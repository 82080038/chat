<?php

declare(strict_types=1);

namespace Platform\Microstructure;

use Platform\Core\BaseService;
use Platform\Core\Exceptions\ApiException;

final class MicrostructureService extends BaseService implements MicrostructureServiceInterface
{
    // ─── Order Book Snapshots ────────────────────────────────────────────

    public function captureOrderBook(array $data): array
    {
        $this->validateRequired($data, ['instrument_id', 'exchange_id', 'timestamp']);

        $id = $this->uuid();
        $now = $this->now();

        $bid1 = $data['bid_price_1'] ?? null;
        $ask1 = $data['ask_price_1'] ?? null;

        $midPrice = null;
        $spread = null;
        $spreadBps = null;
        if ($bid1 !== null && $ask1 !== null) {
            $midPrice = ($bid1 + $ask1) / 2;
            $spread = $ask1 - $bid1;
            if ($midPrice > 0) {
                $spreadBps = ($spread / $midPrice) * 10000;
            }
        }

        $totalBidVol = ($data['bid_volume_1'] ?? 0) + ($data['bid_volume_2'] ?? 0)
            + ($data['bid_volume_3'] ?? 0) + ($data['bid_volume_4'] ?? 0)
            + ($data['bid_volume_5'] ?? 0);
        $totalAskVol = ($data['ask_volume_1'] ?? 0) + ($data['ask_volume_2'] ?? 0)
            + ($data['ask_volume_3'] ?? 0) + ($data['ask_volume_4'] ?? 0)
            + ($data['ask_volume_5'] ?? 0);

        $imbalance = null;
        $totalVol = $totalBidVol + $totalAskVol;
        if ($totalVol > 0) {
            $imbalance = ($totalBidVol - $totalAskVol) / $totalVol;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO microstructure.order_book_snapshot
             (snapshot_id, instrument_id, exchange_id, timestamp,
              bid_price_1, bid_volume_1, bid_price_2, bid_volume_2,
              bid_price_3, bid_volume_3, bid_price_4, bid_volume_4,
              bid_price_5, bid_volume_5,
              ask_price_1, ask_volume_1, ask_price_2, ask_volume_2,
              ask_price_3, ask_volume_3, ask_price_4, ask_volume_4,
              ask_price_5, ask_volume_5,
              mid_price, spread, spread_bps,
              total_bid_volume, total_ask_volume, imbalance,
              source, created_at)
             VALUES
             (:id, :instrument_id, :exchange_id, :timestamp,
              :bp1, :bv1, :bp2, :bv2, :bp3, :bv3, :bp4, :bv4, :bp5, :bv5,
              :ap1, :av1, :ap2, :av2, :ap3, :av3, :ap4, :av4, :ap5, :av5,
              :mid, :spread, :spread_bps,
              :tbv, :tav, :imbalance,
              :source, :now)'
        );

        $stmt->execute([
            ':id' => $id,
            ':instrument_id' => $data['instrument_id'],
            ':exchange_id' => $data['exchange_id'],
            ':timestamp' => $data['timestamp'],
            ':bp1' => $data['bid_price_1'] ?? null,
            ':bv1' => $data['bid_volume_1'] ?? null,
            ':bp2' => $data['bid_price_2'] ?? null,
            ':bv2' => $data['bid_volume_2'] ?? null,
            ':bp3' => $data['bid_price_3'] ?? null,
            ':bv3' => $data['bid_volume_3'] ?? null,
            ':bp4' => $data['bid_price_4'] ?? null,
            ':bv4' => $data['bid_volume_4'] ?? null,
            ':bp5' => $data['bid_price_5'] ?? null,
            ':bv5' => $data['bid_volume_5'] ?? null,
            ':ap1' => $data['ask_price_1'] ?? null,
            ':av1' => $data['ask_volume_1'] ?? null,
            ':ap2' => $data['ask_price_2'] ?? null,
            ':av2' => $data['ask_volume_2'] ?? null,
            ':ap3' => $data['ask_price_3'] ?? null,
            ':av3' => $data['ask_volume_3'] ?? null,
            ':ap4' => $data['ask_price_4'] ?? null,
            ':av4' => $data['ask_volume_4'] ?? null,
            ':ap5' => $data['ask_price_5'] ?? null,
            ':av5' => $data['ask_volume_5'] ?? null,
            ':mid' => $midPrice,
            ':spread' => $spread,
            ':spread_bps' => $spreadBps,
            ':tbv' => $totalBidVol,
            ':tav' => $totalAskVol,
            ':imbalance' => $imbalance,
            ':source' => $data['source'] ?? 'MANUAL',
            ':now' => $now,
        ]);

        return $this->getOrderBook($id);
    }

