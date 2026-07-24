<?php

declare(strict_types=1);

namespace Platform\Microstructure;

interface MicrostructureServiceInterface
{
    public function captureOrderBook(array $data): array;
    public function getOrderBook(string $id): ?array;
    public function getLatestOrderBook(string $instrumentId): ?array;
    public function listOrderBooks(array $filters, int $page, int $perPage): array;

    public function calculateSpreadAnalysis(string $instrumentId, int $days): array;
    public function calculateMarketImpact(
        string $instrumentId,
        float $orderQuantity,
        string $side
    ): array;
    public function calculateLiquidityProfile(string $instrumentId): array;

    public function listMetrics(array $filters, int $page, int $perPage): array;
    public function getMetrics(string $instrumentId, string $date): ?array;
}
