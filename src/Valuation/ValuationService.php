<?php

declare(strict_types=1);

namespace Platform\Valuation;

use Platform\Core\BaseService;
use Platform\Core\Exceptions\ApiException;

final class ValuationService extends BaseService implements ValuationServiceInterface
{
    public function createValuation(array $data): array
    {
        $required = ['instrument_id', 'valuation_type', 'fair_value', 'as_of_date'];
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

        $id = $this->uuid();
        $now = $this->now();

        $sql = 'INSERT INTO valuation.valuation_result
            (valuation_id, instrument_id, valuation_type, discount_rate,
             terminal_growth, projected_fcf, peer_group, peer_metric, peer_value,
             fair_value, margin_of_safety, confidence_score, assumptions,
             as_of_date, currency, created_at)
            VALUES
            (:id, :instrument_id, :valuation_type, :discount_rate,
             :terminal_growth, :projected_fcf, :peer_group, :peer_metric, :peer_value,
             :fair_value, :margin_of_safety, :confidence_score, :assumptions,
             :as_of_date, :currency, :created_at)';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id' => $id,
            ':instrument_id' => $data['instrument_id'],
            ':valuation_type' => $data['valuation_type'],
            ':discount_rate' => $data['discount_rate'] ?? null,
            ':terminal_growth' => $data['terminal_growth'] ?? null,
            ':projected_fcf' => isset($data['projected_fcf'])
                ? json_encode($data['projected_fcf']) : null,
            ':peer_group' => $data['peer_group'] ?? null,
            ':peer_metric' => $data['peer_metric'] ?? null,
            ':peer_value' => $data['peer_value'] ?? null,
            ':fair_value' => $data['fair_value'],
            ':margin_of_safety' => $data['margin_of_safety'] ?? null,
            ':confidence_score' => $data['confidence_score'] ?? null,
            ':assumptions' => isset($data['assumptions'])
                ? json_encode($data['assumptions']) : null,
            ':as_of_date' => $data['as_of_date'],
            ':currency' => $data['currency'] ?? 'IDR',
            ':created_at' => $now,
        ]);

        return $this->getValuation($id);
    }

    public function getValuation(string $id): ?array
    {
        $sql = 'SELECT * FROM valuation.valuation_result WHERE valuation_id = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        return $this->decodeJsonFields($row);
    }

    public function listValuations(array $filters, int $page, int $perPage): array
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
        if (isset($filters['valuation_type'])) {
            $where[] = 'valuation_type = :valuation_type';
            $params[':valuation_type'] = $filters['valuation_type'];
        }

        $whereClause = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

        $countSql = "SELECT COUNT(*) FROM valuation.valuation_result {$whereClause}";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT * FROM valuation.valuation_result {$whereClause} "
            . "ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $items = array_map(
            [$this, 'decodeJsonFields'],
            $stmt->fetchAll()
        );

        return $this->paginate($items, $total, $page, $perPage);
    }

    public function getInstrumentValuations(string $instrumentId): array
    {
        $sql = 'SELECT * FROM valuation.valuation_result '
            . 'WHERE instrument_id = :instrument_id ORDER BY as_of_date DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':instrument_id' => $instrumentId]);
        return array_map(
            [$this, 'decodeJsonFields'],
            $stmt->fetchAll()
        );
    }

    public function calculateDcf(array $data): array
    {
        $required = ['projected_fcf', 'discount_rate', 'terminal_growth'];
        foreach ($required as $field) {
            if (!array_key_exists($field, $data)) {
                throw new ApiException(
                    422,
                    'VALIDATION_ERROR',
                    "Field {$field} is required for DCF",
                    [$field => 'Required']
                );
            }
        }

        $fcfs = $data['projected_fcf'];
        $discountRate = (float) $data['discount_rate'];
        $terminalGrowth = (float) $data['terminal_growth'];

        if ($discountRate <= 0 || $discountRate >= 1) {
            throw new ApiException(
                422,
                'INVALID_DISCOUNT_RATE',
                'Discount rate must be between 0 and 1 (exclusive)'
            );
        }
        if ($terminalGrowth < 0 || $terminalGrowth >= $discountRate) {
            throw new ApiException(
                422,
                'INVALID_TERMINAL_GROWTH',
                'Terminal growth must be >= 0 and < discount rate'
            );
        }

        $npv = 0.0;
        $pvDetails = [];
        foreach ($fcfs as $year => $fcf) {
            $period = (int) $year + 1;
            $pv = (float) $fcf / pow(1 + $discountRate, $period);
            $npv += $pv;
            $pvDetails[] = [
                'year' => $period,
                'fcf' => (float) $fcf,
                'pv' => round($pv, 4),
            ];
        }

        $lastFcf = (float) end($fcfs);
        $terminalValue = $lastFcf * (1 + $terminalGrowth)
            / ($discountRate - $terminalGrowth);
        $pvTerminal = $terminalValue
            / pow(1 + $discountRate, count($fcfs));
        $npv += $pvTerminal;

        return [
            'method' => 'DCF',
            'fair_value' => round($npv, 4),
            'pv_of_fcf' => round($npv - $pvTerminal, 4),
            'pv_of_terminal' => round($pvTerminal, 4),
            'terminal_value' => round($terminalValue, 4),
            'pv_details' => $pvDetails,
            'discount_rate' => $discountRate,
            'terminal_growth' => $terminalGrowth,
        ];
    }

    public function calculateRelative(array $data): array
    {
        $required = ['peer_values', 'metric_name', 'current_metric_value'];
        foreach ($required as $field) {
            if (!array_key_exists($field, $data)) {
                throw new ApiException(
                    422,
                    'VALIDATION_ERROR',
                    "Field {$field} is required for relative valuation",
                    [$field => 'Required']
                );
            }
        }

        $peerValues = array_map('floatval', $data['peer_values']);
        $currentMetric = (float) $data['current_metric_value'];

        if ($currentMetric <= 0) {
            throw new ApiException(
                422,
                'INVALID_METRIC',
                'Current metric value must be positive'
            );
        }

        $avgPeer = count($peerValues) > 0
            ? array_sum($peerValues) / count($peerValues)
            : 0.0;
        $medianPeer = $this->median($peerValues);

        $impliedPriceAvg = $avgPeer * $currentMetric;
        $impliedPriceMedian = $medianPeer * $currentMetric;

        return [
            'method' => 'RELATIVE',
            'metric_name' => $data['metric_name'],
            'peer_count' => count($peerValues),
            'peer_avg' => round($avgPeer, 4),
            'peer_median' => round($medianPeer, 4),
            'current_metric_value' => $currentMetric,
            'implied_price_avg' => round($impliedPriceAvg, 4),
            'implied_price_median' => round($impliedPriceMedian, 4),
            'fair_value' => round($impliedPriceMedian, 4),
        ];
    }

    public function calculateFairValue(array $data): array
    {
        $required = ['dcf_result', 'relative_result', 'weights'];
        foreach ($required as $field) {
            if (!array_key_exists($field, $data)) {
                throw new ApiException(
                    422,
                    'VALIDATION_ERROR',
                    "Field {$field} is required for fair value blend",
                    [$field => 'Required']
                );
            }
        }

        $dcfValue = (float) $data['dcf_result']['fair_value'];
        $relativeValue = (float) $data['relative_result']['fair_value'];
        $weights = $data['weights'];

        $dcfWeight = (float) ($weights['dcf'] ?? 0.5);
        $relativeWeight = (float) ($weights['relative'] ?? 0.5);

        $totalWeight = $dcfWeight + $relativeWeight;
        if ($totalWeight <= 0) {
            throw new ApiException(
                422,
                'INVALID_WEIGHTS',
                'Weight sum must be positive'
            );
        }

        $normalizedDcfWeight = $dcfWeight / $totalWeight;
        $normalizedRelativeWeight = $relativeWeight / $totalWeight;

        $blended = $dcfValue * $normalizedDcfWeight
            + $relativeValue * $normalizedRelativeWeight;

        return [
            'method' => 'BLENDED',
            'dcf_value' => $dcfValue,
            'relative_value' => $relativeValue,
            'dcf_weight' => round($normalizedDcfWeight, 4),
            'relative_weight' => round($normalizedRelativeWeight, 4),
            'fair_value' => round($blended, 4),
        ];
    }

    private function median(array $values): float
    {
        sort($values);
        $count = count($values);
        if ($count === 0) {
            return 0.0;
        }
        if ($count % 2 === 0) {
            return ($values[$count / 2 - 1] + $values[$count / 2]) / 2;
        }
        return $values[(int) floor($count / 2)];
    }

    private function decodeJsonFields(array $row): array
    {
        if (isset($row['projected_fcf']) && is_string($row['projected_fcf'])) {
            $row['projected_fcf'] = json_decode($row['projected_fcf'], true);
        }
        if (isset($row['assumptions']) && is_string($row['assumptions'])) {
            $row['assumptions'] = json_decode($row['assumptions'], true);
        }
        return $row;
    }
}
