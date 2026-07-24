<?php

declare(strict_types=1);

namespace Platform\Tests\Valuation;

use PHPUnit\Framework\TestCase;
use Platform\Valuation\ValuationService;
use Platform\Tests\Integration\MockPdo;

final class ValuationServiceTest extends TestCase
{
    private MockPdo $pdo;

    protected function setUp(): void
    {
        $this->pdo = new MockPdo();
    }

    public function testServiceImplementsInterface(): void
    {
        $this->assertTrue(is_subclass_of(
            ValuationService::class,
            \Platform\Valuation\ValuationServiceInterface::class
        ));
    }

    public function testCreateValuationPersistsRecord(): void
    {
        $service = new ValuationService($this->pdo);
        $result = $service->createValuation([
            'instrument_id' => 'inst-001',
            'valuation_type' => 'DCF',
            'fair_value' => 8500,
            'as_of_date' => '2026-07-24',
            'discount_rate' => 0.10,
            'terminal_growth' => 0.03,
        ]);
        $this->assertSame('inst-001', $result['instrument_id']);
        $this->assertSame('DCF', $result['valuation_type']);
    }

    public function testCreateValuationMissingFieldThrows(): void
    {
        $service = new ValuationService($this->pdo);
        $this->expectException(\Platform\Core\Exceptions\ApiException::class);
        $service->createValuation([
            'instrument_id' => 'inst-001',
        ]);
    }

    public function testListValuationsPaginated(): void
    {
        $service = new ValuationService($this->pdo);
        $service->createValuation([
            'instrument_id' => 'inst-001',
            'valuation_type' => 'DCF',
            'fair_value' => 8500,
            'as_of_date' => '2026-07-24',
        ]);
        $result = $service->listValuations([], 1, 50);
        $this->assertCount(1, $result['data']);
        $this->assertSame(1, $result['meta']['total']);
    }

    public function testGetInstrumentValuations(): void
    {
        $service = new ValuationService($this->pdo);
        $service->createValuation([
            'instrument_id' => 'inst-001',
            'valuation_type' => 'DCF',
            'fair_value' => 8500,
            'as_of_date' => '2026-07-24',
        ]);
        $service->createValuation([
            'instrument_id' => 'inst-001',
            'valuation_type' => 'RELATIVE',
            'fair_value' => 8200,
            'as_of_date' => '2026-07-24',
        ]);
        $rows = $service->getInstrumentValuations('inst-001');
        $this->assertCount(2, $rows);
    }

    public function testCalculateDcf(): void
    {
        $service = new ValuationService($this->pdo);
        $result = $service->calculateDcf([
            'projected_fcf' => [1000, 1100, 1210, 1331, 1464],
            'discount_rate' => 0.10,
            'terminal_growth' => 0.03,
        ]);
        $this->assertSame('DCF', $result['method']);
        $this->assertGreaterThan(0, $result['fair_value']);
        $this->assertGreaterThan(0, $result['pv_of_terminal']);
        $this->assertCount(5, $result['pv_details']);
    }

    public function testCalculateDcfInvalidRateThrows(): void
    {
        $service = new ValuationService($this->pdo);
        $this->expectException(\Platform\Core\Exceptions\ApiException::class);
        $service->calculateDcf([
            'projected_fcf' => [1000, 1100],
            'discount_rate' => 1.5,
            'terminal_growth' => 0.03,
        ]);
    }

    public function testCalculateRelative(): void
    {
        $service = new ValuationService($this->pdo);
        $result = $service->calculateRelative([
            'peer_values' => [15, 18, 12, 14, 16],
            'metric_name' => 'P/E',
            'current_metric_value' => 500,
        ]);
        $this->assertSame('RELATIVE', $result['method']);
        $this->assertSame(5, $result['peer_count']);
        $this->assertGreaterThan(0, $result['fair_value']);
        $this->assertSame('P/E', $result['metric_name']);
    }

    public function testCalculateFairValueBlend(): void
    {
        $service = new ValuationService($this->pdo);
        $result = $service->calculateFairValue([
            'dcf_result' => ['fair_value' => 9000],
            'relative_result' => ['fair_value' => 8000],
            'weights' => ['dcf' => 0.6, 'relative' => 0.4],
        ]);
        $this->assertSame('BLENDED', $result['method']);
        $this->assertEquals(8600, $result['fair_value']);
        $this->assertEquals(0.6, $result['dcf_weight']);
        $this->assertEquals(0.4, $result['relative_weight']);
    }

    public function testCalculateFairValueEqualWeights(): void
    {
        $service = new ValuationService($this->pdo);
        $result = $service->calculateFairValue([
            'dcf_result' => ['fair_value' => 10000],
            'relative_result' => ['fair_value' => 8000],
            'weights' => ['dcf' => 0.5, 'relative' => 0.5],
        ]);
        $this->assertEquals(9000, $result['fair_value']);
    }
}
