<?php

declare(strict_types=1);

namespace Platform\Trading\Adapters;

use Platform\Trading\BrokerAdapterInterface;
use Platform\Core\Exceptions\ApiException;

final class MockBrokerAdapter implements BrokerAdapterInterface
{
    private ?string $token = null;
    private array $orders = [];

    public function __construct(
        private readonly string $brokerName = 'MOCK_BROKER'
    ) {
    }

    public function authenticate(array $credentials): array
    {
        $required = ['api_key', 'api_secret'];
        foreach ($required as $field) {
            if (!array_key_exists($field, $credentials)) {
                throw new ApiException(
                    422,
                    'VALIDATION_ERROR',
                    "Field {$field} is required for broker authentication",
                    [$field => 'Required']
                );
            }
        }

        $this->token = 'mock_token_' . bin2hex(random_bytes(8));

        return [
            'access_token' => $this->token,
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'broker' => $this->brokerName,
        ];
    }

    public function getAccountBalance(): array
    {
        $this->ensureAuthenticated();

        return [
            'broker' => $this->brokerName,
            'currency' => 'IDR',
            'cash_balance' => 500000000.00,
            'available_balance' => 450000000.00,
            'blocked_balance' => 50000000.00,
            'as_of' => date('Y-m-d H:i:s'),
        ];
    }

    public function getPortfolioHoldings(): array
    {
        $this->ensureAuthenticated();

        return [
            'broker' => $this->brokerName,
            'holdings' => [
                [
                    'symbol' => 'BBCA',
                    'quantity' => 1000,
                    'avg_price' => 7500.00,
                    'current_price' => 8200.00,
                    'market_value' => 8200000.00,
                    'pnl' => 700000.00,
                    'pnl_pct' => 9.33,
                ],
                [
                    'symbol' => 'TLKM',
                    'quantity' => 5000,
                    'avg_price' => 3200.00,
                    'current_price' => 3100.00,
                    'market_value' => 15500000.00,
                    'pnl' => -500000.00,
                    'pnl_pct' => -3.13,
                ],
            ],
            'as_of' => date('Y-m-d H:i:s'),
        ];
    }

    public function getRealtimePrice(string $symbol): array
    {
        $this->ensureAuthenticated();

        $prices = [
            'BBCA' => ['price' => 8200, 'change' => 50, 'change_pct' => 0.61],
            'TLKM' => ['price' => 3100, 'change' => -20, 'change_pct' => -0.64],
            'ASII' => ['price' => 5150, 'change' => 15, 'change_pct' => 0.29],
        ];

        $data = $prices[strtoupper($symbol)] ?? [
            'price' => 10000,
            'change' => 0,
            'change_pct' => 0.0,
        ];

        return [
            'broker' => $this->brokerName,
            'symbol' => strtoupper($symbol),
            'price' => $data['price'],
            'change' => $data['change'],
            'change_pct' => $data['change_pct'],
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }

    public function placeOrder(array $order): array
    {
        $this->ensureAuthenticated();

        $required = ['symbol', 'side', 'quantity', 'order_type'];
        foreach ($required as $field) {
            if (!array_key_exists($field, $order)) {
                throw new ApiException(
                    422,
                    'VALIDATION_ERROR',
                    "Field {$field} is required for order placement",
                    [$field => 'Required']
                );
            }
        }

        $validSides = ['BUY', 'SELL'];
        if (!in_array($order['side'], $validSides, true)) {
            throw new ApiException(
                422,
                'INVALID_SIDE',
                'side must be BUY or SELL'
            );
        }

        $validTypes = ['MARKET', 'LIMIT'];
        if (!in_array($order['order_type'], $validTypes, true)) {
            throw new ApiException(
                422,
                'INVALID_ORDER_TYPE',
                'order_type must be MARKET or LIMIT'
            );
        }

        $orderId = 'ord_' . bin2hex(random_bytes(8));
        $this->orders[$orderId] = [
            'order_id' => $orderId,
            'broker' => $this->brokerName,
            'symbol' => $order['symbol'],
            'side' => $order['side'],
            'quantity' => (int) $order['quantity'],
            'order_type' => $order['order_type'],
            'price' => $order['price'] ?? null,
            'status' => 'OPEN',
            'created_at' => date('Y-m-d H:i:s'),
        ];

        return $this->orders[$orderId];
    }

    public function cancelOrder(string $orderId): array
    {
        $this->ensureAuthenticated();

        if (!isset($this->orders[$orderId])) {
            throw new ApiException(
                404,
                'ORDER_NOT_FOUND',
                "Order {$orderId} not found"
            );
        }

        $this->orders[$orderId]['status'] = 'CANCELLED';
        return $this->orders[$orderId];
    }

    public function getOrderStatus(string $orderId): array
    {
        $this->ensureAuthenticated();

        if (!isset($this->orders[$orderId])) {
            throw new ApiException(
                404,
                'ORDER_NOT_FOUND',
                "Order {$orderId} not found"
            );
        }

        return $this->orders[$orderId];
    }

    public function getBrokerName(): string
    {
        return $this->brokerName;
    }

    private function ensureAuthenticated(): void
    {
        if ($this->token === null) {
            throw new ApiException(
                401,
                'NOT_AUTHENTICATED',
                'Broker adapter is not authenticated. Call authenticate() first.'
            );
        }
    }
}
