<?php

declare(strict_types=1);

namespace Platform\Valuation;

interface ValuationServiceInterface
{
    public function createValuation(array $data): array;
    public function getValuation(string $id): ?array;
    public function listValuations(array $filters, int $page, int $perPage): array;
    public function getInstrumentValuations(string $instrumentId): array;
    public function calculateDcf(array $data): array;
    public function calculateRelative(array $data): array;
    public function calculateFairValue(array $data): array;
}
