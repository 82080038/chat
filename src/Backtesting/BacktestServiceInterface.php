<?php

declare(strict_types=1);

namespace Platform\Backtesting;

interface BacktestServiceInterface
{
    public function createRun(array $data): array;
    public function getRun(string $runId): ?array;
    public function listRuns(array $filters, int $page, int $perPage): array;
    public function executeRun(string $runId, array $priceData): array;
    public function getRunTrades(string $runId): array;
    public function getRunMetrics(string $runId): ?array;
    public function calculateMetrics(array $trades, float $initialCapital): array;
}
