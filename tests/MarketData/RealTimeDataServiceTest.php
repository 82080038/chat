<?php

declare(strict_types=1);

namespace Platform\Tests\MarketData;

use PHPUnit\Framework\TestCase;
use Platform\Core\Exceptions\ApiException;
use Platform\MarketData\RealTimeDataService;
use Platform\Tests\Config\InMemoryCacheStore;

final class RealTimeDataServiceTest extends TestCase
{
    public function testGetQuoteReturnsCachedData(): void
    {
        $cache = new InMemoryCacheStore();
        $cache->set(
            'market_quote:BBCA',
            json_encode([
                'symbol' => 'BBCA',
                'price' => 7800.0,
                'currency' => 'IDR',
                'exchange' => 'JKSE',
                'market_time' => '2026-07-25T10:00:00+07:00',
                'source' => 'YAHOO',
                'fetched_at' => date('c'),
                'cached' => false,
            ]),
            60
        );

        $service = new RealTimeDataService($cache);
        $quote = $service->getQuote('BBCA', 60);

        $this->assertSame('BBCA', $quote['symbol']);
        $this->assertEqualsWithDelta(7800.0, $quote['price'], 0.0001);
        $this->assertTrue($quote['cached']);
    }

    public function testGetQuoteThrowsValidationErrorForEmptySymbol(): void
    {
        $service = new RealTimeDataService(new InMemoryCacheStore());

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Symbol is required');

        $service->getQuote('');
    }

    public function testTokenBucketPermitsImmediateRequest(): void
    {
        $cache = new InMemoryCacheStore();
        $cache->set(
            'market_quote:BTC-USD',
            json_encode([
                'symbol' => 'BTC-USD',
                'price' => 65000.0,
                'currency' => 'USD',
                'exchange' => 'CCC',
                'market_time' => null,
                'source' => 'YAHOO',
                'fetched_at' => date('c'),
                'cached' => false,
            ]),
            60
        );

        $service = new RealTimeDataService($cache);
        $quote = $service->getQuote('BTC-USD', 60);

        $this->assertSame('BTC-USD', $quote['symbol']);
        $this->assertTrue($quote['cached']);
    }
}
