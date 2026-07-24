<?php

declare(strict_types=1);

namespace Platform\Portfolio;

use Platform\Core\BaseService;
use Platform\Core\Exceptions\ApiException;

final class PortfolioService extends BaseService implements PortfolioServiceInterface
{
    // ─── Portfolios ──────────────────────────────────────────────────────

    public function listPortfolios(array $filters, int $page, int $perPage): array
    {
        [$page, $perPage, $offset] = $this->parsePagination(
            ['page' => $page, 'per_page' => $perPage]
        );
        $where = [];
        $params = [];
        if (isset($filters['portfolio_type'])) {
            $where[] = 'portfolio_type = :portfolio_type';
            $params[':portfolio_type'] = $filters['portfolio_type'];
        }
        if (isset($filters['status'])) {
            $where[] = 'status = :status';
            $params[':status'] = $filters['status'];
        }
        $clause = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
        $total = $this->countRows('portfolio.portfolio', $clause, $params);
        $stmt = $this->db->prepare(
            "SELECT * FROM portfolio.portfolio {$clause} "
            . "ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return $this->paginate($stmt->fetchAll(), $total, $page, $perPage);
    }

    public function createPortfolio(array $data): array
    {
        $this->validateRequired($data, ['name', 'base_currency']);
        $id = $this->uuid();
        $now = $this->now();
        $stmt = $this->db->prepare(
            'INSERT INTO portfolio.portfolio
             (portfolio_id, name, description, base_currency, portfolio_type,
              status, inception_date, benchmark_id, risk_profile_id, created_at, updated_at)
             VALUES
             (:id, :name, :description, :base_currency, :portfolio_type,
              :status, :inception_date, :benchmark_id, :risk_profile_id, :now, :now)'
        );
        $stmt->execute([
            ':id' => $id,
            ':name' => $data['name'],
            ':description' => $data['description'] ?? null,
            ':base_currency' => strtoupper($data['base_currency']),
            ':portfolio_type' => $data['portfolio_type'] ?? 'PAPER',
            ':status' => $data['status'] ?? 'ACTIVE',
            ':inception_date' => $data['inception_date'] ?? null,
            ':benchmark_id' => $data['benchmark_id'] ?? null,
            ':risk_profile_id' => $data['risk_profile_id'] ?? null,
            ':now' => $now,
        ]);
        return $this->getPortfolio($id);
    }

    public function getPortfolio(string $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM portfolio.portfolio WHERE portfolio_id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function updatePortfolio(string $id, array $data): array
    {
        $existing = $this->getPortfolio($id);
        if ($existing === null) {
            throw new ApiException(404, 'PORTFOLIO_NOT_FOUND', 'Portfolio was not found');
        }
        $fields = [];
        $params = [':id' => $id];
        foreach (
            [
            'name', 'description', 'base_currency', 'portfolio_type',
            'status', 'inception_date', 'benchmark_id', 'risk_profile_id',
            ] as $field
        ) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = :{$field}";
                $params[":{$field}"] = $data[$field];
            }
        }
        if ($fields === []) {
            return $existing;
        }
        $fields[] = 'updated_at = :updated_at';
        $params[':updated_at'] = $this->now();
        $stmt = $this->db->prepare(
            'UPDATE portfolio.portfolio SET ' . implode(', ', $fields)
            . ' WHERE portfolio_id = :id'
        );
        $stmt->execute($params);
        return $this->getPortfolio($id);
    }

    public function archivePortfolio(string $id): array
    {
        $existing = $this->getPortfolio($id);
        if ($existing === null) {
            throw new ApiException(404, 'PORTFOLIO_NOT_FOUND', 'Portfolio was not found');
        }
        $stmt = $this->db->prepare(
            'UPDATE portfolio.portfolio SET status = :status, updated_at = :now
             WHERE portfolio_id = :id'
        );
        $stmt->execute([':status' => 'ARCHIVED', ':now' => $this->now(), ':id' => $id]);
        return $this->getPortfolio($id);
    }

