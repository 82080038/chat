<?php

declare(strict_types=1);

namespace Platform\Tests\Trading;

use PHPUnit\Framework\TestCase;
use Platform\Trading\Adapters\MockBrokerAdapter;
use Platform\Trading\BrokerAdapterInterface;
use Platform\Trading\BrokerAdapterService;
use Platform\Tests\Integration\MockPdo;

final class BrokerAdapterTest extends TestCase
{
    public function testMockAdapterImplementsInterface(): void
    {
        $this->assertTrue(is_subclass_of(
            MockBrokerAdapter::class,
            BrokerAdapterInterface::class
        ));
    }

    public function testAuthenticateReturnsToken(): void
    {
        $adapter = new MockBrokerAdapter('TEST_BROKER');
        $result = $adapter->authenticate([
            'api_key' => 'test_key',
            'api_secret' => 'test_secret',
        ]);
        $this->assertNotEmpty($result['access_token']);
        $this->assertSame('Bearer', $result['token_type']);
        $this->assertSame('TEST_BROKER', $result['broker']);
    }

    public function testAuthenticateMissingCredentialsThrows(): void
    {
        $adapter = new MockBrokerAdapter();
        $this->expectException(\Platform\Core\Exceptions\ApiException::class);
        $adapter->authenticate(['api_key' => 'only_key']);
    }

    public function testGetBalanceRequiresAuth(): void
    {
        $adapter = new MockBrokerAdapter();
        $this->expectException(\Platform\Core\Exceptions\ApiException::class);
        $adapter->getAccountBalance();
    }

    public function testGetBalanceAfterAuth(): void
    {
        $adapter = new MockBrokerAdapter('TEST_BROKER');
        $adapter->authenticate([
            'api_key' => 'key',
            'api_secret' => 'secret',
        ]);
        $balance = $adapter->getAccountBalance();
        $this->assertSame('IDR', $balance['currency']);
        $this->assertGreaterThan(0, $balance['cash_balance']);
    }

    public function testGetHoldingsReturnsArray(): void
    {
        $adapter = new MockBrokerAdapter();
        $adapter->authenticate([
            'api_key' => 'key',
            'api_secret' => 'secret',
        ]);
        $holdings = $adapter->getPortfolioHoldings();
        $this->assertArrayHasKey('holdings', $holdings);
        $this->assertCount(2, $holdings['holdings']);
    }

    public function testGetRealtimePrice(): void
    {
        $adapter = new MockBrokerAdapter();
        $adapter->authenticate([
            'api_key' => 'key',
            'api_secret' => 'secret',
        ]);
        $price = $adapter->getRealtimePrice('BBCA');
        $this->assertSame('BBCA', $price['symbol']);
        $this->assertGreaterThan(0, $price['price']);
    }

    public function testPlaceOrder(): void
    {
        $adapter = new MockBrokerAdapter();
        $adapter->authenticate([
            'api_key' => 'key',
            'api_secret' => 'secret',
        ]);
        $order = $adapter->placeOrder([
            'symbol' => 'BBCA',
            'side' => 'BUY',
            'quantity' => 100,
            'order_type' => 'MARKET',
        ]);
        $this->assertSame('OPEN', $order['status']);
        $this->assertSame('BUY', $order['side']);
        $this->assertSame('BBCA', $order['symbol']);
    }

    public function testPlaceOrderInvalidSide(): void
    {
        $adapter = new MockBrokerAdapter();
        $adapter->authenticate([
            'api_key' => 'key',
            'api_secret' => 'secret',
        ]);
        $this->expectException(\Platform\Core\Exceptions\ApiException::class);
        $adapter->placeOrder([
            'symbol' => 'BBCA',
            'side' => 'INVALID',
            'quantity' => 100,
            'order_type' => 'MARKET',
        ]);
    }

    public function testCancelOrder(): void
    {
        $adapter = new MockBrokerAdapter();
        $adapter->authenticate([
            'api_key' => 'key',
            'api_secret' => 'secret',
        ]);
        $order = $adapter->placeOrder([
            'symbol' => 'BBCA',
            'side' => 'BUY',
            'quantity' => 100,
            'order_type' => 'LIMIT',
            'price' => 8000,
        ]);
        $cancelled = $adapter->cancelOrder($order['order_id']);
        $this->assertSame('CANCELLED', $cancelled['status']);
    }

    public function testCancelOrderNotFound(): void
    {
        $adapter = new MockBrokerAdapter();
        $adapter->authenticate([
            'api_key' => 'key',
            'api_secret' => 'secret',
        ]);
        $this->expectException(\Platform\Core\Exceptions\ApiException::class);
        $adapter->cancelOrder('nonexistent');
    }

    public function testGetOrderStatus(): void
    {
        $adapter = new MockBrokerAdapter();
        $adapter->authenticate([
            'api_key' => 'key',
            'api_secret' => 'secret',
        ]);
        $order = $adapter->placeOrder([
            'symbol' => 'TLKM',
            'side' => 'SELL',
            'quantity' => 500,
            'order_type' => 'MARKET',
        ]);
        $status = $adapter->getOrderStatus($order['order_id']);
        $this->assertSame($order['order_id'], $status['order_id']);
        $this->assertSame('OPEN', $status['status']);
    }

    public function testGetBrokerName(): void
    {
        $adapter = new MockBrokerAdapter('MY_BROKER');
        $this->assertSame('MY_BROKER', $adapter->getBrokerName());
    }

    public function testServiceImplementsInterface(): void
    {
        $this->assertTrue(
            method_exists(BrokerAdapterService::class, 'getAdapter')
        );
    }
}
