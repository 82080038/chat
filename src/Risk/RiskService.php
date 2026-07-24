<?php

declare(strict_types=1);

namespace Platform\Risk;

use Platform\Core\BaseService;
use Platform\Core\Exceptions\ApiException;

final class RiskService extends BaseService implements RiskServiceInterface
{
    // ─── Risk Profiles ───────────────────────────────────────────────────

    public function listRiskProfiles(array $filters, int $page, int $perPage): array
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
        if (isset($filters['risk_tolerance'])) {
            $where[] = 'risk_tolerance = :risk_tolerance';
            $params[':risk_tolerance'] = $filters['risk_tolerance'];
        }
        $clause = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
        $total = $this->countRows('risk.risk_profile', $clause, $params);
        $stmt = $this->db->prepare(
            "SELECT * FROM risk.risk_profile {$clause} "
            . "ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return $this->paginate($stmt->fetchAll(), $total, $page, $perPage);
    }

    public function createRiskProfile(array $data): array
    {
        $this->validateRequired($data, ['name', 'risk_tolerance']);
        $this->assertTolerance((string) $data['risk_tolerance']);
        $id = $this->uuid();
        $now = $this->now();
        $stmt = $this->db->prepare(
            'INSERT INTO risk.risk_profile
             (risk_profile_id, name, risk_tolerance, max_single_position,
              max_sector_exposure, max_portfolio_beta, max_var_pct,
              max_drawdown_pct, min_liquidity_days, status, created_at, updated_at)
             VALUES
             (:id, :name, :risk_tolerance, :max_single, :max_sector,
              :max_beta, :max_var, :max_dd, :min_liq, :status, :now1, :now2)'
        );
        $stmt->execute([
            ':id' => $id,
            ':name' => $data['name'],
            ':risk_tolerance' => $data['risk_tolerance'],
            ':max_single' => $data['max_single_position'] ?? null,
            ':max_sector' => $data['max_sector_exposure'] ?? null,
            ':max_beta' => $data['max_portfolio_beta'] ?? null,
            ':max_var' => $data['max_var_pct'] ?? null,
            ':max_dd' => $data['max_drawdown_pct'] ?? null,
            ':min_liq' => $data['min_liquidity_days'] ?? null,
            ':status' => $data['status'] ?? 'ACTIVE',
            ':now1' => $now,
            ':now2' => $now,
        ]);
        return $this->getRiskProfile($id);
    }

