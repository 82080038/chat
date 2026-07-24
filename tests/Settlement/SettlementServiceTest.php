<?php

declare(strict_types=1);

namespace Platform\Tests\Settlement;

use PHPUnit\Framework\TestCase;
use Platform\Core\Exceptions\ApiException;
use Platform\Settlement\SettlementService;
use Platform\Settlement\SettlementServiceInterface;

final class SettlementServiceTest extends TestCase
{
    public function testServiceImplementsContract(): void
    {
        self::assertTrue(
            is_subclass_of(SettlementService::class, SettlementServiceInterface::class)
        );
    }

    public function testCreateSettlementRejectsMissingFields(): void
    {
        $service = (new \ReflectionClass(SettlementService::class))
            ->newInstanceWithoutConstructor();

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Required fields are missing');

        $service->createSettlement(['execution_id' => 'test-exe']);
    }

    public function testCreateReconciliationRejectsMissingFields(): void
    {
        $service = (new \ReflectionClass(SettlementService::class))
            ->newInstanceWithoutConstructor();

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Required fields are missing');

        $service->createReconciliation(['portfolio_id' => 'test-pf']);
    }
}
