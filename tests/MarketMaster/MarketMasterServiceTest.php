<?php

declare(strict_types=1);

namespace Platform\Tests\MarketMaster;

use PHPUnit\Framework\TestCase;
use Platform\Core\Exceptions\ApiException;
use Platform\MarketMaster\MarketMasterService;
use Platform\MarketMaster\MarketMasterServiceInterface;

final class MarketMasterServiceTest extends TestCase
{
    public function testServiceImplementsContract(): void
    {
        self::assertTrue(
            is_subclass_of(MarketMasterService::class, MarketMasterServiceInterface::class)
        );
    }

    public function testCreateExchangeRejectsMissingFields(): void
    {
        $service = (new \ReflectionClass(MarketMasterService::class))
            ->newInstanceWithoutConstructor();

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Required fields are missing');

        $service->createExchange(['name' => 'Test Exchange']);
    }

    public function testCreateInstrumentRejectsMissingFields(): void
    {
        $service = (new \ReflectionClass(MarketMasterService::class))
            ->newInstanceWithoutConstructor();

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Required fields are missing');

        $service->createInstrument(['asset_class' => 'EQUITY']);
    }

    public function testCreateIssuerRejectsMissingFields(): void
    {
        $service = (new \ReflectionClass(MarketMasterService::class))
            ->newInstanceWithoutConstructor();

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Required fields are missing');

        $service->createIssuer(['legal_name' => 'PT Test Tbk']);
    }

    public function testCreateListingRejectsMissingFields(): void
    {
        $service = (new \ReflectionClass(MarketMasterService::class))
            ->newInstanceWithoutConstructor();

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Required fields are missing');

        $service->createListing(['ticker' => 'TEST']);
    }

    public function testCreateCorporateActionRejectsMissingFields(): void
    {
        $service = (new \ReflectionClass(MarketMasterService::class))
            ->newInstanceWithoutConstructor();

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Required fields are missing');

        $service->createCorporateAction(['action_type' => 'SPLIT']);
    }
}