    public function getRiskProfile(string $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM risk.risk_profile WHERE risk_profile_id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function updateRiskProfile(string $id, array $data): array
    {
        $existing = $this->getRiskProfile($id);
        if ($existing === null) {
            throw new ApiException(404, 'RISK_PROFILE_NOT_FOUND', 'Risk profile was not found');
        }
        $fields = [];
        $params = [':id' => $id];
        foreach (
            [
            'name', 'risk_tolerance', 'max_single_position', 'max_sector_exposure',
            'max_portfolio_beta', 'max_var_pct', 'max_drawdown_pct',
            'min_liquidity_days', 'status',
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
            'UPDATE risk.risk_profile SET ' . implode(', ', $fields)
            . ' WHERE risk_profile_id = :id'
        );
        $stmt->execute($params);
        return $this->getRiskProfile($id);
    }

    // ─── Risk Limits ─────────────────────────────────────────────────────

    public function listRiskLimits(string $portfolioId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM risk.risk_limit
             WHERE portfolio_id = :id
             ORDER BY effective_from DESC'
        );
        $stmt->execute([':id' => $portfolioId]);
        return $stmt->fetchAll();
    }

    public function setRiskLimit(string $portfolioId, array $data): array
    {
        $this->validateRequired($data, ['limit_type', 'limit_value']);
        $id = $this->uuid();
        $now = $this->now();
        $stmt = $this->db->prepare(
            'INSERT INTO risk.risk_limit
             (risk_limit_id, portfolio_id, limit_type, limit_value, limit_unit,
              time_horizon, confidence_level, status, effective_from, effective_until, created_at)
             VALUES
             (:id, :portfolio_id, :limit_type, :limit_value, :limit_unit,
              :time_horizon, :confidence_level, :status, :now1, :effective_until, :now2)'
        );
        $stmt->execute([
            ':id' => $id,
            ':portfolio_id' => $portfolioId,
            ':limit_type' => $data['limit_type'],
            ':limit_value' => $data['limit_value'],
            ':limit_unit' => $data['limit_unit'] ?? null,
            ':time_horizon' => $data['time_horizon'] ?? null,
            ':confidence_level' => $data['confidence_level'] ?? null,
            ':status' => $data['status'] ?? 'ACTIVE',
            ':now1' => $now,
            ':effective_until' => $data['effective_until'] ?? null,
            ':now2' => $now,
        ]);
        return $this->getRiskLimitById($id);
    }

    public function updateRiskLimit(string $limitId, array $data): array
    {
        $existing = $this->getRiskLimitById($limitId);
        if ($existing === null) {
            throw new ApiException(404, 'RISK_LIMIT_NOT_FOUND', 'Risk limit was not found');
        }
        $fields = [];
        $params = [':id' => $limitId];
        foreach (
            [
            'limit_value', 'limit_unit', 'time_horizon',
            'confidence_level', 'status', 'effective_until',
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
            'UPDATE risk.risk_limit SET ' . implode(', ', $fields)
            . ' WHERE risk_limit_id = :id'
        );
        $stmt->execute($params);
        return $this->getRiskLimitById($limitId);
    }

    public function removeRiskLimit(string $limitId): array
    {
        $existing = $this->getRiskLimitById($limitId);
        if ($existing === null) {
            throw new ApiException(404, 'RISK_LIMIT_NOT_FOUND', 'Risk limit was not found');
        }
        $stmt = $this->db->prepare(
            'UPDATE risk.risk_limit SET status = :status WHERE risk_limit_id = :id'
        );
        $stmt->execute([':status' => 'REMOVED', ':id' => $limitId]);
        return ['risk_limit_id' => $limitId, 'status' => 'REMOVED'];
    }

    // ─── Risk Assessments ────────────────────────────────────────────────

    public function listRiskAssessments(string $portfolioId, int $page, int $perPage): array
    {
        [$page, $perPage, $offset] = $this->parsePagination(
            ['page' => $page, 'per_page' => $perPage]
        );
        $clause = 'WHERE portfolio_id = :portfolio_id';
        $params = [':portfolio_id' => $portfolioId];
        $total = $this->countRows('risk.risk_assessment', $clause, $params);
        $stmt = $this->db->prepare(
            "SELECT * FROM risk.risk_assessment {$clause} "
            . "ORDER BY as_of DESC LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return $this->paginate($stmt->fetchAll(), $total, $page, $perPage);
    }

    public function triggerAssessment(string $portfolioId, array $data): array
    {
        $this->validateRequired($data, ['assessment_type']);
        $id = $this->uuid();
        $now = $this->now();
        $stmt = $this->db->prepare(
            'INSERT INTO risk.risk_assessment
             (risk_assessment_id, portfolio_id, assessment_type, var_95, var_99,
              expected_shortfall, portfolio_beta, sharpe_ratio, sortino_ratio,
              max_drawdown, volatility, concentration_index, currency, as_of,
              model_version, created_at)
             VALUES
             (:id, :portfolio_id, :assessment_type, :var_95, :var_99,
              :expected_shortfall, :portfolio_beta, :sharpe, :sortino,
              :max_dd, :volatility, :concentration, :currency, :now1,
              :model_version, :now2)'
        );
        $stmt->execute([
            ':id' => $id,
            ':portfolio_id' => $portfolioId,
            ':assessment_type' => $data['assessment_type'],
            ':var_95' => $data['var_95'] ?? null,
            ':var_99' => $data['var_99'] ?? null,
            ':expected_shortfall' => $data['expected_shortfall'] ?? null,
            ':portfolio_beta' => $data['portfolio_beta'] ?? null,
            ':sharpe' => $data['sharpe_ratio'] ?? null,
            ':sortino' => $data['sortino_ratio'] ?? null,
            ':max_dd' => $data['max_drawdown'] ?? null,
            ':volatility' => $data['volatility'] ?? null,
            ':concentration' => $data['concentration_index'] ?? null,
            ':currency' => $data['currency'] ?? null,
            ':now1' => $now,
            ':model_version' => $data['model_version'] ?? null,
            ':now2' => $now,
        ]);
        return $this->getRiskAssessment($id);
    }

    public function getRiskAssessment(string $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM risk.risk_assessment WHERE risk_assessment_id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function getLatestAssessment(string $portfolioId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM risk.risk_assessment
             WHERE portfolio_id = :id
             ORDER BY as_of DESC LIMIT 1'
        );
        $stmt->execute([':id' => $portfolioId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    // ─── Risk Events ─────────────────────────────────────────────────────

    public function listRiskEvents(array $filters, int $page, int $perPage): array
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
        if (isset($filters['severity'])) {
            $where[] = 'severity = :severity';
            $params[':severity'] = $filters['severity'];
        }
        $clause = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
        $total = $this->countRows('risk.risk_event', $clause, $params);
        $stmt = $this->db->prepare(
            "SELECT * FROM risk.risk_event {$clause} "
            . "ORDER BY detected_at DESC LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return $this->paginate($stmt->fetchAll(), $total, $page, $perPage);
    }

    public function listPortfolioRiskEvents(string $portfolioId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM risk.risk_event
             WHERE portfolio_id = :id
             ORDER BY detected_at DESC'
        );
        $stmt->execute([':id' => $portfolioId]);
        return $stmt->fetchAll();
    }

    public function getRiskEvent(string $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM risk.risk_event WHERE risk_event_id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function getActiveRiskEvents(string $portfolioId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM risk.risk_event
             WHERE portfolio_id = :id AND status IN (:s1, :s2)
             ORDER BY detected_at DESC'
        );
        $stmt->execute([
            ':id' => $portfolioId,
            ':s1' => 'OPEN',
            ':s2' => 'ACKNOWLEDGED',
        ]);
        return $stmt->fetchAll();
    }

    public function acknowledgeRiskEvent(string $id): array
    {
        $existing = $this->getRiskEvent($id);
        if ($existing === null) {
            throw new ApiException(404, 'RISK_EVENT_NOT_FOUND', 'Risk event was not found');
        }
        $stmt = $this->db->prepare(
            'UPDATE risk.risk_event SET status = :status WHERE risk_event_id = :id'
        );
        $stmt->execute([':status' => 'ACKNOWLEDGED', ':id' => $id]);
        return $this->getRiskEvent($id);
    }

    public function resolveRiskEvent(string $id, string $resolution): array
    {
        $existing = $this->getRiskEvent($id);
        if ($existing === null) {
            throw new ApiException(404, 'RISK_EVENT_NOT_FOUND', 'Risk event was not found');
        }
        $now = $this->now();
        $stmt = $this->db->prepare(
            'UPDATE risk.risk_event
             SET status = :status, resolved_at = :now, resolution = :resolution
             WHERE risk_event_id = :id'
        );
        $stmt->execute([
            ':status' => 'RESOLVED',
            ':now' => $now,
            ':resolution' => $resolution,
            ':id' => $id,
        ]);
        return $this->getRiskEvent($id);
    }

    // ─── Utility ─────────────────────────────────────────────────────────

    public function checkLimits(string $portfolioId, array $proposedTrade): array
    {
        $limits = $this->listRiskLimits($portfolioId);
        $violations = [];
        foreach ($limits as $limit) {
            if ($limit['status'] !== 'ACTIVE') {
                continue;
            }
            if (
                isset($proposedTrade[$limit['limit_type']])
                && (float) $proposedTrade[$limit['limit_type']] > (float) $limit['limit_value']
            ) {
                $violations[] = [
                    'limit_type' => $limit['limit_type'],
                    'limit_value' => $limit['limit_value'],
                    'proposed_value' => $proposedTrade[$limit['limit_type']],
                    'risk_limit_id' => $limit['risk_limit_id'],
                ];
            }
        }
        return [
            'portfolio_id' => $portfolioId,
            'violations' => $violations,
            'passed' => $violations === [],
        ];
    }

    // ─── Private Helpers ─────────────────────────────────────────────────

    private function getRiskLimitById(string $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM risk.risk_limit WHERE risk_limit_id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    private function assertTolerance(string $tolerance): void
    {
        $valid = ['CONSERVATIVE', 'MODERATE', 'AGGRESSIVE', 'SPECULATIVE'];
        if (!in_array($tolerance, $valid, true)) {
            throw new ApiException(
                422,
                'VALIDATION_ERROR',
                'Invalid risk tolerance. Must be one of: ' . implode(', ', $valid)
            );
        }
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
