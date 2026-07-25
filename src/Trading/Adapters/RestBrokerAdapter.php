<?php

declare(strict_types=1);

namespace Platform\Trading\Adapters;

use Platform\Trading\BrokerAdapterInterface;
use Platform\Core\Exceptions\ApiException;

/**
 * Generic REST API broker adapter.
 *
 * Connects to any broker that exposes a REST API with:
 * - POST /auth/token or similar for authentication
 * - GET /balance for account balance
 * - GET /holdings or /positions for portfolio
 * - GET /quote/{symbol} for real-time price
 * - POST /orders for order placement
 * - DELETE /orders/{id} for cancellation
 * - GET /orders/{id} for status
 *
 * The base URL and endpoint paths are configurable per broker
 * via the broker record's api_config column (JSON).
 */
final class RestBrokerAdapter implements BrokerAdapterInterface
{
    private ?string $token = null;
    private ?string $tokenExpiresAt = null;
    private array $config;

    public function __construct(
        private readonly string $brokerName = 'REST_BROKER',
        array $config = []
    ) {
        $this->config = array_merge([
            'base_url' => '',
            'auth_endpoint' => '/auth/token',
            'balance_endpoint' => '/balance',
            'holdings_endpoint' => '/holdings',
            'quote_endpoint' => '/quote/{symbol}',
            'orders_endpoint' => '/orders',
            'order_endpoint' => '/orders/{id}',
            'auth_method' => 'POST_BODY',
            'auth_token_field' => 'api_key',
            'auth_secret_field' => 'api_secret',
            'auth_grant_type' => null,
            'timeout_seconds' => 15,
            'response_balance_field' => 'data',
            'response_holdings_field' => 'data',
            'response_price_field' => 'data',
            'response_order_field' => 'data',
        ], $config);
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

        $url = $this->config['base_url'] . $this->config['auth_endpoint'];
        $body = [
            $this->config['auth_token_field'] => $credentials['api_key'],
            $this->config['auth_secret_field'] => $credentials['api_secret'],
        ];
        if ($this->config['auth_grant_type'] !== null) {
            $body['grant_type'] = $this->config['auth_grant_type'];
        }

        $response = $this->httpRequest('POST', $url, $body);

        $this->token = $response['access_token'] ?? null;
        if ($this->token === null) {
            throw new ApiException(
                502,
                'BROKER_AUTH_FAILED',
                'Broker did not return an access_token'
            );
        }

        $expiresIn = (int) ($response['expires_in'] ?? 3600);
        $this->tokenExpiresAt = date('Y-m-d H:i:s', time() + $expiresIn);

        return [
            'access_token' => $this->token,
            'token_type' => $response['token_type'] ?? 'Bearer',
            'expires_in' => $expiresIn,
            'broker' => $this->brokerName,
        ];
    }

    public function getAccountBalance(): array
    {
        $this->ensureAuthenticated();
        $url = $this->config['base_url'] . $this->config['balance_endpoint'];
        $response = $this->httpRequest('GET', $url, token: $this->token);
        $data = $response[$this->config['response_balance_field']] ?? $response;

        return [
            'broker' => $this->brokerName,
            'currency' => $data['currency'] ?? 'IDR',
            'cash_balance' => (float) ($data['cash_balance'] ?? 0),
            'available_balance' => (float) ($data['available_balance'] ?? $data['cash_balance'] ?? 0),
            'blocked_balance' => (float) ($data['blocked_balance'] ?? 0),
            'as_of' => $data['as_of'] ?? date('Y-m-d H:i:s'),
        ];
    }

    public function getPortfolioHoldings(): array
    {
        $this->ensureAuthenticated();
        $url = $this->config['base_url'] . $this->config['holdings_endpoint'];
        $response = $this->httpRequest('GET', $url, token: $this->token);
        $data = $response[$this->config['response_holdings_field']] ?? $response;
        $holdings = $data['holdings'] ?? $data;

        $normalized = [];
        foreach ($holdings as $h) {
            $normalized[] = [
                'symbol' => $h['symbol'] ?? $h['ticker'] ?? '',
                'quantity' => (int) ($h['quantity'] ?? $h['shares'] ?? 0),
                'avg_price' => (float) ($h['avg_price'] ?? $h['average_cost'] ?? 0),
                'current_price' => (float) ($h['current_price'] ?? $h['last_price'] ?? 0),
                'market_value' => (float) ($h['market_value'] ?? 0),
                'pnl' => (float) ($h['pnl'] ?? $h['unrealized_pnl'] ?? 0),
                'pnl_pct' => (float) ($h['pnl_pct'] ?? $h['unrealized_pnl_pct'] ?? 0),
            ];
        }

        return [
            'broker' => $this->brokerName,
            'holdings' => $normalized,
            'as_of' => date('Y-m-d H:i:s'),
        ];
    }

