<?php

declare(strict_types=1);

namespace Platform\Tests\Analytics;

use PHPUnit\Framework\TestCase;
use Platform\Analytics\AnalyticsService;
use Platform\Analytics\AnalyticsServiceInterface;
use Platform\Core\Exceptions\ApiException;

final class AnalyticsServiceTest extends TestCase
{
    public function testServiceImplementsContract(): void
    {
        self::assertTrue(
            is_subclass_of(AnalyticsService::class, AnalyticsServiceInterface::class)
        );
    }

    public function testCreateFeatureRejectsMissingFields(): void
    {
        $service = (new \ReflectionClass(AnalyticsService::class))
            ->newInstanceWithoutConstructor();

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Required fields are missing');

        $service->createFeature(['description' => 'Test feature']);
    }

    public function testCreateSignalRejectsInvalidDirection(): void
    {
        $service = (new \ReflectionClass(AnalyticsService::class))
            ->newInstanceWithoutConstructor();

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Invalid direction');

        $service->createSignal([
            'instrument_id' => 'test-inst',
            'signal_type' => 'MOMENTUM',
            'direction' => 'INVALID',
            'timeframe' => '1D',
        ]);
    }

    public function testCreateRecommendationRejectsInvalidAction(): void
    {
        $service = (new \ReflectionClass(AnalyticsService::class))
            ->newInstanceWithoutConstructor();

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Invalid action');

        $service->createRecommendation([
            'instrument_id' => 'test-inst',
            'action' => 'INVALID',
        ]);
    }

    public function testCreateBacktestRejectsMissingFields(): void
    {
        $service = (new \ReflectionClass(AnalyticsService::class))
            ->newInstanceWithoutConstructor();

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Required fields are missing');

        $service->createBacktest(['strategy_name' => 'test_strategy']);
    }

    public function testCreateModelRejectsMissingFields(): void
    {
        $service = (new \ReflectionClass(AnalyticsService::class))
            ->newInstanceWithoutConstructor();

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Required fields are missing');

        $service->createModel(['model_name' => 'test_model']);
    }

    public function testCreateScoreRejectsMissingFields(): void
    {
        $service = (new \ReflectionClass(AnalyticsService::class))
            ->newInstanceWithoutConstructor();

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Required fields are missing');

        $service->createScore(['instrument_id' => 'test-inst']);
    }
}
