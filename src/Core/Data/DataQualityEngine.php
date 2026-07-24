<?php

declare(strict_types=1);

namespace Platform\Core\Data;

use PDO;
use Platform\Core\Database\MySqlConnection;
use Platform\Core\Database\TimescaleDbService;

/**
 * Data Quality Engine — validates ingested data and assigns quality scores.
 *
 * Blueprint: Data quality engine as a core component.
 * Quality scores: 0.0 (worst) to 1.0 (best).
 * Trust levels derived from quality score: <0.5 UNVERIFIED, 0.5-0.8 VALIDATED, >0.8 TRUSTED.
 */
final class DataQualityEngine
{
    private static ?DataQualityEngine $instance = null;
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
     * Run quality checks on OHLCV data for an instrument.
     *
     * @return array{quality_score: float, trust_level: string, checks: array, total_issues: int}
     */
    public function assessOhlcvQuality(string $instrumentId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM data_ingestion.ohlcv_daily
             WHERE instrument_id = :id ORDER BY trade_date ASC'
        );
        $stmt->execute([':id' => $instrumentId]);
        $rows = $stmt->fetchAll();

        if (count($rows) === 0) {
            return [
                'quality_score' => 0.0,
                'trust_level' => 'UNVERIFIED',
                'checks' => [['check' => 'data_exists', 'status' => 'FAIL', 'detail' => 'No data']],
                'total_issues' => 1,
            ];
        }

        $checks = [];
        $totalIssues = 0;
        $totalChecks = 0;

        // Check 1: OHLC consistency
        $ohlcViolations = 0;
        foreach ($rows as $r) {
            $open = (float) $r['open'];
            $high = (float) $r['high'];
            $low = (float) $r['low'];
            $close = (float) $r['close'];
            if ($high < $low || $high < $open || $high < $close || $low > $open || $low > $close) {
                $ohlcViolations++;
            }
        }
        $totalChecks++;
        $ohlcScore = 1.0 - ($ohlcViolations / count($rows));
        $checks[] = [
            'check' => 'ohlc_consistency',
            'status' => $ohlcViolations === 0 ? 'PASS' : 'FAIL',
            'score' => round($ohlcScore, 4),
            'violations' => $ohlcViolations,
        ];
        $totalIssues += $ohlcViolations;

        // Check 2: Zero/negative prices
        $zeroPrices = 0;
        foreach ($rows as $r) {
            $open = (float) $r['open'];
            $high = (float) $r['high'];
            $low = (float) $r['low'];
            $close = (float) $r['close'];
            if ($open <= 0 || $high <= 0 || $low <= 0 || $close <= 0) {
                $zeroPrices++;
            }
        }
        $totalChecks++;
        $priceScore = 1.0 - ($zeroPrices / count($rows));
        $checks[] = [
            'check' => 'positive_prices',
            'status' => $zeroPrices === 0 ? 'PASS' : 'FAIL',
            'score' => round($priceScore, 4),
            'violations' => $zeroPrices,
        ];
        $totalIssues += $zeroPrices;

        // Check 3: Data completeness (gap detection)
        $dates = array_map(fn($r) => $r['trade_date'], $rows);
        $missingDates = 0;
        for ($i = 1; $i < count($dates); $i++) {
            $diff = (strtotime($dates[$i]) - strtotime($dates[$i - 1])) / 86400;
            if ($diff > 3) {
                for ($d = strtotime($dates[$i - 1]) + 86400; $d < strtotime($dates[$i]); $d += 86400) {
                    if (date('N', $d) <= 5) {
                        $missingDates++;
                    }
                }
            }
        }
        $totalChecks++;
        $completenessScore = $missingDates === 0 ? 1.0 : max(0, 1.0 - ($missingDates / count($dates)));
        $checks[] = [
            'check' => 'data_completeness',
            'status' => $missingDates === 0 ? 'PASS' : 'WARN',
            'score' => round($completenessScore, 4),
            'missing_dates' => $missingDates,
        ];
        $totalIssues += $missingDates;

        // Check 4: Duplicate dates
        $duplicates = count($dates) - count(array_unique($dates));
        $totalChecks++;
        $checks[] = [
            'check' => 'no_duplicates',
            'status' => $duplicates === 0 ? 'PASS' : 'FAIL',
            'score' => $duplicates === 0 ? 1.0 : 0.0,
            'duplicates' => $duplicates,
        ];
        $totalIssues += $duplicates;

        // Check 5: Volume sanity
        $zeroVolumeDays = count(array_filter($rows, fn($r) => (int) $r['volume'] === 0));
        $totalChecks++;
        $volumeScore = $zeroVolumeDays === 0 ? 1.0 : max(0, 1.0 - ($zeroVolumeDays / count($rows) * 0.5));
        $checks[] = [
            'check' => 'volume_sanity',
            'status' => $zeroVolumeDays === 0 ? 'PASS' : 'WARN',
            'score' => round($volumeScore, 4),
            'zero_volume_days' => $zeroVolumeDays,
        ];

        // Check 6: Source provenance
        $noSource = count(array_filter($rows, fn($r) => empty($r['source'])));
        $totalChecks++;
        $provenanceScore = $noSource === 0 ? 1.0 : max(0, 1.0 - ($noSource / count($rows)));
        $checks[] = [
            'check' => 'provenance',
            'status' => $noSource === 0 ? 'PASS' : 'FAIL',
            'score' => round($provenanceScore, 4),
            'missing_source' => $noSource,
        ];

        // Overall quality score (weighted average)
        $weights = [
            'ohlc_consistency' => 0.25,
            'positive_prices' => 0.20,
            'data_completeness' => 0.20,
            'no_duplicates' => 0.15,
            'volume_sanity' => 0.10,
            'provenance' => 0.10,
        ];
        $weightedSum = 0.0;
        foreach ($checks as $check) {
            $weight = $weights[$check['check']] ?? 0;
            $weightedSum += ($check['score'] ?? 0) * $weight;
        }
        $qualityScore = round($weightedSum, 4);
        $trustLevel = $this->scoreToTrustLevel($qualityScore);

        return [
            'quality_score' => $qualityScore,
            'trust_level' => $trustLevel,
            'checks' => $checks,
            'total_issues' => $totalIssues,
            'total_records' => count($rows),
            'date_range' => [
                'from' => $dates[0],
                'to' => $dates[count($dates) - 1],
            ],
        ];
    }

    /**
     * Convert quality score to trust level.
     */
    public function scoreToTrustLevel(float $score): string
    {
        if ($score > 0.8) {
            return 'TRUSTED';
        }
        if ($score >= 0.5) {
            return 'VALIDATED';
        }
        return 'UNVERIFIED';
    }

    /**
     * Update quality score on feature_value records.
     */
    public function updateFeatureQualityScore(string $featureValueId, float $score): void
    {
        $stmt = $this->db->prepare(
            'UPDATE analytics.feature_value SET quality_score = :score WHERE feature_value_id = :id'
        );
        $stmt->execute([':score' => $score, ':id' => $featureValueId]);
    }
}
