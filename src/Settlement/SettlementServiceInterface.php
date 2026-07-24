<?php

declare(strict_types=1);

namespace Platform\Settlement;

interface SettlementServiceInterface
{
    // Settlements
    public function listSettlements(array $filters, int $page, int $perPage): array;

    public function getSettlement(string $id): ?array;

    public function getSettlementByExecution(string $executionId): ?array;

    public function getPendingSettlements(string $portfolioId): array;

    public function processSettlement(string $settlementId): array;

    public function createSettlement(array $data): array;

    // Reconciliations
    public function listReconciliations(array $filters, int $page, int $perPage): array;

    public function getReconciliation(string $id): ?array;

    public function listPortfolioReconciliations(string $portfolioId): array;

    public function createReconciliation(array $data): array;

    public function resolveReconciliation(string $id, string $resolution): array;
}
