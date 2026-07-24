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
            'annualized_return' => round($totalReturn * 0.4, 4),
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
        $trades = [];
        $position = null;
        $instrumentId = $run['instrument_id'] ?? 'unknown';

        $sorted = $priceData;
        usort($sorted, function ($a, $b) {
            return strcmp($a['date'] ?? '', $b['date'] ?? '');
        });

        foreach ($sorted as $bar) {
            $price = (float) ($bar['close'] ?? $bar['price'] ?? 0);
            $date = $bar['date'] ?? '';

            if ($position === null && $price > 0) {
                $position = [
                    'instrument_id' => $instrumentId,
                    'side' => 'BUY',
                    'quantity' => 100,
                    'entry_price' => $price,
                    'entry_date' => $date,
                ];
            } elseif ($position !== null && $price > 0) {
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
                $position = null;
            }
        }

        return $trades;
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
