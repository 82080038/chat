<?php

declare(strict_types=1);

namespace Platform\Tests\DataIngestion;

use PHPUnit\Framework\TestCase;
use Platform\DataIngestion\DataIngestionService;
use Platform\Tests\Integration\MockPdo;

final class DataIngestionServiceTest extends TestCase
{
    private MockPdo $pdo;

    protected function setUp(): void
    {
        $this->pdo = new MockPdo();
    }

    public function testServiceImplementsInterface(): void
    {
        $this->assertTrue(is_subclass_of(
            DataIngestionService::class,
            \Platform\DataIngestion\DataIngestionServiceInterface::class
        ));
    }

    public function testIngestOhlcvCreatesRecord(): void
    {
        $service = new DataIngestionService($this->pdo);
        $result = $service->ingestOhlcv([
            'instrument_id' => 'inst-001',
            'trade_date' => '2026-07-24',
            'open' => 7500,
            'high' => 7600,
            'low' => 7400,
            'close' => 7550,
            'volume' => 1000000,
            'source' => 'IDX',
        ]);
        $this->assertNotNull($result);
        $this->assertSame('inst-001', $result['instrument_id']);
        $this->assertSame('2026-07-24', $result['trade_date']);
        $this->assertSame('IDX', $result['source']);
    }

    public function testIngestOhlcvMissingFieldThrows(): void
    {
        $service = new DataIngestionService($this->pdo);
        $this->expectException(\Platform\Core\Exceptions\ApiException::class);
        $service->ingestOhlcv([
            'instrument_id' => 'inst-001',
            'trade_date' => '2026-07-24',
        ]);
    }

    public function testListOhlcvReturnsPaginatedData(): void
    {
        $service = new DataIngestionService($this->pdo);
        $service->ingestOhlcv([
            'instrument_id' => 'inst-001',
            'trade_date' => '2026-07-24',
            'open' => 7500,
            'high' => 7600,
            'low' => 7400,
            'close' => 7550,
        ]);
        $result = $service->listOhlcv([], 1, 50);
        $this->assertCount(1, $result['data']);
        $this->assertSame(1, $result['meta']['total']);
    }

    public function testListOhlcvFiltersByInstrument(): void
    {
        $service = new DataIngestionService($this->pdo);
        $service->ingestOhlcv([
            'instrument_id' => 'inst-001',
            'trade_date' => '2026-07-24',
            'open' => 7500,
            'high' => 7600,
            'low' => 7400,
            'close' => 7550,
        ]);
        $service->ingestOhlcv([
            'instrument_id' => 'inst-002',
            'trade_date' => '2026-07-24',
            'open' => 1000,
            'high' => 1100,
            'low' => 900,
            'close' => 1050,
        ]);
        $result = $service->listOhlcv(['instrument_id' => 'inst-001'], 1, 50);
        $this->assertCount(1, $result['data']);
        $this->assertSame('inst-001', $result['data'][0]['instrument_id']);
    }

    public function testGetOhlcvHistoryReturnsChronological(): void
    {
        $service = new DataIngestionService($this->pdo);
        $service->ingestOhlcv([
            'instrument_id' => 'inst-001',
            'trade_date' => '2026-07-23',
            'open' => 7400,
            'high' => 7500,
            'low' => 7300,
            'close' => 7450,
        ]);
        $service->ingestOhlcv([
            'instrument_id' => 'inst-001',
            'trade_date' => '2026-07-24',
            'open' => 7500,
            'high' => 7600,
            'low' => 7400,
            'close' => 7550,
        ]);
        $history = $service->getOhlcvHistory('inst-001', null, null);
        $this->assertCount(2, $history);
    }

    public function testGetIngestionStatusReturnsCounts(): void
    {
        $service = new DataIngestionService($this->pdo);
        $service->ingestOhlcv([
            'instrument_id' => 'inst-001',
            'trade_date' => '2026-07-24',
            'open' => 7500,
            'high' => 7600,
            'low' => 7400,
            'close' => 7550,
            'source' => 'IDX',
        ]);
        $status = $service->getIngestionStatus();
        $this->assertSame(1, $status['total_records']);
    }
}
