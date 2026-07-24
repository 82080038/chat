<?php

declare(strict_types=1);

namespace Platform\Analytics;

interface AnalyticsServiceInterface
{
    // Feature Definitions
    public function listFeatures(array $filters, int $page, int $perPage): array;

    public function createFeature(array $data): array;

    public function getFeature(string $id): ?array;

    public function updateFeature(string $id, array $data): array;

    // Feature Values
    public function getFeatureValues(string $featureId, array $filters, int $page, int $perPage): array;

    public function ingestFeatureValues(string $featureId, array $data): array;

    // Signals
    public function listSignals(array $filters, int $page, int $perPage): array;

    public function createSignal(array $data): array;

    public function getSignal(string $id): ?array;

    public function getActiveSignals(string $instrumentId): array;

    public function invalidateSignal(string $id, string $reason): array;

    // Forecasts
    public function listForecasts(array $filters, int $page, int $perPage): array;

    public function createForecast(array $data): array;

    public function getForecast(string $id): ?array;

    public function getLatestForecast(string $instrumentId, string $targetVariable): ?array;

    // Recommendations
    public function listRecommendations(array $filters, int $page, int $perPage): array;

    public function createRecommendation(array $data): array;

    public function getRecommendation(string $id): ?array;

    public function getLatestRecommendation(string $instrumentId): ?array;

    // Scores
    public function listScores(array $filters, int $page, int $perPage): array;

    public function createScore(array $data): array;

    public function getScore(string $id): ?array;

    public function getInstrumentScores(string $instrumentId, ?string $scoreType): array;

    // Model Registry
    public function listModels(array $filters, int $page, int $perPage): array;

    public function createModel(array $data): array;

    public function getModel(string $id): ?array;

    public function updateModel(string $id, array $data): array;

    // Backtests
    public function listBacktests(array $filters, int $page, int $perPage): array;

    public function createBacktest(array $data): array;

    public function getBacktest(string $id): ?array;

    public function getBacktestStatus(string $id): ?array;

    public function updateBacktestResults(string $id, array $data): array;
}
