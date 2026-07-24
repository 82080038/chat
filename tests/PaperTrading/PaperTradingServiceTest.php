<?php

declare(strict_types=1);

namespace Platform\Tests\PaperTrading;

use PHPUnit\Framework\TestCase;
use Platform\PaperTrading\PaperTradingService;
use Platform\Tests\Integration\MockPdo;

final class PaperTradingServiceTest extends TestCase
{
    private MockPdo $pdo;

    protected function setUp(): void
    {
        $this->pdo = new MockPdo();
    }

    public function testServiceImplementsInterface(): void
    {
        $this->assertTrue(is_subclass_of(
            PaperTradingService::class,
            \Platform\PaperTrading\PaperTradingServiceInterface::class
        ));
    }

    public function testCreateAccount(): void
    {
        $service = new PaperTradingService($this->pdo);
        $result = $service->createAccount([
            'name' => 'Test Paper Account',
            'initial_cash' => 1000000,
        ]);
        $this->assertSame('Test Paper Account', $result['name']);
        $this->assertSame('1000000', (string) $result['initial_cash']);
        $this->assertSame('ACTIVE', $result['status']);
    }

    public function testCreateAccountMissingFieldThrows(): void
    {
        $service = new PaperTradingService($this->pdo);
        $this->expectException(\Platform\Core\Exceptions\ApiException::class);
        $service->createAccount(['name' => 'Test']);
    }

    public function testCreateAccountInvalidCashThrows(): void
    {
        $service = new PaperTradingService($this->pdo);
        $this->expectException(\Platform\Core\Exceptions\ApiException::class);
        $service->createAccount(['name' => 'Test', 'initial_cash' => -100]);
    }

    public function testPlaceBuyOrder(): void
    {
        $service = new PaperTradingService($this->pdo);
        $account = $service->createAccount([
            'name' => 'Test',
            'initial_cash' => 1000000,
        ]);
        $order = $service->placeOrder($account['account_id'], [
            'instrument_id' => 'inst-001',
            'symbol' => 'BBCA',
            'side' => 'BUY',
            'quantity' => 100,
            'market_price' => 8000,
        ]);
        $this->assertSame('FILLED', $order['status']);
        $this->assertSame('BUY', $order['side']);
        $this->assertSame(8000, (int) $order['filled_price']);
    }

    public function testPlaceOrderInsufficientCashThrows(): void
    {
        $service = new PaperTradingService($this->pdo);
        $account = $service->createAccount([
            'name' => 'Test',
            'initial_cash' => 1000,
        ]);
        $this->expectException(\Platform\Core\Exceptions\ApiException::class);
        $service->placeOrder($account['account_id'], [
            'instrument_id' => 'inst-001',
            'symbol' => 'BBCA',
            'side' => 'BUY',
            'quantity' => 100,
            'market_price' => 8000,
        ]);
    }

    public function testPlaceOrderInvalidSideThrows(): void
    {
        $service = new PaperTradingService($this->pdo);
        $account = $service->createAccount([
            'name' => 'Test',
            'initial_cash' => 1000000,
        ]);
        $this->expectException(\Platform\Core\Exceptions\ApiException::class);
        $service->placeOrder($account['account_id'], [
            'instrument_id' => 'inst-001',
            'symbol' => 'BBCA',
            'side' => 'HOLD',
            'quantity' => 100,
        ]);
    }

    public function testPlaceOrderAccountNotFoundThrows(): void
    {
        $service = new PaperTradingService($this->pdo);
        $this->expectException(\Platform\Core\Exceptions\ApiException::class);
        $service->placeOrder('nonexistent', [
            'instrument_id' => 'inst-001',
            'symbol' => 'BBCA',
            'side' => 'BUY',
            'quantity' => 100,
        ]);
    }

