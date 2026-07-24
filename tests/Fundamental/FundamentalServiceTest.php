<?php

declare(strict_types=1);

namespace Platform\Tests\Fundamental;

use PHPUnit\Framework\TestCase;
use Platform\Core\Exceptions\ApiException;
use Platform\Fundamental\FundamentalService;
use Platform\Fundamental\FundamentalServiceInterface;

final class FundamentalServiceTest extends TestCase
{
    public function testServiceImplementsContract(): void
    {
        self::assertTrue(
            is_subclass_of(FundamentalService::class, FundamentalServiceInterface::class)
        );
    }

    public function testCreateStatementRejectsMissingFields(): void
    {
        $service = (new \ReflectionClass(FundamentalService::class))
            ->newInstanceWithoutConstructor();

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Required fields are missing');

        $service->createFinancialStatement(['issuer_id' => 'test-issuer']);
    }

    public function testCreateStatementRejectsInvalidType(): void
    {
        $service = (new \ReflectionClass(FundamentalService::class))
            ->newInstanceWithoutConstructor();

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Invalid statement type');

        $service->createFinancialStatement([
            'issuer_id' => 'test-issuer',
            'statement_type' => 'INVALID',
            'fiscal_period_type' => 'Q1',
            'fiscal_year' => 2026,
            'period_start' => '2026-01-01',
            'period_end' => '2026-03-31',
            'currency' => 'IDR',
            'source' => 'MANUAL',
        ]);
    }

    public function testCreateStatementRejectsInvalidPeriodType(): void
    {
        $service = (new \ReflectionClass(FundamentalService::class))
            ->newInstanceWithoutConstructor();

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Invalid fiscal period type');

        $service->createFinancialStatement([
            'issuer_id' => 'test-issuer',
            'statement_type' => 'INCOME',
            'fiscal_period_type' => 'INVALID',
            'fiscal_year' => 2026,
            'period_start' => '2026-01-01',
            'period_end' => '2026-03-31',
            'currency' => 'IDR',
            'source' => 'MANUAL',
        ]);
    }

    public function testCreateMetricRejectsMissingFields(): void
    {
        $service = (new \ReflectionClass(FundamentalService::class))
            ->newInstanceWithoutConstructor();

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Required fields are missing');

        $service->createFinancialMetric(['issuer_id' => 'test-issuer']);
    }

    public function testCreateIndicatorRejectsMissingFields(): void
    {
        $service = (new \ReflectionClass(FundamentalService::class))
            ->newInstanceWithoutConstructor();

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Required fields are missing');

        $service->createEconomicIndicator(['country' => 'ID']);
    }

    public function testCreateNewsRejectsMissingFields(): void
    {
        $service = (new \ReflectionClass(FundamentalService::class))
            ->newInstanceWithoutConstructor();

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Required fields are missing');

        $service->createNewsItem(['title' => 'Test News']);
    }
}
