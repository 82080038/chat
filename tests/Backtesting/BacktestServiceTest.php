<?php

declare(strict_types=1);

namespace Platform\Tests\Backtesting;

use PHPUnit\Framework\TestCase;
use Platform\Backtesting\BacktestService;
use Platform\Tests\Integration\MockPdo;

final class BacktestServiceTest extends TestCase
{
    private MockPdo $pdo;

    protected function setUp(): void
    {
        $this->pdo = new MockPdo();
    }

    public function testServiceImplementsInterface(): void
    {
        $this->assertTrue(is_subclass_of(
            BacktestService::class,
            \Platform\Backtesting\BacktestServiceInterface::class
        ));
    }

    public function testCreateRunPersistsRecord(): void
    {
        $service = new BacktestService($this->pdo);
        $result = $service->createRun([
            'strategy_name' => 'MA_Crossover',
            'start_date' => '2024-01-01',
            'end_date' => '2024-06-30',
            'initial_capital' => 100000,
            'instrument_id' => 'inst-001',
        ]);
        $this->assertSame('MA_Crossover', $result['strategy_name']);
        $this->assertSame('PENDING', $result['status']);
        $this->assertSame('100000', (string) $result['initial_capital']);
    }

    public function testCreateRunMissingFieldThrows(): void
    {
        $service = new BacktestService($this->pdo);
        $this->expectException(\Platform\Core\Exceptions\ApiException::class);
        $service->createRun([
            'strategy_name' => 'Test',
        ]);
    }

    public function testCreateRunInvalidDateRangeThrows(): void
    {
        $service = new BacktestService($this->pdo);
        $this->expectException(\Platform\Core\Exceptions\ApiException::class);
        $service->createRun([
            'strategy_name' => 'Test',
            'start_date' => '2024-06-01',
            'end_date' => '2024-01-01',
            'initial_capital' => 100000,
        ]);
    }

    public function testCreateRunInvalidCapitalThrows(): void
    {
        $service = new BacktestService($this->pdo);
        $this->expectException(\Platform\Core\Exceptions\ApiException::class);
        $service->createRun([
            'strategy_name' => 'Test',
            'start_date' => '2024-01-01',
            'end_date' => '2024-06-01',
            'initial_capital' => -100,
        ]);
    }

    public function testListRunsPaginated(): void
    {
        $service = new BacktestService($this->pdo);
        $service->createRun([
            'strategy_name' => 'Strategy A',
            'start_date' => '2024-01-01',
            'end_date' => '2024-06-30',
            'initial_capital' => 50000,
        ]);
        $result = $service->listRuns([], 1, 50);
        $this->assertCount(1, $result['data']);
        $this->assertSame(1, $result['meta']['total']);
    }

    public function testExecuteRunCompletes(): void
    {
        $service = new BacktestService($this->pdo);
        $run = $service->createRun([
            'strategy_name' => 'Test',
            'start_date' => '2024-01-01',
            'end_date' => '2024-01-10',
            'initial_capital' => 100000,
            'instrument_id' => 'inst-001',
        ]);

        $priceData = [
            ['date' => '2024-01-01', 'close' => 100],
            ['date' => '2024-01-02', 'close' => 105],
            ['date' => '2024-01-03', 'close' => 102],
            ['date' => '2024-01-04', 'close' => 108],
        ];

        $result = $service->executeRun($run['run_id'], $priceData);
        $this->assertSame('COMPLETED', $result['status']);
        $this->assertGreaterThan(0, $result['total_trades']);
        $this->assertArrayHasKey('metrics', $result);
    }

    public function testExecuteRunNotFoundThrows(): void
    {
        $service = new BacktestService($this->pdo);
        $this->expectException(\Platform\Core\Exceptions\ApiException::class);
        $service->executeRun('nonexistent', []);
    }

    public function testCalculateMetricsWithNoTrades(): void
    {
        $service = new BacktestService($this->pdo);
        $metrics = $service->calculateMetrics([], 100000);
        $this->assertSame(0, $metrics['total_trades']);
        $this->assertSame(0.0, $metrics['total_return']);
    }

    public function testCalculateMetricsWithWinningTrades(): void
    {
        $service = new BacktestService($this->pdo);
        $trades = [
            ['pnl' => 500],
            ['pnl' => 300],
            ['pnl' => -200],
        ];
        $metrics = $service->calculateMetrics($trades, 10000);
        $this->assertSame(3, $metrics['total_trades']);
        $this->assertSame(2, $metrics['winning_trades']);
        $this->assertSame(1, $metrics['losing_trades']);
        $this->assertGreaterThan(0, $metrics['win_rate']);
        $this->assertGreaterThan(0, $metrics['profit_factor']);
        $this->assertSame(600.0, $metrics['total_pnl']);
    }

    public function testCalculateMetricsAllLosses(): void
    {
        $service = new BacktestService($this->pdo);
        $trades = [
            ['pnl' => -100],
            ['pnl' => -200],
        ];
        $metrics = $service->calculateMetrics($trades, 10000);
        $this->assertSame(0, $metrics['winning_trades']);
        $this->assertSame(2, $metrics['losing_trades']);
        $this->assertSame(0.0, $metrics['win_rate']);
        $this->assertSame(0.0, $metrics['profit_factor']);
    }

    public function testGetRunTrades(): void
    {
        $service = new BacktestService($this->pdo);
        $run = $service->createRun([
            'strategy_name' => 'Test',
            'start_date' => '2024-01-01',
            'end_date' => '2024-01-10',
            'initial_capital' => 100000,
            'instrument_id' => 'inst-001',
        ]);
        $service->executeRun($run['run_id'], [
            ['date' => '2024-01-01', 'close' => 100],
            ['date' => '2024-01-02', 'close' => 110],
        ]);
        $trades = $service->getRunTrades($run['run_id']);
        $this->assertNotEmpty($trades);
    }

    public function testGetRunMetrics(): void
    {
        $service = new BacktestService($this->pdo);
        $run = $service->createRun([
            'strategy_name' => 'Test',
            'start_date' => '2024-01-01',
            'end_date' => '2024-01-10',
            'initial_capital' => 100000,
            'instrument_id' => 'inst-001',
        ]);
        $service->executeRun($run['run_id'], [
            ['date' => '2024-01-01', 'close' => 100],
            ['date' => '2024-01-02', 'close' => 110],
        ]);
        $metrics = $service->getRunMetrics($run['run_id']);
        $this->assertNotNull($metrics);
        $this->assertGreaterThan(0, $metrics['total_trades']);
    }
}
