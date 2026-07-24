<?php

declare(strict_types=1);

namespace Platform\Tests\Config;

use PHPUnit\Framework\TestCase;
use Platform\Config\ConfigService;
use Platform\Config\ConfigServiceInterface;
use Platform\Core\Cache\CacheStoreInterface;
use Platform\Core\Exceptions\ApiException;

final class ConfigServiceTest extends TestCase
{
    public function testServiceImplementsContract(): void
    {
        self::assertTrue(is_subclass_of(ConfigService::class, ConfigServiceInterface::class));
    }

    public function testCreateConfigurationRejectsMissingFields(): void
    {
        $service = (new \ReflectionClass(ConfigService::class))->newInstanceWithoutConstructor();

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Required fields are missing');

        $service->createConfiguration(['config_key' => 'example.key']);
    }

    public function testFeatureFlagCacheContract(): void
    {
        $cache = new InMemoryCacheStore();
        $cache->set('feature', 'enabled', 60);
        self::assertSame('enabled', $cache->get('feature'));
        $cache->delete('feature');
        self::assertNull($cache->get('feature'));
    }
}
