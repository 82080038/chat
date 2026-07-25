<?php

declare(strict_types=1);

namespace Platform\Trading;

use Platform\Core\BaseService;
use Platform\Core\Exceptions\ApiException;
use Platform\Trading\Adapters\MockBrokerAdapter;
use Platform\Trading\Adapters\RestBrokerAdapter;

final class BrokerAdapterService extends BaseService
{
    private array $adapterCache = [];

    public function getAdapter(string $brokerId): BrokerAdapterInterface
    {
        if (isset($this->adapterCache[$brokerId])) {
            return $this->adapterCache[$brokerId];
        }

        $broker = $this->getBroker($brokerId);
        if ($broker === null) {
            throw new ApiException(
                404,
                'BROKER_NOT_FOUND',
                "Broker {$brokerId} not found"
            );
        }

        if ($broker['status'] !== 'ACTIVE') {
            throw new ApiException(
                403,
                'BROKER_INACTIVE',
                "Broker {$brokerId} is not active"
            );
        }

        $adapter = $this->createAdapter($broker);
        $this->adapterCache[$brokerId] = $adapter;
        return $adapter;
    }

    public function authenticateBroker(string $brokerId, array $credentials): array
    {
        $adapter = $this->getAdapter($brokerId);
        $result = $adapter->authenticate($credentials);

        $this->saveCredentials($brokerId, $credentials, $result);

        return $result;
    }

    public function getBalance(string $brokerId): array
    {
        return $this->getAdapter($brokerId)->getAccountBalance();
    }

    public function getHoldings(string $brokerId): array
    {
        return $this->getAdapter($brokerId)->getPortfolioHoldings();
    }

    public function getPrice(string $brokerId, string $symbol): array
    {
        return $this->getAdapter($brokerId)->getRealtimePrice($symbol);
    }

    public function placeOrder(string $brokerId, array $order): array
    {
        return $this->getAdapter($brokerId)->placeOrder($order);
    }

    public function cancelOrder(string $brokerId, string $orderId): array
    {
        return $this->getAdapter($brokerId)->cancelOrder($orderId);
    }

    public function getOrderStatus(string $brokerId, string $orderId): array
    {
        return $this->getAdapter($brokerId)->getOrderStatus($orderId);
    }

    public function listApiLogs(array $filters, int $page, int $perPage): array
    {
        [$page, $perPage, $offset] = $this->parsePagination(
            ['page' => $page, 'per_page' => $perPage]
        );

        $where = [];
        $params = [];
        if (isset($filters['broker_id'])) {
            $where[] = 'broker_id = :broker_id';
            $params[':broker_id'] = $filters['broker_id'];
        }

        $clause = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

        $countSql = "SELECT COUNT(*) FROM trading.broker_api_log {$clause}";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT * FROM trading.broker_api_log {$clause} "
            . "ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $this->paginate($stmt->fetchAll(), $total, $page, $perPage);
    }

    private function getBroker(string $brokerId): ?array
    {
        $sql = 'SELECT * FROM trading.broker WHERE broker_id = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $brokerId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    private function createAdapter(array $broker): BrokerAdapterInterface
    {
        $apiType = strtoupper($broker['api_type'] ?? 'NONE');
        $name = $broker['name'] ?? 'UNKNOWN';

        if ($apiType === 'NONE' || str_contains(strtoupper($name), 'MOCK')) {
            return new MockBrokerAdapter($name);
        }

        if ($apiType === 'REST') {
            $config = [];
            if (!empty($broker['api_config'])) {
                $decoded = json_decode($broker['api_config'], true);
                if (is_array($decoded)) {
                    $config = $decoded;
                }
            }
            if (empty($config['base_url'])) {
                throw new ApiException(
                    422,
                    'BROKER_CONFIG_ERROR',
                    "Broker '{$name}' has api_type=REST but no base_url configured in api_config"
                );
            }
            return new RestBrokerAdapter($name, $config);
        }

        if ($apiType === 'FIX') {
            throw new ApiException(
                501,
                'FIX_NOT_IMPLEMENTED',
                "FIX protocol adapter for broker '{$name}' is not yet implemented. Use api_type=REST or NONE."
            );
        }

        throw new ApiException(
            422,
            'UNKNOWN_API_TYPE',
            "Unknown api_type '{$apiType}' for broker '{$name}'. Supported: NONE, REST."
        );
    }

    private function saveCredentials(
        string $brokerId,
        array $credentials,
        array $authResult
    ): void {
        $id = $this->uuid();
        $now = $this->now();

        $sql = 'INSERT INTO trading.broker_credential
            (credential_id, broker_id, credential_type,
             api_key_enc, api_secret_enc, access_token,
             token_expires_at, is_active, created_at)
            VALUES
            (:id, :broker_id, :type,
             :api_key, :api_secret, :token,
             :expires_at, :active, :now)';

        $expiresAt = null;
        if (isset($authResult['expires_in'])) {
            $expiresAt = date(
                'Y-m-d H:i:s',
                time() + (int) $authResult['expires_in']
            );
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id' => $id,
            ':broker_id' => $brokerId,
            ':type' => 'API_KEY',
            ':api_key' => $credentials['api_key'] ?? null,
            ':api_secret' => $credentials['api_secret'] ?? null,
            ':token' => $authResult['access_token'] ?? null,
            ':expires_at' => $expiresAt,
            ':active' => 1,
            ':now' => $now,
        ]);
    }
}
