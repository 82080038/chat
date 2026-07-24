<?php

declare(strict_types=1);

namespace Platform\Tests\Governance;

use PHPUnit\Framework\TestCase;
use Platform\Governance\GovernanceService;

final class GovernanceServiceTest extends TestCase
{
    public function testServiceImplementsInterface(): void
    {
        $this->assertTrue(is_subclass_of(
            GovernanceService::class,
            \Platform\Governance\GovernanceServiceInterface::class
        ));
    }

    public function testUuidGeneration(): void
    {
        $reflection = new \ReflectionClass(GovernanceService::class);
        $method = $reflection->getMethod('uuid');
        $method->setAccessible(true);
        $uuid = $method->invoke($reflection->newInstanceWithoutConstructor());
        $this->assertNotEmpty($uuid);
        $this->assertEquals(36, strlen($uuid));
    }
}
