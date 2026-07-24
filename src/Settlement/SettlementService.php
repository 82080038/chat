<?php

declare(strict_types=1);

namespace Platform\Settlement;

use Platform\Core\BaseService;
use Platform\Core\Exceptions\ApiException;

final class SettlementService extends BaseService implements SettlementServiceInterface
{
    // ─── Settlements ─────────────────────────────────────────────────────

    public function listSettlements(array $filters, int $page, int $perPage): array
    {
        [$page, $perPage, $offset] = $this->parsePagination(
            ['page' => $page, 'per_page' => $perPage]
        );
        $where = [];
        $params = [];
        if (isset($filters['portfolio_id'])) {
            $where[] = 'portfolio_id = :portfolio_id';
            $params[':portfolio_id'] = $filters['portfolio_id'];
        }
        if (isset($filters['status'])) {
            $where[] = 'status = :status';
            $params[':status'] = $filters['status'];
        }
        if (isset($filters['settlement_date_from'])) {
            $where[] = 'settlement_date >= :from_date';
            $params[':from_date'] = $filters['settlement_date_from'];
        }
        if (isset($filters['settlement_date_to'])) {
            $where[] = 'settlement_date <= :to_date';
            $params[':to_date'] = $filters['settlement_date_to'];
        }
        $clause = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
        $total = $this->countRows('settlement.settlement', $clause, $params);
        $stmt = $this->db->prepare(
            "SELECT * FROM settlement.settlement {$clause} "
            . "ORDER BY settlement_date ASC LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return $this->paginate($stmt->fetchAll(), $total, $page, $perPage);
    }

    public function getSettlement(string $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM settlement.settlement WHERE settlement_id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function getSettlementByExecution(string $executionId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM settlement.settlement WHERE execution_id = :id'
        );
        $stmt->execute([':id' => $executionId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function getPendingSettlements(string $portfolioId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM settlement.settlement
             WHERE portfolio_id = :id AND status = :status
             ORDER BY settlement_date ASC'
        );
        $stmt->execute([':id' => $portfolioId, ':status' => 'PENDING']);
        return $stmt->fetchAll();
    }

    public function processSettlement(string $settlementId): array
    {
        $existing = $this->getSettlement($settlementId);
        if ($existing === null) {
            throw new ApiException(404, 'SETTLEMENT_NOT_FOUND', 'Settlement was not found');
        }
        $now = $this->now();
        $stmt = $this->db->prepare(
            'UPDATE settlement.settlement
             SET status = :status, settled_at = :now
             WHERE settlement_id = :id'
        );
        $stmt->execute([':status' => 'SETTLED', ':now' => $now, ':id' => $settlementId]);
        return $this->getSettlement($settlementId);
    }

    public function createSettlement(array $data): array
    {
        $this->validateRequired(
            $data,
            [
                'execution_id', 'portfolio_id', 'instrument_id',
                'trade_date', 'settlement_date', 'quantity', 'price',
                'gross_amount', 'net_amount', 'currency',
            ]
        );
        $id = $this->uuid();
        $now = $this->now();
        $stmt = $this->db->prepare(
            'INSERT INTO settlement.settlement
             (settlement_id, execution_id, portfolio_id, instrument_id,
              settlement_type, trade_date, settlement_date, quantity, price,
              gross_amount, commission, fees, taxes, net_amount, currency,
              status, settled_at, created_at)
             VALUES
             (:id, :execution_id, :portfolio_id, :instrument_id,
              :settlement_type, :trade_date, :settlement_date, :quantity, :price,
              :gross_amount, :commission, :fees, :taxes, :net_amount, :currency,
              :status, NULL, :now)'
        );
        $stmt->execute([
            ':id' => $id,
            ':execution_id' => $data['execution_id'],
            ':portfolio_id' => $data['portfolio_id'],
            ':instrument_id' => $data['instrument_id'],
            ':settlement_type' => $data['settlement_type'] ?? 'T_PLUS_2',
            ':trade_date' => $data['trade_date'],
            ':settlement_date' => $data['settlement_date'],
            ':quantity' => $data['quantity'],
            ':price' => $data['price'],
            ':gross_amount' => $data['gross_amount'],
            ':commission' => $data['commission'] ?? 0,
            ':fees' => $data['fees'] ?? 0,
            ':taxes' => $data['taxes'] ?? 0,
            ':net_amount' => $data['net_amount'],
            ':currency' => strtoupper($data['currency']),
            ':status' => $data['status'] ?? 'PENDING',
            ':now' => $now,
        ]);
        return $this->getSettlement($id);
    }

    // ─── Reconciliations ─────────────────────────────────────────────────

    public function listReconciliations(array $filters, int $page, int $perPage): array
    {
        [$page, $perPage, $offset] = $this->parsePagination(
            ['page' => $page, 'per_page' => $perPage]
        );
        $where = [];
        $params = [];
        if (isset($filters['portfolio_id'])) {
            $where[] = 'portfolio_id = :portfolio_id';
            $params[':portfolio_id'] = $filters['portfolio_id'];
        }
        if (isset($filters['status'])) {
            $where[] = 'status = :status';
            $params[':status'] = $filters['status'];
        }
        if (isset($filters['reconciliation_type'])) {
            $where[] = 'reconciliation_type = :reconciliation_type';
            $params[':reconciliation_type'] = $filters['reconciliation_type'];
        }
        $clause = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
        $total = $this->countRows('settlement.reconciliation', $clause, $params);
        $stmt = $this->db->prepare(
            "SELECT * FROM settlement.reconciliation {$clause} "
            . "ORDER BY detected_at DESC LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return $this->paginate($stmt->fetchAll(), $total, $page, $perPage);
    }

    public function getReconciliation(string $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM settlement.reconciliation WHERE reconciliation_id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function listPortfolioReconciliations(string $portfolioId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM settlement.reconciliation
             WHERE portfolio_id = :id
             ORDER BY detected_at DESC'
        );
        $stmt->execute([':id' => $portfolioId]);
        return $stmt->fetchAll();
    }

    public function createReconciliation(array $data): array
    {
        $this->validateRequired(
            $data,
            ['portfolio_id', 'reconciliation_type', 'reconciliation_date']
        );
        $id = $this->uuid();
        $now = $this->now();
        $discrepancy = null;
        if (isset($data['internal_value']) && isset($data['broker_value'])) {
            $discrepancy = (float) $data['broker_value'] - (float) $data['internal_value'];
        }
        $stmt = $this->db->prepare(
            'INSERT INTO settlement.reconciliation
             (reconciliation_id, portfolio_id, reconciliation_type,
              reconciliation_date, internal_record_id, broker_record_id,
              internal_value, broker_value, discrepancy, status,
              detected_at, resolved_at, resolution, created_at)
             VALUES
             (:id, :portfolio_id, :reconciliation_type,
              :reconciliation_date, :internal_record_id, :broker_record_id,
              :internal_value, :broker_value, :discrepancy, :status,
              :now, NULL, NULL, :now)'
        );
        $stmt->execute([
            ':id' => $id,
            ':portfolio_id' => $data['portfolio_id'],
            ':reconciliation_type' => $data['reconciliation_type'],
            ':reconciliation_date' => $data['reconciliation_date'],
            ':internal_record_id' => $data['internal_record_id'] ?? null,
            ':broker_record_id' => $data['broker_record_id'] ?? null,
            ':internal_value' => $data['internal_value'] ?? null,
            ':broker_value' => $data['broker_value'] ?? null,
            ':discrepancy' => $discrepancy,
            ':status' => $data['status'] ?? 'PENDING',
            ':now' => $now,
        ]);
        return $this->getReconciliation($id);
    }

    public function resolveReconciliation(string $id, string $resolution): array
    {
        $existing = $this->getReconciliation($id);
        if ($existing === null) {
            throw new ApiException(
                404,
                'RECONCILIATION_NOT_FOUND',
                'Reconciliation was not found'
            );
        }
        $now = $this->now();
        $stmt = $this->db->prepare(
            'UPDATE settlement.reconciliation
             SET status = :status, resolved_at = :now, resolution = :resolution
             WHERE reconciliation_id = :id'
        );
        $stmt->execute([
            ':status' => 'RESOLVED',
            ':now' => $now,
            ':resolution' => $resolution,
            ':id' => $id,
        ]);
        return $this->getReconciliation($id);
    }

    // ─── Private Helpers ─────────────────────────────────────────────────

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
