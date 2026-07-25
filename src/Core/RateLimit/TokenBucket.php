<?php

declare(strict_types=1);

namespace Platform\Core\RateLimit;

use Platform\Core\Cache\CacheStoreInterface;

/**
 * Distributed token-bucket rate limiter backed by a CacheStore.
 *
 * This implementation is intentionally simple and blocking: if no token is
 * available, the caller sleeps until one is ready. It is suitable for
 * throttling outbound API calls (e.g. Yahoo Finance) where the application
 * prefers to wait rather than fail.
 */
final class TokenBucket
{
    private CacheStoreInterface $cache;
    private string $key;
    private float $ratePerSecond;
    private float $capacity;

    public function __construct(
        CacheStoreInterface $cache,
        string $key,
        float $ratePerSecond,
        float $capacity
    ) {
        $this->cache = $cache;
        $this->key = $key;
        $this->ratePerSecond = $ratePerSecond;
        $this->capacity = $capacity;
    }

    /**
     * Acquire one token, blocking until it is available.
     */
    public function acquire(): void
    {
        while (!$this->tryAcquire()) {
            usleep(50_000); // 50 ms
        }
    }

    private function tryAcquire(): bool
    {
        $now = microtime(true);
        $stateKey = "token_bucket:{$this->key}";
        $data = $this->cache->get($stateKey);

        if ($data === null) {
            $tokens = $this->capacity - 1;
            $this->store($tokens, $now);
            return true;
        }

        /** @var array{tokens: float, updated_at: float}|null $state */
        $state = json_decode($data, true);
        if (!is_array($state)) {
            $tokens = $this->capacity - 1;
            $this->store($tokens, $now);
            return true;
        }

        $previousTokens = (float) ($state['tokens'] ?? 0);
        $updatedAt = (float) ($state['updated_at'] ?? 0);
        $tokens = min(
            $this->capacity,
            $previousTokens + (($now - $updatedAt) * $this->ratePerSecond)
        );

        if ($tokens < 1) {
            return false;
        }

        $this->store($tokens - 1, $now);
        return true;
    }

    private function store(float $tokens, float $updatedAt): void
    {
        $ttl = (int) ceil($this->capacity / $this->ratePerSecond) + 1;
        $this->cache->set(
            "token_bucket:{$this->key}",
            json_encode(['tokens' => $tokens, 'updated_at' => $updatedAt]),
            max(1, $ttl)
        );
    }
}
