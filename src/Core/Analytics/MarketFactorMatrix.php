<?php

declare(strict_types=1);

namespace Platform\Core\Analytics;

use PDO;
use Platform\Core\Database\MySqlConnection;

/**
 * Market Factor Matrix — factor exposure analysis for portfolio instruments.
 *
 * Blueprint: "Market factor matrix" as remaining work.
 * Calculates exposure to common market factors: market, size, value, momentum, volatility, liquidity.
 */
final class MarketFactorMatrix
{
    private static ?MarketFactorMatrix $instance = null;
    private PDO $db;

    private function __construct()
    {
        $this->db = MySqlConnection::getInstance();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Calculate factor exposure for a single instrument based on OHLCV history.
     *
     * @return array<string, mixed>
     */
    public function calculateInstrumentFactors(string $instrumentId, int $lookbackDays = 252): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM data_ingestion.ohlcv_daily
             WHERE instrument_id = :id
             ORDER BY trade_date DESC LIMIT :limit'
        );
        $stmt->execute([':id' => $instrumentId, ':limit' => $lookbackDays]);
        $rows = array_reverse($stmt->fetchAll());

        if (count($rows) < 30) {
            return [
                'instrument_id' => $instrumentId,
                'factors' => [],
                'error' => 'Insufficient data (minimum 30 data points required)',
                'data_points' => count($rows),
            ];
        }

        $closes = array_map(fn($r) => (float) $r['close'], $rows);
        $volumes = array_map(fn($r) => (float) $r['volume'], $rows);

        // Calculate daily returns
        $returns = [];
        for ($i = 1; $i < count($closes); $i++) {
            $returns[] = ($closes[$i] - $closes[$i - 1]) / $closes[$i - 1];
        }

        // Factor: Momentum (last 20-day return)
        $momentum = $this->calculateMomentum($closes, 20);

        // Factor: Volatility (20-day return std)
        $volatility = $this->calculateVolatility($returns, 20);

        // Factor: Liquidity (avg volume / median volume ratio)
        $liquidity = $this->calculateLiquidity($volumes, 20);

        // Factor: Size (proxy: average traded value)
        $avgPrice = array_sum($closes) / count($closes);
        $avgVolume = array_sum($volumes) / count($volumes);
        $size = $avgPrice * $avgVolume;

        // Factor: Value (proxy: inverse of price relative to 52-week high)
        $high52 = max($closes);
        $value = $high52 > 0 ? ($closes[count($closes) - 1] / $high52) : 1.0;

        // Factor: Mean reversion (last close vs SMA ratio)
        $sma20 = array_sum(array_slice($closes, -20)) / 20;
        $meanReversion = $sma20 > 0 ? ($closes[count($closes) - 1] / $sma20) : 1.0;

        return [
            'instrument_id' => $instrumentId,
            'data_points' => count($rows),
            'lookback_days' => $lookbackDays,
            'factors' => [
                'momentum' => round($momentum, 6),
                'volatility' => round($volatility, 6),
                'liquidity' => round($liquidity, 4),
                'size' => round($size, 2),
                'value' => round($value, 4),
                'mean_reversion' => round($meanReversion, 4),
            ],
            'calculated_at' => gmdate('Y-m-d H:i:s'),
        ];
    }

    /**
     * Calculate factor matrix for all instruments in a portfolio.
     *
     * @return array<string, mixed>
     */
    public function calculatePortfolioFactorMatrix(string $portfolioId): array
    {
        $stmt = $this->db->prepare(
            'SELECT instrument_id, quantity FROM portfolio.position
             WHERE portfolio_id = :id AND status = "OPEN"'
        );
        $stmt->execute([':id' => $portfolioId]);
        $positions = $stmt->fetchAll();

        $matrix = [];
        foreach ($positions as $pos) {
            $factors = $this->calculateInstrumentFactors($pos['instrument_id']);
            $factors['quantity'] = (float) $pos['quantity'];
            $matrix[] = $factors;
        }

        // Aggregate portfolio-level factor exposures
        $aggregate = $this->aggregateFactors($matrix);

        return [
            'portfolio_id' => $portfolioId,
            'instruments' => $matrix,
            'aggregate_exposure' => $aggregate,
            'calculated_at' => gmdate('Y-m-d H:i:s'),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $matrix
     * @return array<string, float>
     */
    private function aggregateFactors(array $matrix): array
    {
        if ($matrix === []) {
            return [];
        }

        $factorKeys = ['momentum', 'volatility', 'liquidity', 'size', 'value', 'mean_reversion'];
        $aggregate = [];

        foreach ($factorKeys as $key) {
            $values = [];
            $weights = [];
            foreach ($matrix as $entry) {
                if (isset($entry['factors'][$key])) {
                    $values[] = (float) $entry['factors'][$key];
                    $weights[] = (float) ($entry['quantity'] ?? 1);
                }
            }
            if ($values !== []) {
                $totalWeight = array_sum($weights);
                if ($totalWeight > 0) {
                    $weightedSum = 0;
                    for ($i = 0; $i < count($values); $i++) {
                        $weightedSum += $values[$i] * $weights[$i];
                    }
                    $aggregate[$key] = round($weightedSum / $totalWeight, 6);
                }
            }
        }

        return $aggregate;
    }

    private function calculateMomentum(array $closes, int $period): float
    {
        $n = count($closes);
        if ($n < $period + 1) {
            return 0.0;
        }
        $current = $closes[$n - 1];
        $past = $closes[$n - 1 - $period];
        return $past > 0 ? ($current - $past) / $past : 0.0;
    }

    private function calculateVolatility(array $returns, int $period): float
    {
        $n = count($returns);
        if ($n < $period) {
            return 0.0;
        }
        $slice = array_slice($returns, -$period);
        $mean = array_sum($slice) / count($slice);
        $variance = array_sum(array_map(fn($r) => ($r - $mean) ** 2, $slice)) / count($slice);
        return $variance ** 0.5;
    }

    private function calculateLiquidity(array $volumes, int $period): float
    {
        $n = count($volumes);
        if ($n < $period) {
            return 0.0;
        }
        $slice = array_slice($volumes, -$period);
        $avg = array_sum($slice) / count($slice);
        sort($slice);
        $median = $slice[(int)(count($slice) / 2)];
        return $median > 0 ? $avg / $median : 0.0;
    }
}
