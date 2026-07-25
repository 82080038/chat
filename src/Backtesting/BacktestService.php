<?php

declare(strict_types=1);

namespace Platform\Backtesting;

use Platform\Core\BaseService;
use Platform\Core\Exceptions\ApiException;

final class BacktestService extends BaseService implements BacktestServiceInterface
{
    public function createRun(array $data): array
    {
        $required = ['strategy_name', 'start_date', 'end_date', 'initial_capital'];
        foreach ($required as $field) {
            if (!array_key_exists($field, $data)) {
                throw new ApiException(
                    422,
                    'VALIDATION_ERROR',
                    "Field {$field} is required",
                    [$field => 'Required']
                );
            }
        }

        if (strtotime($data['end_date']) < strtotime($data['start_date'])) {
            throw new ApiException(
                422,
                'INVALID_DATE_RANGE',
                'end_date must be after start_date'
            );
        }

        if ((float) $data['initial_capital'] <= 0) {
            throw new ApiException(
                422,
                'INVALID_CAPITAL',
                'initial_capital must be positive'
            );
        }

        $id = $this->uuid();
        $now = $this->now();

        $sql = 'INSERT INTO backtesting.backtest_run
            (run_id, strategy_name, instrument_id, portfolio_id,
             start_date, end_date, initial_capital, final_capital,
             status, parameters, created_at)
            VALUES
            (:id, :strategy, :instrument, :portfolio,
             :start, :end, :capital, NULL,
             :status, :params, :now)';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id' => $id,
            ':strategy' => $data['strategy_name'],
            ':instrument' => $data['instrument_id'] ?? null,
            ':portfolio' => $data['portfolio_id'] ?? null,
            ':start' => $data['start_date'],
            ':end' => $data['end_date'],
            ':capital' => $data['initial_capital'],
            ':status' => 'PENDING',
            ':params' => isset($data['parameters'])
                ? json_encode($data['parameters'])
                : null,
            ':now' => $now,
        ]);

        return $this->getRun($id);
    }

    public function getRun(string $runId): ?array
    {
        $sql = 'SELECT * FROM backtesting.backtest_run WHERE run_id = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $runId]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        if ($row['parameters'] !== null) {
            $row['parameters'] = json_decode($row['parameters'], true);
        }
        return $row;
    }

    public function listRuns(array $filters, int $page, int $perPage): array
    {
        [$page, $perPage, $offset] = $this->parsePagination(
            ['page' => $page, 'per_page' => $perPage]
        );

        $where = [];
        $params = [];
        if (isset($filters['status'])) {
            $where[] = 'status = :status';
            $params[':status'] = $filters['status'];
        }
        if (isset($filters['strategy_name'])) {
            $where[] = 'strategy_name = :strategy';
            $params[':strategy'] = $filters['strategy_name'];
        }

        $clause = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

        $countSql = "SELECT COUNT(*) FROM backtesting.backtest_run {$clause}";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT * FROM backtesting.backtest_run {$clause} "
            . "ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $this->paginate($stmt->fetchAll(), $total, $page, $perPage);
    }

    public function executeRun(string $runId, array $priceData): array
    {
        $run = $this->getRun($runId);
        if ($run === null) {
            throw new ApiException(404, 'RUN_NOT_FOUND', 'Backtest run not found');
        }

        if ($run['status'] === 'COMPLETED') {
            throw new ApiException(
                409,
                'RUN_ALREADY_COMPLETED',
                'Backtest run has already been executed'
            );
        }

        $trades = $this->replayStrategy($run, $priceData);
        $this->persistTrades($runId, $trades);

        $metrics = $this->calculateMetrics(
            $trades,
            (float) $run['initial_capital']
        );
        $this->persistMetrics($runId, $metrics);

        $finalCapital = (float) $run['initial_capital'] + $metrics['total_pnl'];
        $this->updateRunStatus($runId, 'COMPLETED', $finalCapital);

        return [
            'run_id' => $runId,
            'status' => 'COMPLETED',
            'final_capital' => $finalCapital,
            'total_trades' => count($trades),
            'metrics' => $metrics,
        ];
    }

    public function getRunTrades(string $runId): array
    {
        $sql = 'SELECT * FROM backtesting.backtest_trade '
            . 'WHERE run_id = :id ORDER BY entry_date ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $runId]);
        return $stmt->fetchAll();
    }

    public function getRunMetrics(string $runId): ?array
    {
        $sql = 'SELECT * FROM backtesting.backtest_metrics WHERE run_id = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $runId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function calculateMetrics(array $trades, float $initialCapital): array
    {
        $totalTrades = count($trades);
        if ($totalTrades === 0) {
            return [
                'total_return' => 0.0,
                'annualized_return' => 0.0,
                'sharpe_ratio' => 0.0,
                'sortino_ratio' => 0.0,
                'max_drawdown' => 0.0,
                'win_rate' => 0.0,
                'profit_factor' => 0.0,
                'total_trades' => 0,
                'winning_trades' => 0,
                'losing_trades' => 0,
                'avg_win' => 0.0,
                'avg_loss' => 0.0,
                'total_pnl' => 0.0,
            ];
        }

        $pnls = [];
        $wins = 0;
        $losses = 0;
        $totalWin = 0.0;
        $totalLoss = 0.0;
        $totalPnl = 0.0;

        foreach ($trades as $trade) {
            $pnl = (float) ($trade['pnl'] ?? 0);
            $pnls[] = $pnl;
            $totalPnl += $pnl;

            if ($pnl > 0) {
                $wins++;
                $totalWin += $pnl;
            } elseif ($pnl < 0) {
                $losses++;
                $totalLoss += abs($pnl);
            }
        }

        $winRate = ($wins / $totalTrades) * 100;
        $profitFactor = $totalLoss > 0
            ? $totalWin / $totalLoss
            : ($totalWin > 0 ? 999.99 : 0.0);
        $avgWin = $wins > 0 ? $totalWin / $wins : 0.0;
        $avgLoss = $losses > 0 ? $totalLoss / $losses : 0.0;
        $totalReturn = $initialCapital > 0
            ? ($totalPnl / $initialCapital) * 100
            : 0.0;

        $returns = [];
        $capital = $initialCapital;
        foreach ($pnls as $pnl) {
            $capital += $pnl;
            $returns[] = $initialCapital > 0 ? $pnl / $initialCapital : 0.0;
        }

        $sharpe = $this->calculateSharpe($returns);
        $sortino = $this->calculateSortino($returns);
        $maxDrawdown = $this->calculateMaxDrawdown($pnls, $initialCapital);

        return [
            'total_return' => round($totalReturn, 4),
            'annualized_return' => round($this->calculateAnnualizedReturn($totalReturn, $trades), 4),
            'sharpe_ratio' => round($sharpe, 4),
            'sortino_ratio' => round($sortino, 4),
            'max_drawdown' => round($maxDrawdown, 4),
            'win_rate' => round($winRate, 4),
            'profit_factor' => round($profitFactor, 4),
            'total_trades' => $totalTrades,
            'winning_trades' => $wins,
            'losing_trades' => $losses,
            'avg_win' => round($avgWin, 4),
            'avg_loss' => round($avgLoss, 4),
            'total_pnl' => round($totalPnl, 4),
        ];
    }

    private function replayStrategy(array $run, array $priceData): array
    {
        $instrumentId = $run['instrument_id'] ?? 'unknown';
        $strategyName = strtoupper($run['strategy_name'] ?? 'BUY_AND_HOLD');
        $initialCapital = (float) ($run['initial_capital'] ?? 100000);
        $params = $run['parameters'] ?? [];
        if (!is_array($params)) {
            $params = [];
        }

        $sorted = $priceData;
        usort($sorted, function ($a, $b) {
            return strcmp($a['date'] ?? '', $b['date'] ?? '');
        });

        $closes = [];
        $dates = [];
        foreach ($sorted as $bar) {
            $closes[] = (float) ($bar['close'] ?? $bar['price'] ?? 0);
            $dates[] = $bar['date'] ?? '';
        }

        $signals = $this->generateSignals($strategyName, $closes, $params);

        $trades = [];
        $position = null;
        $capital = $initialCapital;

        for ($i = 0; $i < count($sorted); $i++) {
            $price = $closes[$i];
            $date = $dates[$i];
            $signal = $signals[$i] ?? 'HOLD';

            if ($price <= 0) {
                continue;
            }

            if ($position === null && $signal === 'BUY') {
                $maxShares = (int) ($capital / $price);
                $quantity = $maxShares > 0 ? $maxShares : 0;
                if ($quantity === 0) {
                    continue;
                }
                $position = [
                    'instrument_id' => $instrumentId,
                    'side' => 'BUY',
                    'quantity' => $quantity,
                    'entry_price' => $price,
                    'entry_date' => $date,
                    'capital_at_entry' => $capital,
                ];
                $capital -= $quantity * $price;
            } elseif ($position !== null && $signal === 'SELL') {
                $pnl = ($price - $position['entry_price']) * $position['quantity'];
                $pnlPct = $position['entry_price'] > 0
                    ? (($price - $position['entry_price']) / $position['entry_price']) * 100
                    : 0.0;

                $trades[] = [
                    'instrument_id' => $position['instrument_id'],
                    'side' => $position['side'],
                    'quantity' => $position['quantity'],
                    'entry_price' => $position['entry_price'],
                    'exit_price' => $price,
                    'entry_date' => $position['entry_date'],
                    'exit_date' => $date,
                    'pnl' => $pnl,
                    'pnl_pct' => $pnlPct,
                ];
                $capital += $position['quantity'] * $price;
                $position = null;
            }
        }

        if ($position !== null && count($closes) > 0) {
            $lastIdx = count($closes) - 1;
            $lastPrice = $closes[$lastIdx];
            $lastDate = $dates[$lastIdx];
            if ($lastPrice > 0) {
                $pnl = ($lastPrice - $position['entry_price']) * $position['quantity'];
                $pnlPct = $position['entry_price'] > 0
                    ? (($lastPrice - $position['entry_price']) / $position['entry_price']) * 100
                    : 0.0;
                $trades[] = [
                    'instrument_id' => $position['instrument_id'],
                    'side' => $position['side'],
                    'quantity' => $position['quantity'],
                    'entry_price' => $position['entry_price'],
                    'exit_price' => $lastPrice,
                    'entry_date' => $position['entry_date'],
                    'exit_date' => $lastDate,
                    'pnl' => $pnl,
                    'pnl_pct' => $pnlPct,
                ];
            }
        }

        return $trades;
    }

    /**
     * Generate trading signals based on strategy name.
     *
     * Supported strategies:
     * - SMA_CROSSOVER: Buy when short SMA crosses above long SMA, sell on cross below
     * - RSI_MEAN_REVERSION: Buy when RSI < oversold, sell when RSI > overbought
     * - MOMENTUM: Buy when price > SMA and momentum positive, sell on negative momentum
     * - BUY_AND_HOLD: Buy on first bar, sell on last bar
     * - MEAN_REVERSION: Buy when price deviates below SMA by threshold, sell at SMA
     *
     * @param string $strategyName
     * @param array<int, float> $closes
     * @param array<string, mixed> $params
     * @return array<int, string> Array of signals: BUY, SELL, or HOLD
     */
    private function generateSignals(string $strategyName, array $closes, array $params): array
    {
        $n = count($closes);
        $signals = array_fill(0, $n, 'HOLD');

        if ($n === 0) {
            return $signals;
        }

        switch ($strategyName) {
            case 'SMA_CROSSOVER':
            case 'SMA_CROSS':
                $shortPeriod = (int) ($params['short_period'] ?? 10);
                $longPeriod = (int) ($params['long_period'] ?? 30);
                $shortSma = $this->calculateSMA($closes, $shortPeriod);
                $longSma = $this->calculateSMA($closes, $longPeriod);
                $prevAbove = false;
                for ($i = 0; $i < $n; $i++) {
                    if ($shortSma[$i] === null || $longSma[$i] === null) {
                        continue;
                    }
                    $currentlyAbove = $shortSma[$i] > $longSma[$i];
                    if ($currentlyAbove && !$prevAbove) {
                        $signals[$i] = 'BUY';
                    } elseif (!$currentlyAbove && $prevAbove) {
                        $signals[$i] = 'SELL';
                    }
                    $prevAbove = $currentlyAbove;
                }
                break;

            case 'RSI_MEAN_REVERSION':
            case 'RSI':
                $rsiPeriod = (int) ($params['rsi_period'] ?? 14);
                $oversold = (float) ($params['oversold'] ?? 30);
                $overbought = (float) ($params['overbought'] ?? 70);
                $rsi = $this->calculateRSI($closes, $rsiPeriod);
                for ($i = 0; $i < $n; $i++) {
                    if ($rsi[$i] === null) {
                        continue;
                    }
                    if ($rsi[$i] < $oversold) {
                        $signals[$i] = 'BUY';
                    } elseif ($rsi[$i] > $overbought) {
                        $signals[$i] = 'SELL';
                    }
                }
                break;

            case 'MOMENTUM':
                $smaPeriod = (int) ($params['sma_period'] ?? 20);
                $momentumPeriod = (int) ($params['momentum_period'] ?? 10);
                $sma = $this->calculateSMA($closes, $smaPeriod);
                for ($i = $momentumPeriod; $i < $n; $i++) {
                    if ($sma[$i] === null) {
                        continue;
                    }
                    $momentum = $closes[$i] - $closes[$i - $momentumPeriod];
                    if ($closes[$i] > $sma[$i] && $momentum > 0) {
                        $signals[$i] = 'BUY';
                    } elseif ($closes[$i] < $sma[$i] && $momentum < 0) {
                        $signals[$i] = 'SELL';
                    }
                }
                break;

            case 'MEAN_REVERSION':
            case 'BOLLINGER':
                $smaPeriod = (int) ($params['sma_period'] ?? 20);
                $deviation = (float) ($params['deviation'] ?? 2.0);
                $sma = $this->calculateSMA($closes, $smaPeriod);
                for ($i = $smaPeriod; $i < $n; $i++) {
                    if ($sma[$i] === null) {
                        continue;
                    }
                    $slice = array_slice($closes, $i - $smaPeriod + 1, $smaPeriod);
                    $stdDev = $this->stdDev($slice);
                    $lowerBand = $sma[$i] - $deviation * $stdDev;
                    $upperBand = $sma[$i] + $deviation * $stdDev;
                    if ($closes[$i] < $lowerBand) {
                        $signals[$i] = 'BUY';
                    } elseif ($closes[$i] > $upperBand) {
                        $signals[$i] = 'SELL';
                    }
                }
                break;

            case 'BUY_AND_HOLD':
            default:
                if ($n > 0) {
                    $signals[0] = 'BUY';
                    if ($n > 1) {
                        $signals[$n - 1] = 'SELL';
                    }
                }
                break;
        }

        return $signals;
    }

    /**
     * Calculate Simple Moving Average for each point.
     * Returns array of floats (or null for periods without enough data).
     *
     * @param array<int, float> $values
     * @param int $period
     * @return array<int, ?float>
     */
    private function calculateSMA(array $values, int $period): array
    {
        $n = count($values);
        $sma = array_fill(0, $n, null);
        if ($period <= 0 || $n < $period) {
            return $sma;
        }
        $sum = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $sum += $values[$i];
            if ($i >= $period) {
                $sum -= $values[$i - $period];
            }
            if ($i >= $period - 1) {
                $sma[$i] = $sum / $period;
            }
        }
        return $sma;
    }

    /**
     * Calculate Relative Strength Index.
     *
     * @param array<int, float> $closes
     * @param int $period
     * @return array<int, ?float>
     */
    private function calculateRSI(array $closes, int $period): array
    {
        $n = count($closes);
        $rsi = array_fill(0, $n, null);
        if ($n <= $period) {
            return $rsi;
        }

        $gains = [];
        $losses = [];
        for ($i = 1; $i < $n; $i++) {
            $change = $closes[$i] - $closes[$i - 1];
            $gains[] = max(0, $change);
            $losses[] = max(0, -$change);
        }

        $avgGain = array_sum(array_slice($gains, 0, $period)) / $period;
        $avgLoss = array_sum(array_slice($losses, 0, $period)) / $period;

        if ($avgLoss == 0) {
            $rsi[$period] = 100.0;
        } else {
            $rs = $avgGain / $avgLoss;
            $rsi[$period] = 100 - (100 / (1 + $rs));
        }

        for ($i = $period + 1; $i < $n; $i++) {
            $avgGain = (($avgGain * ($period - 1)) + $gains[$i - 1]) / $period;
            $avgLoss = (($avgLoss * ($period - 1)) + $losses[$i - 1]) / $period;
            if ($avgLoss == 0) {
                $rsi[$i] = 100.0;
            } else {
                $rs = $avgGain / $avgLoss;
                $rsi[$i] = 100 - (100 / (1 + $rs));
            }
        }

        return $rsi;
    }

    /**
     * Calculate standard deviation of an array of values.
     *
     * @param array<int, float> $values
     * @return float
     */
    private function stdDev(array $values): float
    {
        $count = count($values);
        if ($count === 0) {
            return 0.0;
        }
        $mean = array_sum($values) / $count;
        $variance = 0.0;
        foreach ($values as $v) {
            $variance += pow($v - $mean, 2);
        }
        return sqrt($variance / $count);
    }

    private function persistTrades(string $runId, array $trades): void
    {
        $sql = 'INSERT INTO backtesting.backtest_trade
            (trade_id, run_id, instrument_id, side, quantity,
             entry_price, exit_price, entry_date, exit_date, pnl, pnl_pct)
            VALUES
            (:id, :run, :inst, :side, :qty,
             :entry, :exit, :entry_dt, :exit_dt, :pnl, :pnl_pct)';

        foreach ($trades as $trade) {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':id' => $this->uuid(),
                ':run' => $runId,
                ':inst' => $trade['instrument_id'],
                ':side' => $trade['side'],
                ':qty' => $trade['quantity'],
                ':entry' => $trade['entry_price'],
                ':exit' => $trade['exit_price'],
                ':entry_dt' => $trade['entry_date'],
                ':exit_dt' => $trade['exit_date'],
                ':pnl' => $trade['pnl'],
                ':pnl_pct' => $trade['pnl_pct'],
            ]);
        }
    }

    private function persistMetrics(string $runId, array $metrics): void
    {
        $id = $this->uuid();
        $now = $this->now();

        $sql = 'INSERT INTO backtesting.backtest_metrics
            (metrics_id, run_id, total_return, annualized_return,
             sharpe_ratio, sortino_ratio, max_drawdown, win_rate,
             profit_factor, total_trades, winning_trades, losing_trades,
             avg_win, avg_loss, created_at)
            VALUES
            (:id, :run, :total_ret, :ann_ret,
             :sharpe, :sortino, :max_dd, :win_rate,
             :pf, :total_t, :win_t, :loss_t,
             :avg_win, :avg_loss, :now)';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id' => $id,
            ':run' => $runId,
            ':total_ret' => $metrics['total_return'],
            ':ann_ret' => $metrics['annualized_return'],
            ':sharpe' => $metrics['sharpe_ratio'],
            ':sortino' => $metrics['sortino_ratio'],
            ':max_dd' => $metrics['max_drawdown'],
            ':win_rate' => $metrics['win_rate'],
            ':pf' => $metrics['profit_factor'],
            ':total_t' => $metrics['total_trades'],
            ':win_t' => $metrics['winning_trades'],
            ':loss_t' => $metrics['losing_trades'],
            ':avg_win' => $metrics['avg_win'],
            ':avg_loss' => $metrics['avg_loss'],
            ':now' => $now,
        ]);
    }

    private function updateRunStatus(
        string $runId,
        string $status,
        float $finalCapital
    ): void {
        $sql = 'UPDATE backtesting.backtest_run
            SET status = :status, final_capital = :capital, completed_at = :now
            WHERE run_id = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':status' => $status,
            ':capital' => $finalCapital,
            ':now' => $this->now(),
            ':id' => $runId,
        ]);
    }

    private function calculateAnnualizedReturn(float $totalReturn, array $trades): float
    {
        if (count($trades) === 0) {
            return 0.0;
        }

        $firstDate = null;
        $lastDate = null;
        foreach ($trades as $trade) {
            $entry = $trade['entry_date'] ?? null;
            $exit = $trade['exit_date'] ?? null;
            if ($entry !== null && ($firstDate === null || strcmp($entry, $firstDate) < 0)) {
                $firstDate = $entry;
            }
            if ($exit !== null && ($lastDate === null || strcmp($exit, $lastDate) > 0)) {
                $lastDate = $exit;
            }
        }

        if ($firstDate === null || $lastDate === null) {
            return $totalReturn;
        }

        $days = (strtotime($lastDate) - strtotime($firstDate)) / 86400;
        if ($days <= 0) {
            return $totalReturn;
        }

        $tradingDays = max(1, (int) round($days * 252 / 365));
        $decimalReturn = $totalReturn / 100;
        $annualized = pow(1 + $decimalReturn, 252 / $tradingDays) - 1;
        return $annualized * 100;
    }

    private function calculateSharpe(array $returns): float
    {
        if (count($returns) < 2) {
            return 0.0;
        }
        $mean = array_sum($returns) / count($returns);
        $variance = 0.0;
        foreach ($returns as $r) {
            $variance += pow($r - $mean, 2);
        }
        $stdDev = sqrt($variance / count($returns));
        return $stdDev > 0 ? ($mean / $stdDev) * sqrt(252) : 0.0;
    }

    private function calculateSortino(array $returns): float
    {
        if (count($returns) < 2) {
            return 0.0;
        }
        $mean = array_sum($returns) / count($returns);
        $downsideReturns = array_filter($returns, fn($r) => $r < 0);
        if (count($downsideReturns) === 0) {
            return $mean > 0 ? 999.99 : 0.0;
        }
        $downsideVariance = 0.0;
        foreach ($downsideReturns as $r) {
            $downsideVariance += pow($r, 2);
        }
        $downsideDev = sqrt($downsideVariance / count($returns));
        return $downsideDev > 0 ? ($mean / $downsideDev) * sqrt(252) : 0.0;
    }

    private function calculateMaxDrawdown(array $pnls, float $initialCapital): float
    {
        $capital = $initialCapital;
        $peak = $initialCapital;
        $maxDd = 0.0;

        foreach ($pnls as $pnl) {
            $capital += $pnl;
            $peak = max($peak, $capital);
            if ($peak > 0) {
                $dd = (($peak - $capital) / $peak) * 100;
                $maxDd = max($maxDd, $dd);
            }
        }
        return $maxDd;
    }
}
