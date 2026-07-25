<?php

declare(strict_types=1);

namespace Platform\AIEngine;

use Platform\Core\BaseService;
use Platform\Core\Exceptions\ApiException;

final class AIEngineService extends BaseService implements AIEngineServiceInterface
{
    private const POSITIVE_WORDS = [
        'surge' => 3, 'jump' => 3, 'soar' => 3, 'rally' => 2, 'rise' => 2,
        'gain' => 2, 'profit' => 2, 'growth' => 2, 'upgrade' => 3,
        'bullish' => 3, 'outperform' => 3, 'beat' => 2, 'strong' => 2,
        'record' => 2, 'high' => 1, 'breakthrough' => 3, 'surge' => 3,
        'dividend' => 2, 'buyback' => 2, 'expand' => 2, 'exceed' => 2,
        'robust' => 2, 'solid' => 2, 'impressive' => 3, 'remarkable' => 3,
        'naik' => 2, 'untung' => 2, 'tumbuh' => 2, 'positif' => 2, 'tinggi' => 1,
        'menguat' => 2, 'melompat' => 3, 'meroket' => 3, 'mencatat' => 1,
    ];

    private const NEGATIVE_WORDS = [
        'drop' => 3, 'fall' => 2, 'decline' => 2, 'loss' => 3, 'downgrade' => 3,
        'bearish' => 3, 'underperform' => 3, 'miss' => 2, 'weak' => 2,
        'low' => 1, 'crash' => 3, 'plunge' => 3, 'sell' => 2, 'dump' => 3,
        'collapse' => 3, 'slump' => 2, 'tumble' => 3, 'warning' => 2,
        'fraud' => 3, 'investigation' => 2, 'lawsuit' => 2, 'default' => 3,
        'bankrupt' => 3, 'restructure' => 2, 'halt' => 2, 'suspend' => 2,
        'turun' => 2, 'rugi' => 3, 'lemah' => 2, 'negatif' => 2, 'rendah' => 1,
        'melemah' => 2, 'anjlok' => 3, 'terjun' => 3, 'merosot' => 3,
    ];

    private const NEGATION_WORDS = [
        'not', 'no', 'never', 'without', 'despite', 'against',
        'tidak', 'bukan', 'tanpa', 'jangan',
    ];

