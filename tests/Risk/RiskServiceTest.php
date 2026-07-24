<?php

declare(strict_types=1);

namespace Platform\Tests\Risk;

use PHPUnit\Framework\TestCase;
use Platform\Core\Exceptions\ApiException;
use Platform\Risk\RiskService;
use Platform\Risk\RiskServiceInterface;

final class RiskServiceTest extends TestCase
{
    public function testServiceImplementsContract(): void
    {
        self::assertTrue(
            is_subclass_of(RiskService::class, RiskServiceInterface::class)
        );
    }

    public function testCreateRiskProfileRejectsMissingFields(): void
    {
        $service = (new \ReflectionClass(RiskService::class))
            ->newInstanceWithoutConstructor();

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Required fields are missing');

        $service->createRiskProfile(['name' => 'Test Profile']);
    }

    public function testCreateRiskProfileRejectsInvalidTolerance(): void
    {
        $service = (new \ReflectionClass(RiskService::class))
            ->newInstanceWithoutConstructor();

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Invalid risk tolerance');

        $service->createRiskProfile([
            'name' => 'Test Profile',
            'risk_tolerance' => 'INVALID',
        ]);
    }

    public function testSetRiskLimitRejectsMissingFields(): void
    {
        $service = (new \ReflectionClass(RiskService::class))
            ->newInstanceWithoutConstructor();

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Required fields are missing');

        $service->setRiskLimit('test-pf', ['limit_type' => 'VAR']);
    }

    public function testTriggerAssessmentRejectsMissingFields(): void
    {
        $service = (new \ReflectionClass(RiskService::class))
            ->newInstanceWithoutConstructor();

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Required fields are missing');

        $service->triggerAssessment('test-pf', ['var_95' => 5000000]);
    }
}
