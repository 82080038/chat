<?php

declare(strict_types=1);

namespace Platform\Core\Middleware;

use Platform\Core\Cache\RedisCacheStore;
use Platform\Core\Http\Request;
use Platform\Core\Http\Response;

final class RateLimitMiddleware
{
    private const DEFAULT_LIMIT = 60;
    private const DEFAULT_WINDOW = 60;
    private const AUTH_LIMIT = 20;
    private const AUTH_WINDOW = 60;

    private static ?RedisCacheStore $cache = null;

    private static function cache(): ?RedisCacheStore
    {
        if (self::$cache === null) {
            try {
                self::$cache = RedisCacheStore::getInstance();
            } catch (\Throwable) {
                self::$cache = null;
            }
        }
        return self::$cache;
    }

    public static function api(Request $request): ?Response
    {
        return self::check(self::DEFAULT_LIMIT, self::DEFAULT_WINDOW, 'api', $request);
    }

    public static function auth(Request $request): ?Response
    {
        return self::check(self::AUTH_LIMIT, self::AUTH_WINDOW, 'auth', $request);
    }

    private static function check(int $limit, int $window, string $type, Request $request): ?Response
    {
        $cache = self::cache();
        if ($cache === null) {
            return null;
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = "rate_limit:{$type}:{$ip}";

        $current = (int) ($cache->get($key) ?? 0);
        if ($current === 0) {
            $cache->set($key, '1', $window);
            $current = 1;
        } else {
            $current++;
            $cache->set($key, (string) $current, $window);
        }

        $remaining = max(0, $limit - $current);
        $request
            ->setAttribute('X-RateLimit-Limit', (string) $limit)
            ->setAttribute('X-RateLimit-Remaining', (string) $remaining);

        if ($current > $limit) {
            return Response::error(
                429,
                'RATE_LIMIT_EXCEEDED',
                'Too many requests. Please retry later.'
            )->addHeader('X-RateLimit-Limit', (string) $limit)
             ->addHeader('X-RateLimit-Remaining', '0')
             ->addHeader('Retry-After', (string) $window);
        }

        return null;
    }
}
