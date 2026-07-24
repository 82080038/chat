<?php

declare(strict_types=1);

namespace Platform\PaperTrading;

interface PaperTradingServiceInterface
{
    public function createAccount(array $data): array;
    public function getAccount(string $accountId): ?array;
    public function placeOrder(string $accountId, array $data): array;
    public function cancelOrder(string $accountId, string $orderId): array;
    public function listOrders(string $accountId, int $page, int $perPage): array;
    public function getPositions(string $accountId): array;
    public function getSummary(string $accountId): array;
    public function validateSignal(string $signalId, string $accountId): array;
}
