<?php

declare(strict_types=1);

namespace Platform\Tests\Alert;

use PHPUnit\Framework\TestCase;
use Platform\Alert\AlertService;
use Platform\Tests\Integration\MockPdo;

final class AlertServiceTest extends TestCase
{
    private MockPdo $pdo;

    protected function setUp(): void
    {
        $this->pdo = new MockPdo();
    }

    public function testServiceImplementsInterface(): void
    {
        $this->assertTrue(is_subclass_of(
            AlertService::class,
            \Platform\Alert\AlertServiceInterface::class
        ));
    }

    public function testCreateAlertPersistsRecord(): void
    {
        $service = new AlertService($this->pdo);
        $result = $service->createAlert([
            'alert_type' => 'PRICE',
            'condition_op' => 'GT',
            'threshold' => 8000,
            'instrument_id' => 'inst-001',
            'description' => 'BBCA above 8000',
        ]);
        $this->assertSame('PRICE', $result['alert_type']);
        $this->assertSame('GT', $result['condition_op']);
        $this->assertSame('inst-001', $result['instrument_id']);
    }

    public function testCreateAlertMissingFieldThrows(): void
    {
        $service = new AlertService($this->pdo);
        $this->expectException(\Platform\Core\Exceptions\ApiException::class);
        $service->createAlert([
            'alert_type' => 'PRICE',
        ]);
    }

    public function testCreateAlertInvalidTypeThrows(): void
    {
        $service = new AlertService($this->pdo);
        $this->expectException(\Platform\Core\Exceptions\ApiException::class);
        $service->createAlert([
            'alert_type' => 'INVALID',
            'condition_op' => 'GT',
            'threshold' => 100,
        ]);
    }

    public function testListAlertsPaginated(): void
    {
        $service = new AlertService($this->pdo);
        $service->createAlert([
            'alert_type' => 'PRICE',
            'condition_op' => 'GT',
            'threshold' => 8000,
        ]);
        $result = $service->listAlerts([], 1, 50);
        $this->assertCount(1, $result['data']);
        $this->assertSame(1, $result['meta']['total']);
    }

    public function testListAlertsFilterByType(): void
    {
        $service = new AlertService($this->pdo);
        $service->createAlert([
            'alert_type' => 'PRICE',
            'condition_op' => 'GT',
            'threshold' => 8000,
        ]);
        $service->createAlert([
            'alert_type' => 'RISK',
            'condition_op' => 'GTE',
            'threshold' => 0.05,
        ]);
        $result = $service->listAlerts(['alert_type' => 'RISK'], 1, 50);
        $this->assertCount(1, $result['data']);
        $this->assertSame('RISK', $result['data'][0]['alert_type']);
    }

    public function testDeleteAlertDeactivates(): void
    {
        $service = new AlertService($this->pdo);
        $alert = $service->createAlert([
            'alert_type' => 'PRICE',
            'condition_op' => 'GT',
            'threshold' => 8000,
        ]);
        $result = $service->deleteAlert($alert['alert_id']);
        $this->assertTrue($result['deleted']);
    }

    public function testTriggerAlertCreatesNotification(): void
    {
        $service = new AlertService($this->pdo);
        $alert = $service->createAlert([
            'alert_type' => 'PRICE',
            'condition_op' => 'GT',
            'threshold' => 8000,
        ]);
        $result = $service->triggerAlert($alert['alert_id'], [
            'value' => 8100,
        ]);
        $this->assertSame('PENDING', $result['status']);
        $this->assertSame(8100.0, $result['trigger_value']);
    }

    public function testTriggerAlertNotFoundThrows(): void
    {
        $service = new AlertService($this->pdo);
        $this->expectException(\Platform\Core\Exceptions\ApiException::class);
        $service->triggerAlert('nonexistent', ['value' => 100]);
    }

    public function testListNotifications(): void
    {
        $service = new AlertService($this->pdo);
        $alert = $service->createAlert([
            'alert_type' => 'PRICE',
            'condition_op' => 'GT',
            'threshold' => 8000,
        ]);
        $service->triggerAlert($alert['alert_id'], ['value' => 8100]);
        $result = $service->listNotifications([], 1, 50);
        $this->assertCount(1, $result['data']);
        $this->assertSame('PENDING', $result['data'][0]['status']);
    }

    public function testAcknowledgeNotification(): void
    {
        $service = new AlertService($this->pdo);
        $alert = $service->createAlert([
            'alert_type' => 'PRICE',
            'condition_op' => 'GT',
            'threshold' => 8000,
        ]);
        $triggered = $service->triggerAlert($alert['alert_id'], ['value' => 8100]);
        $result = $service->acknowledgeNotification(
            $triggered['notification_id']
        );
        $this->assertSame('ACKNOWLEDGED', $result['status']);
    }

    public function testCheckPriceAlertTriggersMatching(): void
    {
        $service = new AlertService($this->pdo);
        $service->createAlert([
            'alert_type' => 'PRICE',
            'condition_op' => 'GT',
            'threshold' => 8000,
            'instrument_id' => 'inst-001',
        ]);
        $service->createAlert([
            'alert_type' => 'PRICE',
            'condition_op' => 'LT',
            'threshold' => 5000,
            'instrument_id' => 'inst-001',
        ]);
        $result = $service->checkPriceAlert('inst-001', 8100);
        $this->assertSame(2, $result['alerts_checked']);
        $this->assertSame(1, $result['alerts_triggered']);
    }

    public function testCheckPriceAlertNoMatch(): void
    {
        $service = new AlertService($this->pdo);
        $service->createAlert([
            'alert_type' => 'PRICE',
            'condition_op' => 'GT',
            'threshold' => 9000,
            'instrument_id' => 'inst-001',
        ]);
        $result = $service->checkPriceAlert('inst-001', 8100);
        $this->assertSame(0, $result['alerts_triggered']);
    }
}
