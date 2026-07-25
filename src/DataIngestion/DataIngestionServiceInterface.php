<?php

declare(strict_types=1);

namespace Platform\DataIngestion;

interface DataIngestionServiceInterface
{
    public function ingestOhlcv(array $data): array;
    public function getOhlcv(string $id): ?array;
    public function listOhlcv(array $filters, int $page, int $perPage): array;
    public function getOhlcvHistory(
        string $instrumentId,
        ?string $fromDate,
        ?string $toDate
    ): array;
    public function getIngestionStatus(): array;

    public function runDataQualityChecks(string $instrumentId): array;

    public function fetchFromExternal(string $provider, string $symbol, ?string $fromDate, ?string $toDate): array;
}