    private const INTENSIFIER_WORDS = [
        'very' => 1.5, 'extremely' => 2.0, 'significantly' => 1.5,
        'substantially' => 1.5, 'highly' => 1.5, 'remarkably' => 2.0,
        'sangat' => 1.5, 'sangatlah' => 2.0, 'amat' => 1.5,
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
        $words = explode(' ', $text);
        $wordCount = count($words);
        $entities = $this->extractEntities($data['text']);

        $positiveScore = 0.0;
        $negativeScore = 0.0;
        $positive = 0;
        $negative = 0;

        foreach ($words as $i => $word) {
            $word = trim($word, ".,!?;:\"'()[]{}");
            if ($word === '') {
                continue;
            }

            $weight = 1.0;
            $negated = false;

            if ($i > 0) {
                $prevWord = trim($words[$i - 1], ".,!?;:\"'()[]{}");
                if (in_array($prevWord, self::NEGATION_WORDS, true)) {
                    $negated = true;
                }
                if (isset(self::INTENSIFIER_WORDS[$prevWord])) {
                    $weight *= self::INTENSIFIER_WORDS[$prevWord];
                }
            }

            if ($i > 1) {
                $prevPrev = trim($words[$i - 2], ".,!?;:\"'()[]{}");
                if (in_array($prevPrev, self::NEGATION_WORDS, true)) {
                    $negated = true;
                }
            }

            if (isset(self::POSITIVE_WORDS[$word])) {
                $value = self::POSITIVE_WORDS[$word] * $weight;
                if ($negated) {
                    $negativeScore += $value;
                    $negative++;
                } else {
                    $positiveScore += $value;
                    $positive++;
                }
            } elseif (isset(self::NEGATIVE_WORDS[$word])) {
                $value = self::NEGATIVE_WORDS[$word] * $weight;
                if ($negated) {
                    $positiveScore += $value;
                    $positive++;
                } else {
                    $negativeScore += $value;
                    $negative++;
                }
            }
        }

        $totalScore = $positiveScore + $negativeScore;
        if ($totalScore <= 0.0) {
            $score = 0.0;
            $label = 'NEUTRAL';
        } else {
            $score = round((($positiveScore - $negativeScore) / $totalScore) * 100, 2);
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
                'positive_score' => round($positiveScore, 2),
                'negative_score' => round($negativeScore, 2),
                'word_count' => $wordCount,
                'method' => 'weighted_keyword',
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

        $tickerPattern = '/\b([A-Z]{4,5})\b/';
        if (preg_match_all($tickerPattern, $text, $matches)) {
            $entities['tickers'] = array_values(array_unique($matches[1]));
        }

        $moneyPattern = '/(?:Rp\.?|USD|IDR|\$)\s?([\d,]+(?:\.\d+)?(?:\s?(?:miliar|triliun|billion|million|B|M))?)/i';
        if (preg_match_all($moneyPattern, $text, $moneyMatches)) {
            $entities['monetary'] = $moneyMatches[0];
        }

        $percentPattern = '/([\d]+(?:\.\d+)?)\s?%/';
        if (preg_match_all($percentPattern, $text, $percentMatches)) {
            $entities['percentages'] = $percentMatches[0];
        }

        $datePattern = '/\b(\d{1,2}[\/-]\d{1,2}[\/-]\d{2,4}|\d{4}[\/-]\d{1,2}[\/-]\d{1,2})\b/';
        if (preg_match_all($datePattern, $text, $dateMatches)) {
            $entities['dates'] = $dateMatches[0];
        }

        $companyKeywords = ['bank', 'corp', 'group', 'energy', 'mining', 'telecom', 'pharma'];
        $sentences = preg_split('/[.!,]/', $text);
        foreach ($sentences as $sentence) {
            $words = explode(' ', trim($sentence));
            foreach ($words as $i => $word) {
                $cleanWord = trim($word, ".,!?;:\"'()[]{}");
                foreach ($companyKeywords as $kw) {
                    if (stripos($cleanWord, $kw) !== false && strlen($cleanWord) > 3) {
                        $entities['companies'][] = $cleanWord;
                    }
                }
            }
        }
        if (isset($entities['companies'])) {
            $entities['companies'] = array_values(array_unique($entities['companies']));
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
        $n = count($closes);

        $maxHigh = max($highs);
        $minLow = min($lows);
        $range = $maxHigh - $minLow;

        if ($range <= 0) {
            return 'FLAT';
        }

        $sma = $this->computeSMA($closes, min(10, (int) ($n / 2)));
        $firstSma = $sma[0];
        $lastSma = $sma[count($sma) - 1];
        $trend = $firstSma > 0 ? ($lastSma - $firstSma) / $firstSma : 0;

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

        $highVariance = $this->variance($highs);
        $lowVariance = $this->variance($lows);
        $highCV = $firstSma > 0 ? sqrt($highVariance) / $firstSma : 0;
        $lowCV = $firstSma > 0 ? sqrt($lowVariance) / $firstSma : 0;

        if ($topCount >= 2 && abs($trend) < 0.02 && $highCV < 0.02) {
            return 'DOUBLE_TOP';
        }

        if ($bottomCount >= 2 && abs($trend) < 0.02 && $lowCV < 0.02) {
            return 'DOUBLE_BOTTOM';
        }

        if ($trend > 0.03 && $lowCV < 0.03) {
            return 'ASCENDING_TRIANGLE';
        }

        if ($trend < -0.03 && $highCV < 0.03) {
            return 'DESCENDING_TRIANGLE';
        }

        if ($trend > 0.05) {
            return 'UPTREND_CHANNEL';
        }

        if ($trend < -0.05) {
            return 'DOWNTREND_CHANNEL';
        }

        return 'SIDEWAYS_CHANNEL';
    }

    private function computeSMA(array $values, int $period): array
    {
        $n = count($values);
        $result = [];
        $sum = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $sum += $values[$i];
            if ($i >= $period) {
                $sum -= $values[$i - $period];
            }
            if ($i >= $period - 1) {
                $result[] = $sum / $period;
            }
        }
        return $result;
    }

    private function variance(array $values): float
    {
        $count = count($values);
        if ($count === 0) {
            return 0.0;
        }
        $mean = array_sum($values) / $count;
        $sum = 0.0;
        foreach ($values as $v) {
            $sum += pow($v - $mean, 2);
        }
        return $sum / $count;
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