    public function getOrderBook(string $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM microstructure.order_book_snapshot
             WHERE snapshot_id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function getLatestOrderBook(string $instrumentId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM microstructure.order_book_snapshot
             WHERE instrument_id = :id
             ORDER BY timestamp DESC LIMIT 1'
        );
        $stmt->execute([':id' => $instrumentId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function listOrderBooks(array $filters, int $page, int $perPage): array
    {
        [$page, $perPage, $offset] = $this->parsePagination(
            ['page' => $page, 'per_page' => $perPage]
        );

        $where = [];
        $params = [];

        if (isset($filters['instrument_id'])) {
            $where[] = 'instrument_id = :instrument_id';
            $params[':instrument_id'] = $filters['instrument_id'];
        }
        if (isset($filters['exchange_id'])) {
            $where[] = 'exchange_id = :exchange_id';
            $params[':exchange_id'] = $filters['exchange_id'];
        }
        if (isset($filters['from_date'])) {
            $where[] = 'timestamp >= :from_date';
            $params[':from_date'] = $filters['from_date'];
        }
        if (isset($filters['to_date'])) {
            $where[] = 'timestamp <= :to_date';
            $params[':to_date'] = $filters['to_date'];
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $countStmt = $this->db->prepare(
            "SELECT COUNT(*) FROM microstructure.order_book_snapshot {$whereClause}"
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->db->prepare(
            "SELECT * FROM microstructure.order_book_snapshot
             {$whereClause}
             ORDER BY timestamp DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);

        return $this->paginate($stmt->fetchAll(), $total, $page, $perPage);
    }

    // ─── Spread Analysis ─────────────────────────────────────────────────

    public function calculateSpreadAnalysis(string $instrumentId, int $days = 30): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                AVG(spread_bps) as avg_spread_bps,
                MAX(spread_bps) as max_spread_bps,
                MIN(spread_bps) as min_spread_bps,
                AVG(total_bid_volume) as avg_bid_depth,
                AVG(total_ask_volume) as avg_ask_depth,
                AVG(imbalance) as avg_imbalance,
                COUNT(*) as sample_count
             FROM microstructure.order_book_snapshot
             WHERE instrument_id = :id
               AND timestamp >= DATE_SUB(NOW(), INTERVAL :days DAY)
               AND spread_bps IS NOT NULL'
        );
        $stmt->execute([':id' => $instrumentId, ':days' => $days]);
        $row = $stmt->fetch();

        if ($row === false) {
            return [
                'instrument_id' => $instrumentId,
                'period_days' => $days,
                'avg_spread_bps' => null,
                'max_spread_bps' => null,
                'min_spread_bps' => null,
                'avg_bid_depth' => null,
                'avg_ask_depth' => null,
                'avg_imbalance' => null,
                'liquidity_regime' => 'NORMAL',
                'sample_count' => 0,
            ];
        }

        $avgSpread = $row['avg_spread_bps'] !== null
            ? (float) $row['avg_spread_bps'] : null;
        $liquidityRegime = $this->classifyLiquidityRegime($avgSpread);

        return [
            'instrument_id' => $instrumentId,
            'period_days' => $days,
            'avg_spread_bps' => $avgSpread !== null
                ? round($avgSpread, 4) : null,
            'max_spread_bps' => $row['max_spread_bps'] !== null
                ? round((float) $row['max_spread_bps'], 4) : null,
            'min_spread_bps' => $row['min_spread_bps'] !== null
                ? round((float) $row['min_spread_bps'], 4) : null,
            'avg_bid_depth' => $row['avg_bid_depth'] !== null
                ? round((float) $row['avg_bid_depth'], 2) : null,
            'avg_ask_depth' => $row['avg_ask_depth'] !== null
                ? round((float) $row['avg_ask_depth'], 2) : null,
            'avg_imbalance' => $row['avg_imbalance'] !== null
                ? round((float) $row['avg_imbalance'], 6) : null,
            'liquidity_regime' => $liquidityRegime,
            'sample_count' => (int) $row['sample_count'],
        ];
    }

    // ─── Market Impact ───────────────────────────────────────────────────

    public function calculateMarketImpact(
        string $instrumentId,
        float $orderQuantity,
        string $side
    ): array {
        $orderBook = $this->getLatestOrderBook($instrumentId);

        if ($orderBook === null) {
            throw new ApiException(
                404,
                'ORDER_BOOK_NOT_FOUND',
                'No order book snapshot found for instrument'
            );
        }

        $isBuy = strtoupper($side) === 'BUY';
        $filledQty = 0;
        $totalCost = 0.0;

        for ($i = 1; $i <= 5; $i++) {
            $priceCol = $isBuy ? "ask_price_{$i}" : "bid_price_{$i}";
            $volCol = $isBuy ? "ask_volume_{$i}" : "bid_volume_{$i}";

            $price = $orderBook[$priceCol] ?? null;
            $vol = $orderBook[$volCol] ?? null;

            if ($price === null || $vol === null || $vol <= 0) {
                continue;
            }

            $remaining = $orderQuantity - $filledQty;
            if ($remaining <= 0) {
                break;
            }

            $fillQty = min($remaining, $vol);
            $filledQty += $fillQty;
            $totalCost += $fillQty * (float) $price;
        }

        $avgExecPrice = $filledQty > 0 ? $totalCost / $filledQty : null;
        $midPrice = $orderBook['mid_price'] !== null
            ? (float) $orderBook['mid_price'] : null;

        $marketImpactBps = null;
        $slippage = null;
        if ($avgExecPrice !== null && $midPrice !== null && $midPrice > 0) {
            $slippage = $avgExecPrice - $midPrice;
            $marketImpactBps = ($slippage / $midPrice) * 10000;
        }

        $fullyFilled = $filledQty >= $orderQuantity;

        return [
            'instrument_id' => $instrumentId,
            'side' => strtoupper($side),
            'order_quantity' => $orderQuantity,
            'filled_quantity' => $filledQty,
            'fully_filled' => $fullyFilled,
            'avg_execution_price' => $avgExecPrice !== null
                ? round($avgExecPrice, 8) : null,
            'mid_price' => $midPrice !== null
                ? round($midPrice, 8) : null,
            'slippage' => $slippage !== null
                ? round($slippage, 8) : null,
            'market_impact_bps' => $marketImpactBps !== null
                ? round($marketImpactBps, 4) : null,
            'snapshot_timestamp' => $orderBook['timestamp'],
        ];
    }

    // ─── Liquidity Profile ───────────────────────────────────────────────

    public function calculateLiquidityProfile(string $instrumentId): array
    {
        $spreadAnalysis = $this->calculateSpreadAnalysis($instrumentId, 30);

        // Get avg daily volume from OHLCV
        $stmt = $this->db->prepare(
            'SELECT AVG(volume) as avg_daily_volume
             FROM data_ingestion.ohlcv_daily
             WHERE instrument_id = :id
               AND trade_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)'
        );
        $stmt->execute([':id' => $instrumentId]);
        $avgVol = $stmt->fetchColumn();

        $avgDailyVolume = $avgVol !== false && $avgVol !== null
            ? (float) $avgVol : 0.0;

        // Estimated liquidation days (assume 10% of daily volume can be liquidated)
        $estLiquidationDays = null;
        if ($avgDailyVolume > 0) {
            // Get position size if available
            $estLiquidationDays = 0.0;
        }

        // Liquidity score (0-100)
        $spreadScore = 0.0;
        if ($spreadAnalysis['avg_spread_bps'] !== null) {
            $s = $spreadAnalysis['avg_spread_bps'];
            if ($s <= 1) {
                $spreadScore = 100;
            } elseif ($s <= 5) {
                $spreadScore = 80;
            } elseif ($s <= 10) {
                $spreadScore = 60;
            } elseif ($s <= 20) {
                $spreadScore = 40;
            } elseif ($s <= 50) {
                $spreadScore = 20;
            } else {
                $spreadScore = 5;
            }
        }

        $volumeScore = 0.0;
        if ($avgDailyVolume > 1000000) {
            $volumeScore = 100;
        } elseif ($avgDailyVolume > 500000) {
            $volumeScore = 80;
        } elseif ($avgDailyVolume > 100000) {
            $volumeScore = 60;
        } elseif ($avgDailyVolume > 10000) {
            $volumeScore = 40;
        } elseif ($avgDailyVolume > 1000) {
            $volumeScore = 20;
        } else {
            $volumeScore = 5;
        }

        $depthScore = 0.0;
        if ($spreadAnalysis['avg_bid_depth'] !== null) {
            $d = (float) $spreadAnalysis['avg_bid_depth'];
            if ($d > 100000) {
                $depthScore = 100;
            } elseif ($d > 50000) {
                $depthScore = 80;
            } elseif ($d > 10000) {
                $depthScore = 60;
            } elseif ($d > 1000) {
                $depthScore = 40;
            } else {
                $depthScore = 20;
            }
        }

        $liquidityScore = ($spreadScore * 0.4) + ($volumeScore * 0.3)
            + ($depthScore * 0.3);

        return [
            'instrument_id' => $instrumentId,
            'liquidity_score' => round($liquidityScore, 2),
            'liquidity_grade' => $this->liquidityGrade($liquidityScore),
            'liquidity_regime' => $spreadAnalysis['liquidity_regime'],
            'avg_spread_bps' => $spreadAnalysis['avg_spread_bps'],
            'avg_daily_volume' => round($avgDailyVolume, 2),
            'avg_bid_depth' => $spreadAnalysis['avg_bid_depth'],
            'avg_ask_depth' => $spreadAnalysis['avg_ask_depth'],
            'avg_imbalance' => $spreadAnalysis['avg_imbalance'],
            'estimated_liquidation_days' => $estLiquidationDays,
            'spread_score' => round($spreadScore, 2),
            'volume_score' => round($volumeScore, 2),
            'depth_score' => round($depthScore, 2),
        ];
    }

    // ─── Metrics ─────────────────────────────────────────────────────────

    public function listMetrics(array $filters, int $page, int $perPage): array
    {
        [$page, $perPage, $offset] = $this->parsePagination(
            ['page' => $page, 'per_page' => $perPage]
        );

        $where = [];
        $params = [];

        if (isset($filters['instrument_id'])) {
            $where[] = 'instrument_id = :instrument_id';
            $params[':instrument_id'] = $filters['instrument_id'];
        }
        if (isset($filters['from_date'])) {
            $where[] = 'metric_date >= :from_date';
            $params[':from_date'] = $filters['from_date'];
        }
        if (isset($filters['to_date'])) {
            $where[] = 'metric_date <= :to_date';
            $params[':to_date'] = $filters['to_date'];
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $countStmt = $this->db->prepare(
            "SELECT COUNT(*) FROM microstructure.metrics {$whereClause}"
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->db->prepare(
            "SELECT * FROM microstructure.metrics
             {$whereClause}
             ORDER BY metric_date DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);

        return $this->paginate($stmt->fetchAll(), $total, $page, $perPage);
    }

    public function getMetrics(string $instrumentId, string $date): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM microstructure.metrics
             WHERE instrument_id = :id AND metric_date = :date'
        );
        $stmt->execute([':id' => $instrumentId, ':date' => $date]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    // ─── Helpers ─────────────────────────────────────────────────────────

    private function classifyLiquidityRegime(?float $avgSpreadBps): string
    {
        if ($avgSpreadBps === null) {
            return 'NORMAL';
        }
        if ($avgSpreadBps <= 5) {
            return 'NORMAL';
        }
        if ($avgSpreadBps <= 20) {
            return 'THIN';
        }
        return 'STRESS';
    }

    private function liquidityGrade(float $score): string
    {
        if ($score >= 80) {
            return 'EXCELLENT';
        }
        if ($score >= 65) {
            return 'GOOD';
        }
        if ($score >= 50) {
            return 'FAIR';
        }
        if ($score >= 35) {
            return 'POOR';
        }
        return 'ILLIQUID';
    }

    private function validateRequired(array $data, array $fields): void
    {
        $errors = [];
        foreach ($fields as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                $errors[] = $field;
            }
        }
        if ($errors) {
            throw new ApiException(
                400,
                'VALIDATION_ERROR',
                'Missing required fields: ' . implode(', ', $errors)
            );
        }
    }
}
