<?php

declare(strict_types=1);

namespace Platform\Core\Analytics;

/**
 * Explainable AI — provides feature importance and decision explanations
 * for AI/ML model outputs.
 *
 * Blueprint: "Explainable AI" — feature importance, decision explanation for recommendations.
 *
 * Uses a model-agnostic approach: tracks which input features contributed
 * most to each prediction/recommendation, and generates human-readable explanations.
 */
final class ExplainableAI
{
    private static ?ExplainableAI $instance = null;

    /** @var array<string, array<string, float>> Feature importance cache per model */
    private array $featureImportance = [];

    private function __construct()
    {
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Register feature importance for a model.
     *
     * @param string $modelId
     * @param array<string, float> $importance Map of feature name to importance score (0-1)
     */
    public function registerFeatureImportance(string $modelId, array $importance): void
    {
        $this->featureImportance[$modelId] = $importance;
    }

    /**
     * Explain a recommendation by listing contributing factors.
     *
     * @param array<string, mixed> $recommendation
     * @param array<string, mixed> $inputFeatures
     * @return array{explanation: string, factors: array, confidence_breakdown: array}
     */
    public function explainRecommendation(array $recommendation, array $inputFeatures = []): array
    {
        $action = $recommendation['action'] ?? 'HOLD';
        $confidence = (float) ($recommendation['confidence'] ?? 0.5);
        $modelId = $recommendation['model_version'] ?? 'default';

        $factors = [];
        $importance = $this->featureImportance[$modelId] ?? $this->defaultImportance();

        foreach ($importance as $feature => $weight) {
            $value = $inputFeatures[$feature] ?? null;
            if ($value !== null) {
                $factors[] = [
                    'feature' => $feature,
                    'value' => $value,
                    'importance' => round($weight, 4),
                    'contribution' => $this->calculateContribution($feature, $value, $action),
                ];
            }
        }

        // Sort by importance descending
        usort($factors, fn($a, $b) => $b['importance'] <=> $a['importance']);

        $explanation = $this->generateExplanation($action, $confidence, $factors);

        return [
            'explanation' => $explanation,
            'factors' => $factors,
            'confidence_breakdown' => [
                'overall' => $confidence,
                'data_quality' => $inputFeatures['data_quality_score'] ?? null,
                'model_confidence' => $confidence,
                'agreement_score' => $inputFeatures['agreement_score'] ?? null,
            ],
            'model_id' => $modelId,
        ];
    }

    /**
     * Explain a signal by listing contributing indicators.
     *
     * @param array<string, mixed> $signal
     * @param array<string, mixed> $indicatorValues
     * @return array{explanation: string, indicators: array}
     */
    public function explainSignal(array $signal, array $indicatorValues = []): array
    {
        $direction = $signal['direction'] ?? 'NEUTRAL';
        $signalType = $signal['signal_type'] ?? 'UNKNOWN';
        $strength = $signal['strength'] ?? 'MODERATE';

        $indicators = [];
        foreach ($indicatorValues as $name => $value) {
            $indicators[] = [
                'indicator' => $name,
                'value' => $value,
                'interpretation' => $this->interpretIndicator($name, $value, $direction),
            ];
        }

        $explanation = sprintf(
            "Signal '%s' indicates %s direction with %s strength. Based on %d indicator(s).",
            $signalType,
            $direction,
            $strength,
            count($indicators)
        );

        return [
            'explanation' => $explanation,
            'indicators' => $indicators,
            'direction' => $direction,
            'strength' => $strength,
        ];
    }

    /**
     * Generate SHAP-like feature contribution scores.
     *
     * @param array<string, mixed> $features
     * @param string $modelId
     * @return array<string, float>
     */
    public function calculateShapValues(array $features, string $modelId = 'default'): array
    {
        $importance = $this->featureImportance[$modelId] ?? $this->defaultImportance();
        $shap = [];

        foreach ($features as $name => $value) {
            $weight = $importance[$name] ?? 0.1;
            $numericValue = is_numeric($value) ? (float) $value : 0.5;
            $shap[$name] = round($weight * ($numericValue - 0.5), 6);
        }

        return $shap;
    }

    /**
     * @return array<string, float>
     */
    private function defaultImportance(): array
    {
        return [
            'rsi' => 0.20,
            'macd' => 0.18,
            'sma' => 0.15,
            'bollinger' => 0.12,
            'volume' => 0.10,
            'momentum' => 0.10,
            'volatility' => 0.08,
            'sentiment' => 0.07,
        ];
    }

    private function calculateContribution(string $feature, mixed $value, string $action): string
    {
        if (!is_numeric($value)) {
            return 'neutral';
        }
        $v = (float) $value;
        $isBuy = in_array($action, ['BUY', 'OVERWEIGHT', 'ACCUMULATE'], true);

        return match ($feature) {
            'rsi' => $v < 30
                ? ($isBuy ? 'strong_positive' : 'neutral')
                : ($v > 70
                    ? ($isBuy ? 'negative' : 'strong_positive')
                    : 'neutral'),
            'macd' => $v > 0 ? ($isBuy ? 'positive' : 'negative') : ($isBuy ? 'negative' : 'positive'),
            'momentum' => $v > 0 ? ($isBuy ? 'positive' : 'negative') : ($isBuy ? 'negative' : 'positive'),
            'volatility' => $v > 0.03 ? 'caution' : 'neutral',
            default => 'neutral',
        };
    }

    private function generateExplanation(string $action, float $confidence, array $factors): string
    {
        $topFactors = array_slice($factors, 0, 3);
        $factorNames = array_map(fn($f) => $f['feature'], $topFactors);

        $actionText = match ($action) {
            'BUY' => 'buy',
            'SELL' => 'sell',
            'HOLD' => 'hold',
            'ABSTAIN' => 'abstain from',
            'REBALANCE' => 'rebalance',
            default => strtolower($action),
        };

        $confidenceText = $confidence > 0.8 ? 'high confidence'
            : ($confidence > 0.6 ? 'moderate confidence' : 'low confidence');

        $factorsText = $factorNames !== []
            ? ' primarily driven by ' . implode(', ', $factorNames)
            : '';

        return sprintf(
            "Recommendation to %s with %s%s.",
            $actionText,
            $confidenceText,
            $factorsText
        );
    }

    private function interpretIndicator(string $name, mixed $value, string $direction): string
    {
        if (!is_numeric($value)) {
            return 'non-numeric value';
        }
        $v = (float) $value;

        return match ($name) {
            'rsi' => $v < 30
                ? 'oversold — bullish signal'
                : ($v > 70 ? 'overbought — bearish signal' : 'neutral range'),
            'macd' => $v > 0 ? 'bullish crossover' : 'bearish crossover',
            'sma' => $v > 0 ? 'above moving average — uptrend' : 'below moving average — downtrend',
            'bollinger' => abs($v) > 2 ? 'outside bands — high deviation' : 'within bands — normal range',
            default => "value: {$v}",
        };
    }
}
