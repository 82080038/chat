<?php

declare(strict_types=1);

namespace Platform\AIEngine;

use Platform\Core\BaseService;
use Platform\Core\Exceptions\ApiException;

final class AIEngineService extends BaseService implements AIEngineServiceInterface
{
    private const POSITIVE_WORDS = [
        'surge', 'jump', 'rise', 'gain', 'profit', 'growth', 'upgrade',
        'bullish', 'outperform', 'beat', 'strong', 'record', 'high',
        'naik', 'untung', 'tumbuh', 'positif', 'tinggi',
    ];

    private const NEGATIVE_WORDS = [
        'drop', 'fall', 'decline', 'loss', 'downgrade', 'bearish',
        'underperform', 'miss', 'weak', 'low', 'crash', 'plunge',
        'turun', 'rugi', 'lemah', 'negatif', 'rendah',
    ];

    private const PATTERNS = [
        'DOUBLE_TOP', 'DOUBLE_BOTTOM', 'HEAD_SHOULDERS',
        'ASCENDING_TRIANGLE', 'DESCENDING_TRIANGLE', 'CUP_HANDLE',
        'FLAG', 'WEDGE', 'CHANNEL',
    ];

    public function analyzeSentiment(array $data): array
    {
        $required = ['text'];
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

        $text = strtolower($data['text']);
        $positive = 0;
        $negative = 0;
        $entities = $this->extractEntities($data['text']);

        foreach (self::POSITIVE_WORDS as $word) {
            $count = substr_count($text, $word);
            $positive += $count;
        }

        foreach (self::NEGATIVE_WORDS as $word) {
            $count = substr_count($text, $word);
            $negative += $count;
        }

        $total = $positive + $negative;
        if ($total === 0) {
            $score = 0.0;
            $label = 'NEUTRAL';
        } else {
            $score = round((($positive - $negative) / $total) * 100, 2);
            $label = $score > 20 ? 'POSITIVE' : ($score < -20 ? 'NEGATIVE' : 'NEUTRAL');
        }

        $events = $this->extractEvents($text);

        $id = $this->uuid();
        $now = $this->now();

        $sql = 'INSERT INTO ai_engine.ai_analysis
            (analysis_id, analysis_type, instrument_id, source_id, source_type,
             sentiment_score, sentiment_label, entities, events,
             summary, metadata, created_at)
            VALUES
            (:id, :type, :inst, :src_id, :src_type,
             :score, :label, :entities, :events,
             :summary, :meta, :now)';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id' => $id,
            ':type' => 'SENTIMENT',
            ':inst' => $data['instrument_id'] ?? null,
            ':src_id' => $data['source_id'] ?? null,
            ':src_type' => $data['source_type'] ?? 'NEWS',
            ':score' => $score,
            ':label' => $label,
            ':entities' => json_encode($entities),
            ':events' => json_encode($events),
            ':summary' => substr($data['text'], 0, 500),
            ':meta' => json_encode([
                'positive_count' => $positive,
                'negative_count' => $negative,
                'word_count' => str_word_count($text),
            ]),
            ':now' => $now,
        ]);

        return [
            'analysis_id' => $id,
            'analysis_type' => 'SENTIMENT',
            'sentiment_score' => $score,
            'sentiment_label' => $label,
            'entities' => $entities,
            'events' => $events,
            'instrument_id' => $data['instrument_id'] ?? null,
            'source_id' => $data['source_id'] ?? null,
            'source_type' => $data['source_type'] ?? 'NEWS',
            'created_at' => $now,
        ];
    }

    public function recognizePattern(array $data): array
    {
        $required = ['price_data', 'instrument_id'];
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

        $priceData = $data['price_data'];
        if (count($priceData) < 5) {
            throw new ApiException(
                422,
                'INSUFFICIENT_DATA',
                'At least 5 price bars required for pattern recognition'
            );
        }

        $pattern = $this->detectChartPattern($priceData);
        $confidence = $this->calculatePatternConfidence($priceData, $pattern);

        $id = $this->uuid();
        $now = $this->now();

        $sql = 'INSERT INTO ai_engine.ai_analysis
            (analysis_id, analysis_type, instrument_id,
             pattern_type, pattern_confidence,
             summary, metadata, created_at)
            VALUES
            (:id, :type, :inst,
             :pattern, :confidence,
             :summary, :meta, :now)';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id' => $id,
            ':type' => 'PATTERN',
            ':inst' => $data['instrument_id'],
            ':pattern' => $pattern,
            ':confidence' => $confidence,
            ':summary' => "Detected {$pattern} pattern with {$confidence}% confidence",
            ':meta' => json_encode([
                'bars_analyzed' => count($priceData),
                'price_range' => [
                    'high' => max(array_column($priceData, 'high')),
                    'low' => min(array_column($priceData, 'low')),
                ],
            ]),
            ':now' => $now,
        ]);

        return [
            'analysis_id' => $id,
            'analysis_type' => 'PATTERN',
            'instrument_id' => $data['instrument_id'],
            'pattern_type' => $pattern,
            'pattern_confidence' => $confidence,
            'bars_analyzed' => count($priceData),
            'created_at' => $now,
        ];
    }

    public function detectAnomaly(array $data): array
    {
        $required = ['values', 'instrument_id'];
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

        $values = $data['values'];
        if (count($values) < 3) {
            throw new ApiException(
                422,
                'INSUFFICIENT_DATA',
                'At least 3 values required for anomaly detection'
            );
        }

        $mean = array_sum($values) / count($values);
        $variance = 0.0;
        foreach ($values as $v) {
            $variance += pow($v - $mean, 2);
        }
        $stdDev = sqrt($variance / count($values));

        $anomalies = [];
        $maxScore = 0.0;
        $anomalyType = 'NONE';

        if ($stdDev > 0) {
            foreach ($values as $i => $v) {
                $zScore = abs(($v - $mean) / $stdDev);
                if ($zScore > 2.0) {
                    $anomalies[] = [
                        'index' => $i,
                        'value' => $v,
                        'z_score' => round($zScore, 2),
                    ];
                    if ($zScore > $maxScore) {
                        $maxScore = $zScore;
                        $anomalyType = $v > $mean ? 'SPIKE' : 'DROP';
                    }
                }
            }
        }

        $score = round(min($maxScore * 20, 100), 2);

        $id = $this->uuid();
        $now = $this->now();

        $sql = 'INSERT INTO ai_engine.ai_analysis
            (analysis_id, analysis_type, instrument_id,
             anomaly_score, anomaly_type,
             summary, metadata, created_at)
            VALUES
            (:id, :type, :inst,
             :score, :atype,
             :summary, :meta, :now)';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id' => $id,
            ':type' => 'ANOMALY',
            ':inst' => $data['instrument_id'],
            ':score' => $score,
            ':atype' => $anomalyType,
            ':summary' => count($anomalies) > 0
                ? "Detected {$anomalyType} anomaly with score {$score}"
                : 'No anomalies detected',
            ':meta' => json_encode([
                'mean' => round($mean, 4),
                'std_dev' => round($stdDev, 4),
                'anomaly_count' => count($anomalies),
                'anomalies' => $anomalies,
            ]),
            ':now' => $now,
        ]);

        return [
            'analysis_id' => $id,
            'analysis_type' => 'ANOMALY',
            'instrument_id' => $data['instrument_id'],
            'anomaly_score' => $score,
            'anomaly_type' => $anomalyType,
            'anomaly_count' => count($anomalies),
            'anomalies' => $anomalies,
            'mean' => round($mean, 4),
            'std_dev' => round($stdDev, 4),
            'created_at' => $now,
        ];
    }

    public function getAnalysis(string $analysisId): ?array
    {
        $sql = 'SELECT * FROM ai_engine.ai_analysis WHERE analysis_id = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $analysisId]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        foreach (['entities', 'events', 'metadata'] as $jsonField) {
            if (isset($row[$jsonField]) && $row[$jsonField] !== null) {
                $row[$jsonField] = json_decode($row[$jsonField], true);
            }
        }
        return $row;
    }

    public function listAnalyses(array $filters, int $page, int $perPage): array
    {
        [$page, $perPage, $offset] = $this->parsePagination(
            ['page' => $page, 'per_page' => $perPage]
        );

        $where = [];
        $params = [];
        if (isset($filters['analysis_type'])) {
            $where[] = 'analysis_type = :type';
            $params[':type'] = $filters['analysis_type'];
        }
        if (isset($filters['instrument_id'])) {
            $where[] = 'instrument_id = :inst';
            $params[':inst'] = $filters['instrument_id'];
        }

        $clause = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

        $countSql = "SELECT COUNT(*) FROM ai_engine.ai_analysis {$clause}";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT * FROM ai_engine.ai_analysis {$clause} "
            . "ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $this->paginate($stmt->fetchAll(), $total, $page, $perPage);
    }

    public function createModelRun(array $data): array
    {
        $required = ['model_name', 'model_version'];
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

        $sql = 'INSERT INTO ai_engine.ai_model_run
            (run_id, model_name, model_version, input_count,
             output_count, status, started_at)
            VALUES
            (:id, :name, :ver, :input, 0, :status, :now)';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id' => $id,
            ':name' => $data['model_name'],
            ':ver' => $data['model_version'],
            ':input' => $data['input_count'] ?? 0,
            ':status' => 'RUNNING',
            ':now' => $now,
        ]);

        return [
            'run_id' => $id,
            'model_name' => $data['model_name'],
            'model_version' => $data['model_version'],
            'input_count' => $data['input_count'] ?? 0,
            'output_count' => 0,
            'status' => 'RUNNING',
            'started_at' => $now,
        ];
    }

    public function updateModelRun(string $runId, array $data): array
    {
        $sql = 'SELECT * FROM ai_engine.ai_model_run WHERE run_id = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $runId]);
        $run = $stmt->fetch();

        if ($run === false) {
            throw new ApiException(404, 'RUN_NOT_FOUND', 'Model run not found');
        }

        $status = $data['status'] ?? $run['status'];
        $outputCount = $data['output_count'] ?? (int) $run['output_count'];
        $errorMsg = $data['error_message'] ?? null;
        $completedAt = $status === 'COMPLETED' || $status === 'FAILED'
            ? $this->now()
            : null;

        $updateSql = 'UPDATE ai_engine.ai_model_run
            SET status = :status, output_count = :output,
                error_message = :err, completed_at = :completed
            WHERE run_id = :id';

        $updateStmt = $this->db->prepare($updateSql);
        $updateStmt->execute([
            ':status' => $status,
            ':output' => $outputCount,
            ':err' => $errorMsg,
            ':completed' => $completedAt,
            ':id' => $runId,
        ]);

        return [
            'run_id' => $runId,
            'status' => $status,
            'output_count' => $outputCount,
            'error_message' => $errorMsg,
            'completed_at' => $completedAt,
        ];
    }

    private function extractEntities(string $text): array
    {
        $entities = [];
        $tickerPattern = '/\b([A-Z]{4})\b/';
        if (preg_match_all($tickerPattern, $text, $matches)) {
            $entities['tickers'] = $matches[1];
        }

        $companyKeywords = ['bank', 'corp', 'group', 'energy', 'mining'];
        $words = explode(' ', $text);
        foreach ($words as $i => $word) {
            foreach ($companyKeywords as $kw) {
                if (str_contains($word, $kw)) {
                    $entities['companies'][] = $word;
                }
            }
        }

        return $entities;
    }

    private function extractEvents(string $text): array
    {
        $events = [];
        $eventKeywords = [
            'earnings' => 'EARNINGS_REPORT',
            'dividend' => 'DIVIDEND',
            'acquisition' => 'ACQUISITION',
            'merger' => 'MERGER',
            'ipo' => 'IPO',
            'split' => 'STOCK_SPLIT',
            'downgrade' => 'RATING_DOWNGRADE',
            'upgrade' => 'RATING_UPGRADE',
        ];

        foreach ($eventKeywords as $keyword => $eventType) {
            if (str_contains($text, $keyword)) {
                $events[] = $eventType;
            }
        }

        return $events;
    }

    private function detectChartPattern(array $priceData): string
    {
        $highs = array_column($priceData, 'high');
        $lows = array_column($priceData, 'low');
        $closes = array_column($priceData, 'close');

        $maxHigh = max($highs);
        $minLow = min($lows);
        $range = $maxHigh - $minLow;

        if ($range <= 0) {
            return 'FLAT';
        }

        $firstHalf = array_slice($closes, 0, (int) (count($closes) / 2));
        $secondHalf = array_slice($closes, (int) (count($closes) / 2));
        $firstAvg = array_sum($firstHalf) / count($firstHalf);
        $secondAvg = array_sum($secondHalf) / count($secondHalf);

        $trend = ($secondAvg - $firstAvg) / $firstAvg;

        $topCount = 0;
        $topThreshold = $maxHigh * 0.98;
        foreach ($highs as $h) {
            if ($h >= $topThreshold) {
                $topCount++;
            }
        }

        $bottomCount = 0;
        $bottomThreshold = $minLow * 1.02;
        foreach ($lows as $l) {
            if ($l <= $bottomThreshold) {
                $bottomCount++;
            }
        }

        if ($topCount >= 2 && abs($trend) < 0.02) {
            return 'DOUBLE_TOP';
        }

        if ($bottomCount >= 2 && abs($trend) < 0.02) {
            return 'DOUBLE_BOTTOM';
        }

        if ($trend > 0.03) {
            return 'ASCENDING_TRIANGLE';
        }

        if ($trend < -0.03) {
            return 'DESCENDING_TRIANGLE';
        }

        return 'CHANNEL';
    }

    private function calculatePatternConfidence(array $priceData, string $pattern): float
    {
        $baseConfidence = 50.0;
        $barCount = count($priceData);

        if ($barCount >= 20) {
            $baseConfidence += 20;
        } elseif ($barCount >= 10) {
            $baseConfidence += 10;
        }

        if ($pattern === 'DOUBLE_TOP' || $pattern === 'DOUBLE_BOTTOM') {
            $baseConfidence += 15;
        }

        if ($pattern === 'ASCENDING_TRIANGLE' || $pattern === 'DESCENDING_TRIANGLE') {
            $baseConfidence += 10;
        }

        return round(min($baseConfidence, 95), 2);
    }
}
