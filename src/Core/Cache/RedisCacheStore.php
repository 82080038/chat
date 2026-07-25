<?php

declare(strict_types=1);

namespace Platform\Core\Cache;

use Platform\Core\Application;
use Predis\Client;
use Throwable;

final class RedisCacheStore implements CacheStoreInterface
{
    private static ?self $instance = null;

    private Client $client;

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct(?Client $client = null)
    {
        $app = Application::getInstance();
        $this->client = $client ?? new Client([
            'scheme' => 'tcp',
            'host' => $app->getConfig('REDIS_HOST', '127.0.0.1'),
            'port' => (int) $app->getConfig('REDIS_PORT', 6379),
            'database' => (int) $app->getConfig('REDIS_DB', 0),
        ]);
    }

    public function get(string $key): ?string
    {
        try {
            $value = $this->client->get($key);
            return is_string($value) ? $value : null;
        } catch (Throwable) {
            return null;
        }
    }

    public function set(string $key, string $value, int $ttlSeconds): void
    {
        try {
            $this->client->setex($key, $ttlSeconds, $value);
        } catch (Throwable) {
        }
    }

    public function delete(string $key): void
    {
        try {
            $this->client->del([$key]);
        } catch (Throwable) {
        }
    }

    public function ping(): bool
    {
        try {
            $this->client->ping();
            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