    public function getRealtimePrice(string $symbol): array
    {
        $this->ensureAuthenticated();
        $endpoint = str_replace('{symbol}', urlencode($symbol), $this->config['quote_endpoint']);
        $url = $this->config['base_url'] . $endpoint;
        $response = $this->httpRequest('GET', $url, token: $this->token);
        $data = $response[$this->config['response_price_field']] ?? $response;

        return [
            'broker' => $this->brokerName,
            'symbol' => strtoupper($symbol),
            'price' => (float) ($data['price'] ?? $data['last_price'] ?? 0),
            'change' => (float) ($data['change'] ?? 0),
            'change_pct' => (float) ($data['change_pct'] ?? $data['percent_change'] ?? 0),
            'timestamp' => $data['timestamp'] ?? date('Y-m-d H:i:s'),
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
            throw new ApiException(422, 'INVALID_SIDE', 'side must be BUY or SELL');
        }

        $validTypes = ['MARKET', 'LIMIT'];
        if (!in_array($order['order_type'], $validTypes, true)) {
            throw new ApiException(422, 'INVALID_ORDER_TYPE', 'order_type must be MARKET or LIMIT');
        }

        $url = $this->config['base_url'] . $this->config['orders_endpoint'];
        $response = $this->httpRequest('POST', $url, $order, $this->token);
        $data = $response[$this->config['response_order_field']] ?? $response;

        return [
            'order_id' => $data['order_id'] ?? $data['id'] ?? '',
            'broker' => $this->brokerName,
            'symbol' => $order['symbol'],
            'side' => $order['side'],
            'quantity' => (int) $order['quantity'],
            'order_type' => $order['order_type'],
            'price' => $order['price'] ?? null,
            'status' => strtoupper($data['status'] ?? 'OPEN'),
            'created_at' => $data['created_at'] ?? date('Y-m-d H:i:s'),
        ];
    }

    public function modifyOrder(string $orderId, array $modifications): array
    {
        $this->ensureAuthenticated();
        $endpoint = str_replace('{id}', urlencode($orderId), $this->config['order_endpoint']);
        $url = $this->config['base_url'] . $endpoint;
        $response = $this->httpRequest('PATCH', $url, $modifications, $this->token);
        $data = $response[$this->config['response_order_field']] ?? $response;

        return [
            'order_id' => $orderId,
            'broker' => $this->brokerName,
            'status' => strtoupper($data['status'] ?? 'MODIFIED'),
            'modified_at' => date('Y-m-d H:i:s'),
        ];
    }

    public function cancelOrder(string $orderId): array
    {
        $this->ensureAuthenticated();
        $endpoint = str_replace('{id}', urlencode($orderId), $this->config['order_endpoint']);
        $url = $this->config['base_url'] . $endpoint;
        $response = $this->httpRequest('DELETE', $url, token: $this->token);
        $data = $response[$this->config['response_order_field']] ?? $response;

        return [
            'order_id' => $orderId,
            'broker' => $this->brokerName,
            'status' => 'CANCELLED',
            'cancelled_at' => date('Y-m-d H:i:s'),
        ];
    }

    public function getOrderStatus(string $orderId): array
    {
        $this->ensureAuthenticated();
        $endpoint = str_replace('{id}', urlencode($orderId), $this->config['order_endpoint']);
        $url = $this->config['base_url'] . $endpoint;
        $response = $this->httpRequest('GET', $url, token: $this->token);
        $data = $response[$this->config['response_order_field']] ?? $response;

        return [
            'order_id' => $orderId,
            'broker' => $this->brokerName,
            'status' => strtoupper($data['status'] ?? 'UNKNOWN'),
            'quantity' => (int) ($data['quantity'] ?? 0),
            'filled_quantity' => (int) ($data['filled_quantity'] ?? 0),
            'avg_fill_price' => isset($data['avg_fill_price']) ? (float) $data['avg_fill_price'] : null,
            'created_at' => $data['created_at'] ?? null,
        ];
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

    private function httpRequest(
        string $method,
        string $url,
        ?array $body = null,
        ?string $token = null
    ): array {
        $ch = curl_init();
        $headers = ['Content-Type: application/json', 'Accept: application/json'];

        if ($token !== null) {
            $headers[] = "Authorization: Bearer {$token}";
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->config['timeout_seconds'],
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        if ($body !== null && in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new ApiException(
                502,
                'BROKER_CONNECTION_ERROR',
                "Failed to connect to broker API: {$error}"
            );
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            throw new ApiException(
                502,
                'BROKER_INVALID_RESPONSE',
                "Broker returned invalid JSON response (HTTP {$httpCode})"
            );
        }

        if ($httpCode >= 400) {
            $code = $data['error']['code'] ?? 'BROKER_API_ERROR';
            $message = $data['error']['message'] ?? "Broker API returned HTTP {$httpCode}";
            throw new ApiException($httpCode, $code, $message);
        }

        return $data;
    }
}