    public function getPortfolioSummary(string $id): array
    {
        $portfolio = $this->getPortfolio($id);
        if ($portfolio === null) {
            throw new ApiException(404, 'PORTFOLIO_NOT_FOUND', 'Portfolio was not found');
        }
        $currency = $portfolio['base_currency'];

        $posStmt = $this->db->prepare(
            'SELECT COUNT(*) as position_count,
                    COALESCE(SUM(unrealized_pnl), 0) as unrealized_pnl,
                    COALESCE(SUM(realized_pnl), 0) as realized_pnl
             FROM portfolio.position
             WHERE portfolio_id = :id AND status = :status'
        );
        $posStmt->execute([':id' => $id, ':status' => 'OPEN']);
        $posData = $posStmt->fetch();

        $cashStmt = $this->db->prepare(
            'SELECT COALESCE(SUM(available_balance), 0) as cash_balance
             FROM portfolio.cash_balance
             WHERE portfolio_id = :id AND currency = :currency'
        );
        $cashStmt->execute([':id' => $id, ':currency' => $currency]);
        $cashData = $cashStmt->fetch();

        $realizedPnl = (float) ($posData['realized_pnl'] ?? 0);
        $unrealizedPnl = (float) ($posData['unrealized_pnl'] ?? 0);
        $cashBalance = (float) ($cashData['cash_balance'] ?? 0);
        $totalPnl = $realizedPnl + $unrealizedPnl;

        return [
            'portfolio_id' => $id,
            'currency' => $currency,
            'realized_pnl' => $realizedPnl,
            'unrealized_pnl' => $unrealizedPnl,
            'total_pnl' => $totalPnl,
            'cash_balance' => $cashBalance,
            'position_count' => (int) ($posData['position_count'] ?? 0),
            'as_of' => $this->now(),
        ];
    }

    // ─── Positions ───────────────────────────────────────────────────────

