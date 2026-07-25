<?php

declare(strict_types=1);

namespace Platform\Risk;

use Platform\Core\BaseService;
use Platform\Core\Exceptions\ApiException;

final class RiskService extends BaseService implements RiskServiceInterface
{
    // ─── Risk Profiles ─────────────────────────────────────────────

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

    // ─── Risk Limits ───────────────────────────────────────────────────

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

        $calculated = $this->computeRiskMetrics($portfolioId);

        $var95 = $data['var_95'] ?? $calculated['var_95'];
        $var99 = $data['var_99'] ?? $calculated['var_99'];
        $expectedShortfall = $data['expected_shortfall'] ?? $calculated['expected_shortfall'];
        $portfolioBeta = $data['portfolio_beta'] ?? $calculated['portfolio_beta'];
        $sharpe = $data['sharpe_ratio'] ?? $calculated['sharpe_ratio'];
        $sortino = $data['sortino_ratio'] ?? $calculated['sortino_ratio'];
        $maxDrawdown = $data['max_drawdown'] ?? $calculated['max_drawdown'];
        $volatility = $data['volatility'] ?? $calculated['volatility'];
        $concentration = $data['concentration_index'] ?? $calculated['concentration_index'];

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
            ':var_95' => $var95,
            ':var_99' => $var99,
            ':expected_shortfall' => $expectedShortfall,
            ':portfolio_beta' => $portfolioBeta,
            ':sharpe' => $sharpe,
            ':sortino' => $sortino,
            ':max_dd' => $maxDrawdown,
            ':volatility' => $volatility,
            ':concentration' => $concentration,
            ':currency' => $data['currency'] ?? 'IDR',
            ':now1' => $now,
            ':model_version' => $data['model_version'] ?? 'historical-v1',
            ':now2' => $now,
        ]);
        return $this->getRiskAssessment($id);
    }

    /**
     * Compute risk metrics from actual portfolio positions and historical returns.
     *
     * Uses historical VaR method: collects daily returns for each position,
     * computes weighted portfolio returns, then derives VaR at 95% and 99%
     * confidence levels, Expected Shortfall, volatility, Sharpe, Sortino,
     * max drawdown, concentration index (Herfindahl), and portfolio beta
     * (using market proxy if available).
     *
     * @param string $portfolioId
     * @return array{var_95: ?float, var_99: ?float, expected_shortfall: ?float, portfolio_beta: ?float, sharpe_ratio: ?float, sortino_ratio: ?float, max_drawdown: ?float, volatility: ?float, concentration_index: ?float}
     */
    private function computeRiskMetrics(string $portfolioId): array
    {
        $positions = $this->db->prepare(
            'SELECT instrument_id, quantity, average_cost
             FROM portfolio.position WHERE portfolio_id = :pid AND status = "OPEN"'
        );
        $positions->execute([':pid' => $portfolioId]);
        $rows = $positions->fetchAll();

        if (count($rows) === 0) {
            return [
                'var_95' => null, 'var_99' => null, 'expected_shortfall' => null,
                'portfolio_beta' => null, 'sharpe_ratio' => null, 'sortino_ratio' => null,
                'max_drawdown' => null, 'volatility' => null, 'concentration_index' => null,
            ];
        }

        $weights = [];
        $positionReturns = [];
        $totalValue = 0.0;
        $positionValues = [];

        foreach ($rows as $pos) {
            $instrumentId = $pos['instrument_id'];
            $quantity = (float) $pos['quantity'];
            $avgCost = (float) $pos['average_cost'];
            $value = $quantity * $avgCost;
            $totalValue += $value;
            $positionValues[$instrumentId] = $value;
            $positionReturns[$instrumentId] = $this->getDailyReturns($instrumentId, 120);
        }

        if ($totalValue <= 0) {
            return [
                'var_95' => null, 'var_99' => null, 'expected_shortfall' => null,
                'portfolio_beta' => null, 'sharpe_ratio' => null, 'sortino_ratio' => null,
                'max_drawdown' => null, 'volatility' => null, 'concentration_index' => null,
            ];
        }

        foreach ($positionValues as $instId => $value) {
            $weights[$instId] = $value / $totalValue;
        }

        $minReturns = PHP_INT_MAX;
        foreach ($positionReturns as $returns) {
            $minReturns = min($minReturns, count($returns));
        }

        if ($minReturns < 2) {
            $concentration = $this->herfindahlIndex($weights);
            return [
                'var_95' => null, 'var_99' => null, 'expected_shortfall' => null,
                'portfolio_beta' => null, 'sharpe_ratio' => null, 'sortino_ratio' => null,
                'max_drawdown' => null, 'volatility' => null,
                'concentration_index' => round($concentration, 4),
            ];
        }

        $portfolioReturns = [];
        for ($i = 0; $i < $minReturns; $i++) {
            $weightedReturn = 0.0;
            foreach ($weights as $instId => $w) {
                $returns = $positionReturns[$instId];
                $idx = count($returns) - $minReturns + $i;
                $weightedReturn += $w * $returns[$idx];
            }
            $portfolioReturns[] = $weightedReturn;
        }

        sort($portfolioReturns);
        $n = count($portfolioReturns);

        $var95Idx = (int) floor(0.05 * $n);
        $var99Idx = (int) floor(0.01 * $n);
        $var95Idx = min($var95Idx, $n - 1);
        $var99Idx = min($var99Idx, $n - 1);

        $var95 = $portfolioReturns[$var95Idx];
        $var99 = $portfolioReturns[$var99Idx];

        $tailReturns = [];
        for ($i = 0; $i <= $var95Idx; $i++) {
            $tailReturns[] = $portfolioReturns[$i];
        }
        $expectedShortfall = count($tailReturns) > 0
            ? array_sum($tailReturns) / count($tailReturns)
            : $var95;

        $meanReturn = array_sum($portfolioReturns) / $n;
        $variance = 0.0;
        foreach ($portfolioReturns as $r) {
            $variance += pow($r - $meanReturn, 2);
        }
        $stdDev = sqrt($variance / $n);
        $volatility = $stdDev * sqrt(252);

        $sharpe = $stdDev > 0
            ? ($meanReturn / $stdDev) * sqrt(252)
            : 0.0;

        $downsideReturns = array_filter($portfolioReturns, fn($r) => $r < 0);
        $downsideDev = 0.0;
        if (count($downsideReturns) > 0) {
            $dsVar = 0.0;
            foreach ($downsideReturns as $r) {
                $dsVar += $r * $r;
            }
            $downsideDev = sqrt($dsVar / $n);
        }
        $sortino = $downsideDev > 0
            ? ($meanReturn / $downsideDev) * sqrt(252)
            : ($meanReturn > 0 ? 999.99 : 0.0);

        $cumulative = 1.0;
        $peak = 1.0;
        $maxDrawdown = 0.0;
        foreach ($portfolioReturns as $r) {
            $cumulative *= (1 + $r);
            $peak = max($peak, $cumulative);
            if ($peak > 0) {
                $dd = ($peak - $cumulative) / $peak;
                $maxDrawdown = max($maxDrawdown, $dd);
            }
        }
        $maxDrawdown *= 100;

        $concentration = $this->herfindahlIndex($weights);

        $portfolioBeta = $this->calculatePortfolioBeta($weights, $positionReturns, $minReturns);

        return [
            'var_95' => round($var95 * 100, 4),
            'var_99' => round($var99 * 100, 4),
            'expected_shortfall' => round($expectedShortfall * 100, 4),
            'portfolio_beta' => $portfolioBeta !== null ? round($portfolioBeta, 4) : null,
            'sharpe_ratio' => round($sharpe, 4),
            'sortino_ratio' => round($sortino, 4),
            'max_drawdown' => round($maxDrawdown, 4),
            'volatility' => round($volatility * 100, 4),
            'concentration_index' => round($concentration, 4),
        ];
    }

    /**
     * Calculate Herfindahl-Hirschman Index for concentration.
     *
     * @param array<string, float> $weights
     * @return float
     */
    private function herfindahlIndex(array $weights): float
    {
        $sum = 0.0;
        foreach ($weights as $w) {
            $sum += $w * $w;
        }
        return $sum;
    }

    /**
     * Calculate portfolio beta as weighted average of individual asset betas.
     * Asset beta is computed as covariance(asset, equal-weight portfolio) / variance(portfolio).
     *
     * @param array<string, float> $weights
     * @param array<string, array<int, float>> $positionReturns
     * @param int $minReturns
     * @return ?float
     */
    private function calculatePortfolioBeta(
        array $weights,
        array $positionReturns,
        int $minReturns
    ): ?float {
        $portfolioReturns = [];
        for ($i = 0; $i < $minReturns; $i++) {
            $wr = 0.0;
            foreach ($weights as $instId => $w) {
                $returns = $positionReturns[$instId];
                $idx = count($returns) - $minReturns + $i;
                $wr += $w * $returns[$idx];
            }
            $portfolioReturns[] = $wr;
        }

        $marketMean = array_sum($portfolioReturns) / $minReturns;
        $marketVar = 0.0;
        foreach ($portfolioReturns as $r) {
            $marketVar += pow($r - $marketMean, 2);
        }
        $marketVar /= $minReturns;

        if ($marketVar == 0) {
            return null;
        }

        $weightedBeta = 0.0;
        foreach ($weights as $instId => $w) {
            $returns = $positionReturns[$instId];
            $assetSlice = [];
            for ($i = 0; $i < $minReturns; $i++) {
                $idx = count($returns) - $minReturns + $i;
                $assetSlice[] = $returns[$idx];
            }
            $assetMean = array_sum($assetSlice) / $minReturns;
            $cov = 0.0;
            for ($i = 0; $i < $minReturns; $i++) {
                $cov += ($assetSlice[$i] - $assetMean) * ($portfolioReturns[$i] - $marketMean);
            }
            $cov /= $minReturns;
            $weightedBeta += $w * ($cov / $marketVar);
        }

        return $weightedBeta;
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

        $portfolioExposure = $this->getPortfolioExposure($portfolioId);
        $orderValue = (float) ($proposedTrade['order_value']
            ?? ($proposedTrade['quantity'] ?? 0) * ($proposedTrade['limit_price'] ?? 0));
        $instrumentId = $proposedTrade['instrument_id'] ?? null;

        foreach ($limits as $limit) {
            if ($limit['status'] !== 'ACTIVE') {
                continue;
            }

            $limitType = $limit['limit_type'];
            $limitValue = (float) $limit['limit_value'];

            switch ($limitType) {
                case 'MAX_SINGLE_POSITION':
                    if ($instrumentId !== null) {
                        $currentExposure = $portfolioExposure['by_instrument'][$instrumentId] ?? 0.0;
                        $newExposure = $currentExposure + $orderValue;
                        $pctOfPortfolio = $portfolioExposure['total'] > 0
                            ? ($newExposure / $portfolioExposure['total']) * 100 : 0.0;
                        if ($pctOfPortfolio > $limitValue) {
                            $violations[] = [
                                'limit_type' => $limitType,
                                'limit_value' => $limitValue,
                                'proposed_value' => round($pctOfPortfolio, 2),
                                'current_exposure_pct' => round(
                                    $portfolioExposure['total'] > 0
                                        ? ($currentExposure / $portfolioExposure['total']) * 100
                                        : 0.0, 2
                                ),
                                'risk_limit_id' => $limit['risk_limit_id'],
                            ];
                        }
                    }
                    break;

                case 'MAX_PORTFOLIO_EXPOSURE':
                    $newTotal = $portfolioExposure['total'] + $orderValue;
                    if ($newTotal > $limitValue) {
                        $violations[] = [
                            'limit_type' => $limitType,
                            'limit_value' => $limitValue,
                            'proposed_value' => round($newTotal, 2),
                            'current_exposure' => round($portfolioExposure['total'], 2),
                            'risk_limit_id' => $limit['risk_limit_id'],
                        ];
                    }
                    break;

                case 'MAX_ORDER_VALUE':
                    if ($orderValue > $limitValue) {
                        $violations[] = [
                            'limit_type' => $limitType,
                            'limit_value' => $limitValue,
                            'proposed_value' => round($orderValue, 2),
                            'risk_limit_id' => $limit['risk_limit_id'],
                        ];
                    }
                    break;

                case 'MAX_SECTOR_EXPOSURE':
                    if ($instrumentId !== null) {
                        $sector = $this->getInstrumentSector($instrumentId);
                        if ($sector !== null) {
                            $currentSectorExposure
                                = $portfolioExposure['by_sector'][$sector] ?? 0.0;
                            $newSectorExposure = $currentSectorExposure + $orderValue;
                            $pctOfPortfolio = $portfolioExposure['total'] > 0
                                ? ($newSectorExposure / $portfolioExposure['total']) * 100 : 0.0;
                            if ($pctOfPortfolio > $limitValue) {
                                $violations[] = [
                                    'limit_type' => $limitType,
                                    'limit_value' => $limitValue,
                                    'proposed_value' => round($pctOfPortfolio, 2),
                                    'sector' => $sector,
                                    'risk_limit_id' => $limit['risk_limit_id'],
                                ];
                            }
                        }
                    }
                    break;

                default:
                    if (
                        isset($proposedTrade[$limitType])
                        && (float) $proposedTrade[$limitType] > $limitValue
                    ) {
                        $violations[] = [
                            'limit_type' => $limitType,
                            'limit_value' => $limitValue,
                            'proposed_value' => $proposedTrade[$limitType],
                            'risk_limit_id' => $limit['risk_limit_id'],
                        ];
                    }
                    break;
            }
        }
        return [
            'portfolio_id' => $portfolioId,
            'violations' => $violations,
            'passed' => $violations === [],
        ];
    }

    /**
     * Get portfolio exposure broken down by instrument and sector.
     *
     * @param string $portfolioId
     * @return array{total: float, by_instrument: array<string, float>, by_sector: array<string, float>}
     */
    private function getPortfolioExposure(string $portfolioId): array
    {
        $stmt = $this->db->prepare(
            'SELECT p.instrument_id, p.quantity, p.average_cost, i.sector
             FROM portfolio.position p
             LEFT JOIN market.instrument i ON p.instrument_id = i.instrument_id
             WHERE p.portfolio_id = :pid AND p.status = "OPEN"'
        );
        $stmt->execute([':pid' => $portfolioId]);
        $rows = $stmt->fetchAll();

        $total = 0.0;
        $byInstrument = [];
        $bySector = [];

        foreach ($rows as $row) {
            $value = (float) $row['quantity'] * (float) $row['average_cost'];
            $instId = $row['instrument_id'];
            $sector = $row['sector'] ?? 'UNKNOWN';

            $total += $value;
            $byInstrument[$instId] = ($byInstrument[$instId] ?? 0) + $value;
            $bySector[$sector] = ($bySector[$sector] ?? 0) + $value;
        }

        return [
            'total' => $total,
            'by_instrument' => $byInstrument,
            'by_sector' => $bySector,
        ];
    }

    private function getInstrumentSector(string $instrumentId): ?string
    {
        $stmt = $this->db->prepare(
            'SELECT sector FROM market.instrument WHERE instrument_id = :id'
        );
        $stmt->execute([':id' => $instrumentId]);
        $row = $stmt->fetch();
        return $row === false ? null : ($row['sector'] ?? null);
    }

    // ─── Stop Loss & Correlation ─────────────────────────────────────────

    /**
     * Calculate stop loss price for a position.
     *
     * @param string $instrumentId
     * @param string $side BUY or SELL
     * @param float $entryPrice
     * @param string $method PERCENTAGE, ATR, or SUPPORT
     * @param float $param Percentage (e.g. 2.0), ATR multiplier (e.g. 2.0), or support level
     * @return array{stop_loss_price: float, method: string, risk_amount: float, risk_percent: float}
     */
    public function calculateStopLoss(
        string $instrumentId,
        string $side,
        float $entryPrice,
        string $method = 'PERCENTAGE',
        float $param = 2.0
    ): array {
        $stopPrice = 0.0;

        if ($method === 'PERCENTAGE') {
            $offset = $entryPrice * ($param / 100);
            $stopPrice = $side === 'BUY' ? $entryPrice - $offset : $entryPrice + $offset;
        } elseif ($method === 'ATR') {
            $atr = $this->calculateATR($instrumentId, 14);
            if ($atr === null) {
                $offset = $entryPrice * ($param / 100);
            } else {
                $offset = $atr * $param;
            }
            $stopPrice = $side === 'BUY' ? $entryPrice - $offset : $entryPrice + $offset;
        } elseif ($method === 'SUPPORT') {
            $stopPrice = $param;
            if ($side === 'BUY' && $stopPrice >= $entryPrice) {
                $stopPrice = $entryPrice * 0.98;
            }
            if ($side === 'SELL' && $stopPrice <= $entryPrice) {
                $stopPrice = $entryPrice * 1.02;
            }
        } else {
            throw new ApiException(
                422,
                'VALIDATION_ERROR',
                'Invalid stop loss method. Must be one of: PERCENTAGE, ATR, SUPPORT'
            );
        }

        $riskAmount = abs($entryPrice - $stopPrice);
        $riskPercent = $entryPrice > 0 ? ($riskAmount / $entryPrice) * 100 : 0;

        return [
            'stop_loss_price' => round($stopPrice, 4),
            'method' => $method,
            'entry_price' => $entryPrice,
            'side' => $side,
            'risk_amount' => round($riskAmount, 4),
            'risk_percent' => round($riskPercent, 2),
        ];
    }

    /**
     * Calculate correlation matrix for portfolio positions.
     * Uses daily returns from OHLCV data.
     *
     * @param string $portfolioId
     * @return array{instruments: array<string>, matrix: array<array<float>>}
     */
    public function calculateCorrelationMatrix(string $portfolioId): array
    {
        $positions = $this->db->prepare(
            'SELECT instrument_id FROM portfolio.position WHERE portfolio_id = :pid AND status = "OPEN"'
        );
        $positions->execute([':pid' => $portfolioId]);
        $instrumentIds = array_column($positions->fetchAll(), 'instrument_id');

        if (count($instrumentIds) < 2) {
            return ['instruments' => $instrumentIds, 'matrix' => []];
        }

        $returns = [];
        foreach ($instrumentIds as $instId) {
            $returns[$instId] = $this->getDailyReturns($instId, 60);
        }

        $n = count($instrumentIds);
        $matrix = [];
        for ($i = 0; $i < $n; $i++) {
            $matrix[$i] = [];
            for ($j = 0; $j < $n; $j++) {
                if ($i === $j) {
                    $matrix[$i][$j] = 1.0;
                } else {
                    $matrix[$i][$j] = $this->pearsonCorrelation(
                        $returns[$instrumentIds[$i]],
                        $returns[$instrumentIds[$j]]
                    );
                }
            }
        }

        return [
            'instruments' => $instrumentIds,
            'matrix' => $matrix,
        ];
    }

    // ─── Private Helpers ─────────────────────────────────────────────────

    private function calculateATR(string $instrumentId, int $period = 14): ?float
    {
        $stmt = $this->db->prepare(
            'SELECT high, low, close FROM data_ingestion.ohlcv_daily
             WHERE instrument_id = :id ORDER BY trade_date DESC LIMIT :limit'
        );
        $stmt->bindValue(':id', $instrumentId);
        $stmt->bindValue(':limit', $period + 1, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = array_reverse($stmt->fetchAll());

        if (count($rows) < 2) {
            return null;
        }

        $trueRanges = [];
        for ($i = 1; $i < count($rows); $i++) {
            $high = (float) $rows[$i]['high'];
            $low = (float) $rows[$i]['low'];
            $prevClose = (float) $rows[$i - 1]['close'];
            $tr = max(
                $high - $low,
                abs($high - $prevClose),
                abs($low - $prevClose)
            );
            $trueRanges[] = $tr;
        }

        return array_sum($trueRanges) / count($trueRanges);
    }

    private function getDailyReturns(string $instrumentId, int $days = 60): array
    {
        $stmt = $this->db->prepare(
            'SELECT close FROM data_ingestion.ohlcv_daily
             WHERE instrument_id = :id ORDER BY trade_date DESC LIMIT :limit'
        );
        $stmt->bindValue(':id', $instrumentId);
        $stmt->bindValue(':limit', $days, \PDO::PARAM_INT);
        $stmt->execute();
        $closes = array_reverse(array_map(fn($r) => (float) $r['close'], $stmt->fetchAll()));

        $returns = [];
        for ($i = 1; $i < count($closes); $i++) {
            if ($closes[$i - 1] > 0) {
                $returns[] = ($closes[$i] - $closes[$i - 1]) / $closes[$i - 1];
            }
        }
        return $returns;
    }

    private function pearsonCorrelation(array $x, array $y): float
    {
        $n = min(count($x), count($y));
        if ($n < 2) {
            return 0.0;
        }
        $x = array_slice($x, -$n);
        $y = array_slice($y, -$n);

        $meanX = array_sum($x) / $n;
        $meanY = array_sum($y) / $n;

        $numerator = 0.0;
        $sumSqX = 0.0;
        $sumSqY = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $dx = $x[$i] - $meanX;
            $dy = $y[$i] - $meanY;
            $numerator += $dx * $dy;
            $sumSqX += $dx * $dx;
            $sumSqY += $dy * $dy;
        }

        $denominator = sqrt($sumSqX * $sumSqY);
        if ($denominator == 0) {
            return 0.0;
        }

        return round($numerator / $denominator, 4);
    }

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

    // ─── Liquidity Risk & Gap Risk ──────────────────────────────────────

    /**
     * Assess liquidity risk for a portfolio.
     * Evaluates how quickly positions can be liquidated without significant price impact.
     */
    public function assessLiquidityRisk(string $portfolioId): array
    {
        $positions = $this->db->prepare(
            'SELECT p.instrument_id, p.quantity, p.average_cost
             FROM portfolio.position p
             WHERE p.portfolio_id = :pid AND p.status = "OPEN"'
        );
        $positions->execute([':pid' => $portfolioId]);
        $rows = $positions->fetchAll();

        $positionRisks = [];
        $totalLiquidationDays = 0;
        $totalValue = 0;
        $highRiskPositions = 0;

        foreach ($rows as $pos) {
            $instrumentId = $pos['instrument_id'];
            $quantity = (float) $pos['quantity'];
            $avgCost = (float) $pos['average_cost'];
            $positionValue = $quantity * $avgCost;
            $totalValue += $positionValue;

            // Get average daily volume from OHLCV
            $volStmt = $this->db->prepare(
                'SELECT AVG(volume) AS avg_vol FROM data_ingestion.ohlcv_daily
                 WHERE instrument_id = :iid AND trade_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)'
            );
            $volStmt->execute([':iid' => $instrumentId]);
            $avgVol = (float) ($volStmt->fetchColumn() ?: 0);

            // Participation rate: assume max 10% of daily volume
            $maxDailySell = $avgVol * 0.10;
            $liquidationDays = $maxDailySell > 0 ? ceil($quantity / $maxDailySell) : 999;
            $totalLiquidationDays += $liquidationDays;

            // Liquidity risk score: higher days = higher risk
            $riskScore = min(100, $liquidationDays * 10);
            $riskLevel = $riskScore >= 70 ? 'HIGH' : ($riskScore >= 40 ? 'MEDIUM' : 'LOW');

            if ($riskLevel === 'HIGH') {
                $highRiskPositions++;
            }

            $positionRisks[] = [
                'instrument_id' => $instrumentId,
                'quantity' => $quantity,
                'position_value' => round($positionValue, 2),
                'avg_daily_volume' => round($avgVol, 0),
                'max_daily_sellable' => round($maxDailySell, 0),
                'liquidation_days' => (int) $liquidationDays,
                'liquidity_risk_score' => round($riskScore, 1),
                'risk_level' => $riskLevel,
            ];
        }

        $portfolioRiskScore = $totalValue > 0
            ? min(100, ($totalLiquidationDays / max(1, count($rows))) * 10)
            : 0;

        return [
            'portfolio_id' => $portfolioId,
            'total_positions' => count($rows),
            'total_value' => round($totalValue, 2),
            'high_risk_positions' => $highRiskPositions,
            'avg_liquidation_days' => count($rows) > 0 ? round($totalLiquidationDays / count($rows), 1) : 0,
            'portfolio_liquidity_risk_score' => round($portfolioRiskScore, 1),
            'portfolio_risk_level' => $portfolioRiskScore >= 70
                ? 'HIGH'
                : ($portfolioRiskScore >= 40 ? 'MEDIUM' : 'LOW'),
            'positions' => $positionRisks,
        ];
    }

    /**
     * Assess gap risk for a portfolio.
     * Evaluates overnight/weekend gap risk based on ATR and position size.
     */
    public function assessGapRisk(string $portfolioId): array
    {
        $positions = $this->db->prepare(
            'SELECT p.instrument_id, p.quantity, p.average_cost
             FROM portfolio.position p
             WHERE p.portfolio_id = :pid AND p.status = "OPEN"'
        );
        $positions->execute([':pid' => $portfolioId]);
        $rows = $positions->fetchAll();

        $positionGaps = [];
        $totalGapRisk = 0;
        $totalValue = 0;

        foreach ($rows as $pos) {
            $instrumentId = $pos['instrument_id'];
            $quantity = (float) $pos['quantity'];
            $avgCost = (float) $pos['average_cost'];
            $positionValue = $quantity * $avgCost;
            $totalValue += $positionValue;

            // Get ATR from OHLCV (proxy: average of high-low range over 14 days)
            $atrStmt = $this->db->prepare(
                'SELECT AVG(high - low) AS atr FROM (
                    SELECT high, low FROM data_ingestion.ohlcv_daily
                    WHERE instrument_id = :iid
                    ORDER BY trade_date DESC LIMIT 14
                ) AS sub'
            );
            $atrStmt->execute([':iid' => $instrumentId]);
            $atr = (float) ($atrStmt->fetchColumn() ?: 0);

            // Gap risk = potential overnight move as % of position value
            $gapPct = $avgCost > 0 ? ($atr / $avgCost) * 100 : 0;
            $gapValue = $positionValue * ($gapPct / 100);
            $totalGapRisk += $gapValue;

            $riskLevel = $gapPct >= 5 ? 'HIGH' : ($gapPct >= 2.5 ? 'MEDIUM' : 'LOW');

            $positionGaps[] = [
                'instrument_id' => $instrumentId,
                'quantity' => $quantity,
                'position_value' => round($positionValue, 2),
                'atr_14' => round($atr, 4),
                'gap_risk_pct' => round($gapPct, 2),
                'gap_risk_value' => round($gapValue, 2),
                'risk_level' => $riskLevel,
            ];
        }

        $portfolioGapPct = $totalValue > 0 ? ($totalGapRisk / $totalValue) * 100 : 0;

        return [
            'portfolio_id' => $portfolioId,
            'total_positions' => count($rows),
            'total_value' => round($totalValue, 2),
            'total_gap_risk_value' => round($totalGapRisk, 2),
            'portfolio_gap_risk_pct' => round($portfolioGapPct, 2),
            'portfolio_risk_level' => $portfolioGapPct >= 5 ? 'HIGH' : ($portfolioGapPct >= 2.5 ? 'MEDIUM' : 'LOW'),
            'positions' => $positionGaps,
        ];
    }
}
