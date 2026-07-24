<?php

declare(strict_types=1);

namespace Platform\Tests\Microstructure;

use PHPUnit\Framework\TestCase;
use Platform\Core\BaseService;
use Platform\Microstructure\MicrostructureService;
use Platform\Microstructure\MicrostructureServiceInterface;
use Platform\Tests\Integration\MockPdo;
use Platform\Tests\Integration\MockPdoStatement;

final class MicrostructureServiceTest extends TestCase
{
    private MicrostructureService $service;
    private MockPdo $db;

    protected function setUp(): void
    {
        $this->db = new MockPdo();
        $this->service = new MicrostructureService($this->db);
    }

    public function testImplementsInterface(): void
    {
        $this->assertInstanceOf(
            MicrostructureServiceInterface::class,
            $this->service
        );
    }

    public function testCaptureOrderBookCalculatesSpreadAndMid(): void
    {
        $result = $this->service->captureOrderBook([
            'instrument_id' => 'inst-001',
            'exchange_id' => 'ex-001',
            'timestamp' => '2026-01-01 10:00:00',
            'bid_price_1' => 999,
            'bid_volume_1' => 1000,
            'ask_price_1' => 1001,
            'ask_volume_1' => 500,
        ]);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    public function testGetOrderBookReturnsNullForMissing(): void
    {
        $result = $this->service->getOrderBook('nonexistent');
        $this->assertNull($result);
    }

    public function testGetLatestOrderBookReturnsNullForMissing(): void
    {
        $result = $this->service->getLatestOrderBook('nonexistent');
        $this->assertNull($result);
    }

    public function testListOrderBooksReturnsPaginatedArray(): void
    {
        $result = $this->service->listOrderBooks([], 1, 20);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('meta', $result);
        $this->assertArrayHasKey('total', $result['meta']);
        $this->assertArrayHasKey('page', $result['meta']);
        $this->assertArrayHasKey('per_page', $result['meta']);
    }

    public function testCalculateSpreadAnalysisReturnsArray(): void
    {
        $result = $this->service->calculateSpreadAnalysis('inst-001', 30);
        $this->assertIsArray($result);
        $this->assertSame('inst-001', $result['instrument_id']);
        $this->assertSame(30, $result['period_days']);
        $this->assertArrayHasKey('liquidity_regime', $result);
        $this->assertArrayHasKey('sample_count', $result);
    }

    public function testCalculateLiquidityProfileReturnsArray(): void
    {
        $result = $this->service->calculateLiquidityProfile('inst-001');
        $this->assertIsArray($result);
        $this->assertSame('inst-001', $result['instrument_id']);
        $this->assertArrayHasKey('liquidity_score', $result);
        $this->assertArrayHasKey('liquidity_grade', $result);
        $this->assertArrayHasKey('liquidity_regime', $result);
    }

    public function testListMetricsReturnsPaginatedArray(): void
    {
        $result = $this->service->listMetrics([], 1, 20);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('meta', $result);
        $this->assertArrayHasKey('total', $result['meta']);
    }

    public function testGetMetricsReturnsNullForMissing(): void
    {
        $result = $this->service->getMetrics('nonexistent', '2026-01-01');
        $this->assertNull($result);
    }
}