    public function testSellOrderUpdatesPosition(): void
    {
        $service = new PaperTradingService($this->pdo);
        $account = $service->createAccount([
            'name' => 'Test',
            'initial_cash' => 1000000,
        ]);
        $service->placeOrder($account['account_id'], [
            'instrument_id' => 'inst-001',
            'symbol' => 'BBCA',
            'side' => 'BUY',
            'quantity' => 100,
            'market_price' => 8000,
        ]);
        $sellOrder = $service->placeOrder($account['account_id'], [
            'instrument_id' => 'inst-001',
            'symbol' => 'BBCA',
            'side' => 'SELL',
            'quantity' => 50,
            'market_price' => 8500,
        ]);
        $this->assertSame('FILLED', $sellOrder['status']);
        $this->assertSame('SELL', $sellOrder['side']);
    }

    public function testGetPositions(): void
    {
        $service = new PaperTradingService($this->pdo);
        $account = $service->createAccount([
            'name' => 'Test',
            'initial_cash' => 1000000,
        ]);
        $service->placeOrder($account['account_id'], [
            'instrument_id' => 'inst-001',
            'symbol' => 'BBCA',
            'side' => 'BUY',
            'quantity' => 100,
            'market_price' => 8000,
        ]);
        $positions = $service->getPositions($account['account_id']);
        $this->assertCount(1, $positions);
        $this->assertSame('BBCA', $positions[0]['symbol']);
    }

    public function testGetSummary(): void
    {
        $service = new PaperTradingService($this->pdo);
        $account = $service->createAccount([
            'name' => 'Test',
            'initial_cash' => 1000000,
        ]);
        $service->placeOrder($account['account_id'], [
            'instrument_id' => 'inst-001',
            'symbol' => 'BBCA',
            'side' => 'BUY',
            'quantity' => 100,
            'market_price' => 8000,
        ]);
        $summary = $service->getSummary($account['account_id']);
        $this->assertSame(1, $summary['total_orders']);
        $this->assertSame(1, $summary['filled_orders']);
        $this->assertSame(1, $summary['open_positions']);
        $this->assertSame(200000.0, $summary['cash_balance']);
    }

    public function testListOrders(): void
    {
        $service = new PaperTradingService($this->pdo);
        $account = $service->createAccount([
            'name' => 'Test',
            'initial_cash' => 1000000,
        ]);
        $service->placeOrder($account['account_id'], [
            'instrument_id' => 'inst-001',
            'symbol' => 'BBCA',
            'side' => 'BUY',
            'quantity' => 100,
            'market_price' => 8000,
        ]);
        $result = $service->listOrders($account['account_id'], 1, 50);
        $this->assertCount(1, $result['data']);
    }

    public function testValidateSignalNoExistingOrders(): void
    {
        $service = new PaperTradingService($this->pdo);
        $account = $service->createAccount([
            'name' => 'Test',
            'initial_cash' => 1000000,
        ]);
        $result = $service->validateSignal('sig-001', $account['account_id']);
        $this->assertFalse($result['already_traded']);
        $this->assertSame('OK', $result['validation']);
    }

    public function testValidateSignalWithExistingOrder(): void
    {
        $service = new PaperTradingService($this->pdo);
        $account = $service->createAccount([
            'name' => 'Test',
            'initial_cash' => 1000000,
        ]);
        $service->placeOrder($account['account_id'], [
            'instrument_id' => 'inst-001',
            'symbol' => 'BBCA',
            'side' => 'BUY',
            'quantity' => 100,
            'market_price' => 8000,
            'signal_id' => 'sig-001',
        ]);
        $result = $service->validateSignal('sig-001', $account['account_id']);
        $this->assertTrue($result['already_traded']);
        $this->assertSame('DUPLICATE', $result['validation']);
    }

    public function testLimitOrderRequiresPrice(): void
    {
        $service = new PaperTradingService($this->pdo);
        $account = $service->createAccount([
            'name' => 'Test',
            'initial_cash' => 1000000,
        ]);
        $this->expectException(\Platform\Core\Exceptions\ApiException::class);
        $service->placeOrder($account['account_id'], [
            'instrument_id' => 'inst-001',
            'symbol' => 'BBCA',
            'side' => 'BUY',
            'quantity' => 100,
            'order_type' => 'LIMIT',
        ]);
    }
}
