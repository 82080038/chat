<?php

declare(strict_types=1);

namespace Platform\Tests\Portfolio;

use PHPUnit\Framework\TestCase;
use Platform\Core\Exceptions\ApiException;
use Platform\Portfolio\PortfolioService;
use Platform\Portfolio\PortfolioServiceInterface;

final class PortfolioServiceTest extends TestCase
{
    public function testServiceImplementsContract(): void
    {
        self::assertTrue(
            is_subclass_of(PortfolioService::class, PortfolioServiceInterface::class)
        );
    }

    public function testCreatePortfolioRejectsMissingFields(): void
    {
        $service = (new \ReflectionClass(PortfolioService::class))
            ->newInstanceWithoutConstructor();

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Required fields are missing');

        $service->createPortfolio(['name' => 'Test Portfolio']);
    }

    public function testOpenPositionRejectsMissingFields(): void
    {
        $service = (new \ReflectionClass(PortfolioService::class))
            ->newInstanceWithoutConstructor();

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Required fields are missing');

        $service->openPosition(['portfolio_id' => 'test-pf']);
    }

    public function testRecordCashTransactionRejectsMissingFields(): void
    {
        $service = (new \ReflectionClass(PortfolioService::class))
            ->newInstanceWithoutConstructor();

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Required fields are missing');

        $service->recordCashTransaction('test-pf', ['currency' => 'IDR']);
    }

    public function testSetPortfolioTargetRejectsMissingFields(): void
    {
        $service = (new \ReflectionClass(PortfolioService::class))
            ->newInstanceWithoutConstructor();

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Required fields are missing');

        $service->setPortfolioTarget('test-pf', ['instrument_id' => 'test-inst']);
    }

    public function testLinkPortfolioAccountRejectsMissingFields(): void
    {
        $service = (new \ReflectionClass(PortfolioService::class))
            ->newInstanceWithoutConstructor();

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Required fields are missing');

        $service->linkPortfolioAccount('test-pf', ['broker_id' => 'test-broker']);
    }
}
