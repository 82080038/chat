<?php

declare(strict_types=1);

namespace Platform\Trading;

interface BrokerAdapterInterface
{
    public function authenticate(array $credentials): array;
    public function getAccountBalance(): array;
    public function getPortfolioHoldings(): array;
    public function getRealtimePrice(string $symbol): array;
    public function placeOrder(array $order): array;
    public function cancelOrder(string $orderId): array;
    public function getOrderStatus(string $orderId): array;
    public function getBrokerName(): string;
}
