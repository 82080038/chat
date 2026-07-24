<?php

declare(strict_types=1);

namespace Platform\Tests\Governance;

use PHPUnit\Framework\TestCase;
use Platform\Governance\GovernanceService;

final class GovernanceServiceTest extends TestCase
{
    public function testServiceImplementsInterface(): void
    {
        $service = new GovernanceService();
        $this->assertInstanceOf(\Platform\Governance\GovernanceServiceInterface::class, $service);
    }

    public function testUuidGeneration(): void
    {
        $reflection = new \ReflectionClass(GovernanceService::class);
        $method = $reflection->getMethod('uuid');
        $method->setAccessible(true);
        $uuid = $method->invoke(new GovernanceService());
        $this->assertNotEmpty($uuid);
        $this->assertEquals(36, strlen($uuid));
    }
}
