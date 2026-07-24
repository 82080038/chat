<?php

declare(strict_types=1);

namespace Platform\Tests\Config;

use Platform\Core\Cache\CacheStoreInterface;

final class InMemoryCacheStore implements CacheStoreInterface
{
    private array $values = [];

    public function get(string $key): ?string
    {
        return $this->values[$key] ?? null;
    }

    public function set(string $key, string $value, int $ttlSeconds): void
    {
        $this->values[$key] = $value;
    }

    public function delete(string $key): void
    {
        unset($this->values[$key]);
    }
}