    public function getPositions(string $portfolioId, int $page, int $perPage): array
    {
        [$page, $perPage, $offset] = $this->parsePagination(
            ['page' => $page, 'per_page' => $perPage]
        );
        $where = ['portfolio_id = :portfolio_id'];
        $params = [':portfolio_id' => $portfolioId];
        $clause = 'WHERE ' . implode(' AND ', $where);
        $total = $this->countRows('portfolio.position', $clause, $params);
        $stmt = $this->db->prepare(
            "SELECT * FROM portfolio.position {$clause} "
            . "ORDER BY as_of DESC LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return $this->paginate($stmt->fetchAll(), $total, $page, $perPage);
    }

    public function getPosition(string $portfolioId, string $instrumentId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM portfolio.position
             WHERE portfolio_id = :portfolio_id
               AND instrument_id = :instrument_id
               AND status = :status
             ORDER BY as_of DESC LIMIT 1'
        );
        $stmt->execute([
            ':portfolio_id' => $portfolioId,
            ':instrument_id' => $instrumentId,
            ':status' => 'OPEN',
        ]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function getPositionHistory(
        string $portfolioId,
        string $instrumentId,
        string $from,
        string $to
    ): array {
        $stmt = $this->db->prepare(
            'SELECT * FROM portfolio.position_snapshot
             WHERE portfolio_id = :portfolio_id
               AND instrument_id = :instrument_id
               AND snapshot_date BETWEEN :from AND :to
             ORDER BY snapshot_date ASC'
        );
        $stmt->execute([
            ':portfolio_id' => $portfolioId,
            ':instrument_id' => $instrumentId,
            ':from' => $from,
            ':to' => $to,
        ]);
        return $stmt->fetchAll();
    }

    public function openPosition(array $data): array
    {
        $this->validateRequired($data, ['portfolio_id', 'instrument_id', 'quantity']);
        $id = $this->uuid();
        $now = $this->now();
        $stmt = $this->db->prepare(
            'INSERT INTO portfolio.position
             (position_id, portfolio_id, instrument_id, quantity, average_cost,
              realized_pnl, unrealized_pnl, position_type, status, opened_at, as_of)
             VALUES
             (:id, :portfolio_id, :instrument_id, :quantity, :average_cost,
              0, 0, :position_type, :status, :now, :now)'
        );
        $stmt->execute([
            ':id' => $id,
            ':portfolio_id' => $data['portfolio_id'],
            ':instrument_id' => $data['instrument_id'],
            ':quantity' => $data['quantity'],
            ':average_cost' => $data['average_cost'] ?? null,
            ':position_type' => $data['position_type'] ?? 'LONG',
            ':status' => 'OPEN',
            ':now' => $now,
        ]);
        return $this->getPositionById($id);
    }

    public function updatePosition(string $positionId, array $data): array
    {
        $existing = $this->getPositionById($positionId);
        if ($existing === null) {
            throw new ApiException(404, 'POSITION_NOT_FOUND', 'Position was not found');
        }
        $fields = [];
        $params = [':id' => $positionId];
        foreach (['quantity', 'average_cost', 'realized_pnl', 'unrealized_pnl', 'status', 'as_of'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = :{$field}";
                $params[":{$field}"] = $data[$field];
            }
        }
        if ($fields === []) {
            return $existing;
        }
        $stmt = $this->db->prepare(
            'UPDATE portfolio.position SET ' . implode(', ', $fields)
            . ' WHERE position_id = :id'
        );
        $stmt->execute($params);
        return $this->getPositionById($positionId);
    }

    public function closePosition(string $positionId, array $data): array
    {
        $existing = $this->getPositionById($positionId);
        if ($existing === null) {
            throw new ApiException(404, 'POSITION_NOT_FOUND', 'Position was not found');
        }
        $now = $this->now();
        $stmt = $this->db->prepare(
            'UPDATE portfolio.position
             SET status = :status, closed_at = :now, as_of = :now,
                 realized_pnl = :realized_pnl, unrealized_pnl = 0, quantity = 0
             WHERE position_id = :id'
        );
        $stmt->execute([
            ':status' => 'CLOSED',
            ':now' => $now,
            ':realized_pnl' => $data['realized_pnl'] ?? $existing['realized_pnl'],
            ':id' => $positionId,
        ]);
        return $this->getPositionById($positionId);
    }

    // ─── Cash ────────────────────────────────────────────────────────────

    public function getCashBalances(string $portfolioId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM portfolio.cash_balance WHERE portfolio_id = :id'
        );
        $stmt->execute([':id' => $portfolioId]);
        return $stmt->fetchAll();
    }

    public function getCashTransactions(string $portfolioId, int $page, int $perPage): array
    {
        [$page, $perPage, $offset] = $this->parsePagination(
            ['page' => $page, 'per_page' => $perPage]
        );
        $clause = 'WHERE portfolio_id = :portfolio_id';
        $params = [':portfolio_id' => $portfolioId];
        $total = $this->countRows('portfolio.cash_transaction', $clause, $params);
        $stmt = $this->db->prepare(
            "SELECT * FROM portfolio.cash_transaction {$clause} "
            . "ORDER BY value_date DESC, created_at DESC LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return $this->paginate($stmt->fetchAll(), $total, $page, $perPage);
    }

    public function recordCashTransaction(string $portfolioId, array $data): array
    {
        $this->validateRequired($data, ['currency', 'transaction_type', 'amount', 'direction', 'value_date']);
        $id = $this->uuid();
        $now = $this->now();
        $stmt = $this->db->prepare(
            'INSERT INTO portfolio.cash_transaction
             (cash_txn_id, portfolio_id, currency, transaction_type, amount,
              direction, execution_id, description, value_date, created_at, status)
             VALUES
             (:id, :portfolio_id, :currency, :transaction_type, :amount,
              :direction, :execution_id, :description, :value_date, :now, :status)'
        );
        $stmt->execute([
            ':id' => $id,
            ':portfolio_id' => $portfolioId,
            ':currency' => strtoupper($data['currency']),
            ':transaction_type' => $data['transaction_type'],
            ':amount' => $data['amount'],
            ':direction' => $data['direction'],
            ':execution_id' => $data['execution_id'] ?? null,
            ':description' => $data['description'] ?? null,
            ':value_date' => $data['value_date'],
            ':now' => $now,
            ':status' => $data['status'] ?? 'PENDING',
        ]);
        return $this->getCashTransactionById($id);
    }

    // ─── Targets ─────────────────────────────────────────────────────────

    public function getPortfolioTargets(string $portfolioId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM portfolio.portfolio_target
             WHERE portfolio_id = :id
             ORDER BY effective_from DESC'
        );
        $stmt->execute([':id' => $portfolioId]);
        return $stmt->fetchAll();
    }

    public function setPortfolioTarget(string $portfolioId, array $data): array
    {
        $this->validateRequired($data, ['instrument_id', 'target_type', 'effective_from']);
        $id = $this->uuid();
        $now = $this->now();
        $stmt = $this->db->prepare(
            'INSERT INTO portfolio.portfolio_target
             (target_id, portfolio_id, instrument_id, target_weight, target_quantity,
              target_type, min_weight, max_weight, effective_from, effective_until, created_at)
             VALUES
             (:id, :portfolio_id, :instrument_id, :target_weight, :target_quantity,
              :target_type, :min_weight, :max_weight, :effective_from, :effective_until, :now)'
        );
        $stmt->execute([
            ':id' => $id,
            ':portfolio_id' => $portfolioId,
            ':instrument_id' => $data['instrument_id'],
            ':target_weight' => $data['target_weight'] ?? null,
            ':target_quantity' => $data['target_quantity'] ?? null,
            ':target_type' => $data['target_type'],
            ':min_weight' => $data['min_weight'] ?? null,
            ':max_weight' => $data['max_weight'] ?? null,
            ':effective_from' => $data['effective_from'],
            ':effective_until' => $data['effective_until'] ?? null,
            ':now' => $now,
        ]);
        return $this->getTargetById($id);
    }

    public function updatePortfolioTarget(string $targetId, array $data): array
    {
        $existing = $this->getTargetById($targetId);
        if ($existing === null) {
            throw new ApiException(404, 'TARGET_NOT_FOUND', 'Portfolio target was not found');
        }
        $fields = [];
        $params = [':id' => $targetId];
        foreach (
            [
            'target_weight', 'target_quantity', 'target_type',
            'min_weight', 'max_weight', 'effective_until',
            ] as $field
        ) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = :{$field}";
                $params[":{$field}"] = $data[$field];
            }
        }
        if ($fields === []) {
            return $existing;
        }
        $stmt = $this->db->prepare(
            'UPDATE portfolio.portfolio_target SET ' . implode(', ', $fields)
            . ' WHERE target_id = :id'
        );
        $stmt->execute($params);
        return $this->getTargetById($targetId);
    }

    public function removePortfolioTarget(string $targetId): array
    {
        $existing = $this->getTargetById($targetId);
        if ($existing === null) {
            throw new ApiException(404, 'TARGET_NOT_FOUND', 'Portfolio target was not found');
        }
        $stmt = $this->db->prepare('DELETE FROM portfolio.portfolio_target WHERE target_id = :id');
        $stmt->execute([':id' => $targetId]);
        return ['target_id' => $targetId, 'deleted' => true];
    }

    // ─── Accounts ────────────────────────────────────────────────────────

    public function getPortfolioAccounts(string $portfolioId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM portfolio.portfolio_account WHERE portfolio_id = :id'
        );
        $stmt->execute([':id' => $portfolioId]);
        return $stmt->fetchAll();
    }

    public function linkPortfolioAccount(string $portfolioId, array $data): array
    {
        $this->validateRequired($data, ['currency']);
        $id = $this->uuid();
        $now = $this->now();
        $stmt = $this->db->prepare(
            'INSERT INTO portfolio.portfolio_account
             (account_id, portfolio_id, broker_id, broker_account_code,
              account_type, currency, status, opened_at)
             VALUES
             (:id, :portfolio_id, :broker_id, :broker_account_code,
              :account_type, :currency, :status, :now)'
        );
        $stmt->execute([
            ':id' => $id,
            ':portfolio_id' => $portfolioId,
            ':broker_id' => $data['broker_id'] ?? null,
            ':broker_account_code' => $data['broker_account_code'] ?? null,
            ':account_type' => $data['account_type'] ?? 'CASH',
            ':currency' => strtoupper($data['currency']),
            ':status' => $data['status'] ?? 'ACTIVE',
            ':now' => $now,
        ]);
        return $this->getAccountById($id);
    }

    // ─── Private Helpers ─────────────────────────────────────────────────

    private function getPositionById(string $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM portfolio.position WHERE position_id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    private function getCashTransactionById(string $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM portfolio.cash_transaction WHERE cash_txn_id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    private function getTargetById(string $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM portfolio.portfolio_target WHERE target_id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    private function getAccountById(string $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM portfolio.portfolio_account WHERE account_id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    private function validateRequired(array $data, array $fields): void
    {
        $errors = [];
        foreach ($fields as $field) {
            if (!array_key_exists($field, $data) || $data[$field] === '') {
                $errors[$field][] = 'This field is required';
            }
        }
        if ($errors !== []) {
            throw new ApiException(422, 'VALIDATION_ERROR', 'Required fields are missing', $errors);
        }
    }

    private function countRows(string $table, string $clause = '', array $params = []): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM {$table} {$clause}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }
}
