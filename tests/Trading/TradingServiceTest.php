<?php

declare(strict_types=1);

namespace Platform\Tests\Trading;

use PHPUnit\Framework\TestCase;
use Platform\Core\Exceptions\ApiException;
use Platform\Trading\TradingService;
use Platform\Trading\TradingServiceInterface;

final class TradingServiceTest extends TestCase
{
    public function testServiceImplementsContract(): void
    {
        self::assertTrue(
            is_subclass_of(TradingService::class, TradingServiceInterface::class)
        );
    }

    public function testCreateBrokerRejectsMissingFields(): void
    {
        $service = (new \ReflectionClass(TradingService::class))
            ->newInstanceWithoutConstructor();

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Required fields are missing');

        $service->createBroker(['name' => 'Test Broker']);
    }

    public function testCreateDecisionRejectsInvalidAction(): void
    {
        $service = (new \ReflectionClass(TradingService::class))
            ->newInstanceWithoutConstructor();

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Invalid action');

        $service->createDecision([
            'portfolio_id' => 'test-pf',
            'instrument_id' => 'test-inst',
            'action' => 'INVALID',
        ]);
    }

    public function testCreateOrderIntentRejectsInvalidSide(): void
    {
        $service = (new \ReflectionClass(TradingService::class))
            ->newInstanceWithoutConstructor();

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Invalid side');

        $service->createOrderIntent([
            'decision_id' => 'test-dec',
            'portfolio_id' => 'test-pf',
            'instrument_id' => 'test-inst',
            'side' => 'INVALID',
            'target_quantity' => 1000,
        ]);
    }

    public function testCreateDecisionRejectsMissingFields(): void
    {
        $service = (new \ReflectionClass(TradingService::class))
            ->newInstanceWithoutConstructor();

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Required fields are missing');

        $service->createDecision(['portfolio_id' => 'test-pf']);
    }

    public function testRecordExecutionRejectsMissingFields(): void
    {
        $service = (new \ReflectionClass(TradingService::class))
            ->newInstanceWithoutConstructor();

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Required fields are missing');

        $service->recordExecution(['order_id' => 'test-ord']);
    }
}
