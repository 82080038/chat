<?php

declare(strict_types=1);

namespace Platform\Trading;

interface TradingServiceInterface
{
    // Brokers
    public function listBrokers(array $filters, int $page, int $perPage): array;

    public function createBroker(array $data): array;

    public function getBroker(string $id): ?array;

    public function updateBroker(string $id, array $data): array;

    // Decisions
    public function listDecisions(array $filters, int $page, int $perPage): array;

    public function createDecision(array $data): array;

    public function getDecision(string $id): ?array;

    public function approveDecision(string $id): array;

    public function rejectDecision(string $id, string $reason): array;

    public function overrideDecision(string $id, string $reason): array;

    // Order Intents
    public function listOrderIntents(array $filters, int $page, int $perPage): array;

    public function createOrderIntent(array $data): array;

    public function getOrderIntent(string $id): ?array;

    public function approveOrderIntent(string $id): array;

    public function rejectOrderIntent(string $id, string $reason): array;

    // Orders
    public function listOrders(array $filters, int $page, int $perPage): array;

    public function submitOrder(array $data): array;

    public function getOrder(string $id): ?array;

    public function cancelOrder(string $id, string $reason): array;

    public function getOrderExecutions(string $orderId): array;

    // Executions
    public function listExecutions(array $filters, int $page, int $perPage): array;

    public function getExecution(string $id): ?array;

    public function recordExecution(array $data): array;
}
