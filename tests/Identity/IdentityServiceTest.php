<?php

declare(strict_types=1);

namespace Platform\Tests\Identity;

use PHPUnit\Framework\TestCase;
use Platform\Core\Exceptions\ApiException;
use Platform\Identity\IdentityService;
use Platform\Identity\IdentityServiceInterface;

final class IdentityServiceTest extends TestCase
{
    public function testServiceImplementsContract(): void
    {
        self::assertTrue(is_subclass_of(IdentityService::class, IdentityServiceInterface::class));
    }

    public function testSetupRejectsWeakPasswordBeforeDatabaseWrite(): void
    {
        $service = (new \ReflectionClass(IdentityService::class))->newInstanceWithoutConstructor();

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Owner setup data is invalid');

        $service->setupOwner([
            'email' => 'owner@example.com',
            'password' => 'weak',
        ]);
    }

    public function testPasswordPolicyAcceptsStrongPassword(): void
    {
        $reflection = new \ReflectionClass(IdentityService::class);
        $method = $reflection->getMethod('isValidPassword');
        $method->setAccessible(true);
        $service = $reflection->newInstanceWithoutConstructor();

        self::assertTrue($method->invoke($service, 'SecurePass123!'));
        self::assertFalse($method->invoke($service, 'alllowercase123'));
    }
}
