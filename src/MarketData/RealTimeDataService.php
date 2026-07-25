<?php

declare(strict_types=1);

namespace Platform\MarketData;

use Platform\Core\Cache\CacheStoreInterface;
use Platform\Core\Cache\RedisCacheStore;
use Platform\Core\Exceptions\ApiException;
use Platform\Core\Http\HttpClient;
use Platform\Core\RateLimit\TokenBucket;

/**
 * Real-time (near-real-time) market data service.
 *
 * Fetches the latest price for a symbol from Yahoo Finance, enforces an
 * internal token-bucket rate limit, and caches the result so repeated API
 * calls are avoided.
 *
 * Note: Yahoo Finance free data is delayed and its Terms of Service restrict
 * redistribution. This service is intended for personal/sandbox use only.
 */
final class RealTimeDataService
{
    private const DEFAULT_RATE = 4.0; // 4 requests per second
    private const DEFAULT_CAPACITY = 4.0;
    private const DEFAULT_CACHE_TTL = 60;

    private CacheStoreInterface $cache;
    private HttpClient $httpClient;
    private TokenBucket $bucket;

    public function __construct(
        ?CacheStoreInterface $cache = null,
        ?HttpClient $httpClient = null
    ) {
        $this->cache = $cache ?? RedisCacheStore::getInstance();
        $this->httpClient = $httpClient ?? new HttpClient();
        $this->bucket = new TokenBucket(
            $this->cache,
            'yahoo_finance',
            self::DEFAULT_RATE,
            self::DEFAULT_CAPACITY
        );
    }

    /**
     * Get the latest market quote for a symbol.
     *
     * Returns cached data if it exists and is not older than $maxAgeSeconds.
     * Otherwise fetches from Yahoo Finance with rate limiting.
     *
     * @return array{
     *     symbol: string,
     *     price: float|null,
     *     currency: string,
     *     exchange: string,
     *     market_time: string|null,
     *     source: string,
     *     cached: bool,
     *     fetched_at: string
     * }
     */
    public function getQuote(string $symbol, int $maxAgeSeconds = self::DEFAULT_CACHE_TTL): array
    {
        $symbol = strtoupper(trim($symbol));
        if ($symbol === '') {
            throw new ApiException(422, 'VALIDATION_ERROR', 'Symbol is required');
        }

        $cacheKey = "market_quote:{$symbol}";
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            $payload = json_decode($cached, true);
            if (is_array($payload)) {
                $cachedAt = strtotime((string) ($payload['fetched_at'] ?? 'now'));
                if ($cachedAt !== false && (time() - $cachedAt) <= $maxAgeSeconds) {
                    $payload['cached'] = true;
                    return $payload;
                }
            }
        }

        $quote = $this->fetchAndCacheQuote($symbol);
        $quote['cached'] = false;
        return $quote;
    }

    /**
     * Return the cached quote for a symbol without triggering an external fetch.
     *
     * @return array<string, mixed>|null
     */
    public function getCachedQuote(string $symbol): ?array
    {
        $symbol = strtoupper(trim($symbol));
        $cached = $this->cache->get("market_quote:{$symbol}");
        if ($cached === null) {
            return null;
        }

        $payload = json_decode($cached, true);
        return is_array($payload) ? $payload : null;
    }

    /**
     * Force a fresh fetch for a symbol, bypassing the cache.
     *
     * @return array<string, mixed>
     */
    public function refreshQuote(string $symbol): array
    {
        $symbol = strtoupper(trim($symbol));
        if ($symbol === '') {
            throw new ApiException(422, 'VALIDATION_ERROR', 'Symbol is required');
        }

        $quote = $this->fetchAndCacheQuote($symbol);
        $quote['cached'] = false;
        return $quote;
    }

    /**
     * Fetch a batch of symbols sequentially while respecting the rate limit.
     *
     * @param array<int, string> $symbols
     * @return array<string, array<string, mixed>>
     */
    public function refreshBatch(array $symbols): array
    {
        $results = [];
        foreach ($symbols as $symbol) {
            try {
                $results[strtoupper(trim($symbol))] = $this->refreshQuote($symbol);
            } catch (ApiException $e) {
                $results[strtoupper(trim($symbol))] = [
                    'symbol' => strtoupper(trim($symbol)),
                    'error' => $e->getMessage(),
                    'cached' => false,
                ];
            }
        }
        return $results;
    }

    private function fetchAndCacheQuote(string $symbol): array
    {
        $this->bucket->acquire();

        $url = "https://query1.finance.yahoo.com/v8/finance/chart/"
            . urlencode($symbol)
            . '?interval=1m&range=1d&includePrePost=false';

        $data = $this->httpClient->getJson(
            $url,
            ['User-Agent: Mozilla/5.0 (Platform Trading Bot)'],
            2,
            20
        );

        $quote = $this->parseQuote($data, $symbol);
        $cachePayload = $quote;
        $cachePayload['cached'] = false;
        $this->cache->set(
            "market_quote:{$symbol}",
            json_encode($cachePayload),
            max(1, self::DEFAULT_CACHE_TTL)
        );

        return $quote;
    }

    private function parseQuote(array $data, string $symbol): array
    {
        $result = $data['chart']['result'][0] ?? null;
        if (!is_array($result)) {
            throw new ApiException(
                502,
                'EXTERNAL_API_ERROR',
                "Yahoo Finance returned no chart data for '{$symbol}'"
            );
        }

        $meta = $result['meta'] ?? [];
        $quote = $result['indicators']['quote'][0] ?? [];
        $adjClose = $result['indicators']['adjclose'][0]['adjclose'] ?? [];
        $timestamps = $result['timestamp'] ?? [];

        $price = null;
        if (isset($meta['regularMarketPrice']) && is_numeric($meta['regularMarketPrice'])) {
            $price = (float) $meta['regularMarketPrice'];
        } elseif (isset($quote['close']) && is_array($quote['close']) && $quote['close'] !== []) {
            $lastClose = null;
            foreach ($quote['close'] as $value) {
                if ($value !== null && $value !== '') {
                    $lastClose = $value;
                }
            }
            if ($lastClose !== null) {
                $price = (float) $lastClose;
            }
        }

        $lastTimestamp = $timestamps !== [] ? (int) $timestamps[count($timestamps) - 1] : null;
        $marketTime = $lastTimestamp !== null ? date('c', $lastTimestamp) : null;

        return [
            'symbol' => $symbol,
            'price' => $price,
            'currency' => (string) ($meta['currency'] ?? ''),
            'exchange' => (string) ($meta['exchangeName'] ?? ''),
            'market_time' => $marketTime,
            'source' => 'YAHOO',
            'fetched_at' => date('c'),
        ];
    }
}
