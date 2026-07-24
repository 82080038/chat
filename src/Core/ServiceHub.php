<?php

declare(strict_types=1);

namespace Platform\Core;

use Platform\Core\EventBus\EventBus;
use Platform\Governance\GovernanceService;
use Platform\Risk\RiskService;
use Platform\Settlement\SettlementService;

/**
 * Provides cross-service wiring via Application service registry.
 * Services can optionally call other services for pre-trade checks,
 * auto-settlement, and audit logging.
 */
final class ServiceHub
{
    private static ?ServiceHub $instance = null;

    private ?string $correlationId = null;

    private function __construct()
    {
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getRiskService(): ?RiskService
    {
        $svc = Application::getInstance()->getService('risk');
        return $svc instanceof RiskService ? $svc : null;
    }

    public function getSettlementService(): ?SettlementService
    {
        $svc = Application::getInstance()->getService('settlement');
        return $svc instanceof SettlementService ? $svc : null;
    }

    public function getGovernanceService(): ?GovernanceService
    {
        $svc = Application::getInstance()->getService('governance');
        return $svc instanceof GovernanceService ? $svc : null;
    }

    public function setCorrelationId(?string $id): void
    {
        $this->correlationId = $id;
    }

    public function getCorrelationId(): ?string
    {
        return $this->correlationId;
    }

    /**
     * Pre-trade risk check. Returns violations array (empty if passed).
     *
     * @param string $portfolioId
     * @param array<string, mixed> $proposedTrade
     * @return array{passed: bool, violations: array<int, array<string, mixed>>}
     */
    public function checkPreTradeRisk(
        string $portfolioId,
        array $proposedTrade
    ): array {
        $risk = $this->getRiskService();
        if ($risk === null) {
            return ['passed' => true, 'violations' => []];
        }
        return $risk->checkLimits($portfolioId, $proposedTrade);
    }

    /**
     * Auto-create settlement record from execution data.
     *
     * @param array<string, mixed> $execution
     * @return array<string, mixed>|null
     */
    public function autoCreateSettlement(array $execution): ?array
    {
        $settlement = $this->getSettlementService();
        if ($settlement === null) {
            return null;
        }
        $tradeDate = date('Y-m-d', strtotime($execution['executed_at'] ?? 'now'));
        $settlementDate = date(
            'Y-m-d',
            strtotime('+2 weekdays', strtotime($tradeDate))
        );
        $fillValue = (float) $execution['fill_value'];
        $commission = (float) ($execution['commission'] ?? 0);
        $fees = (float) ($execution['fees'] ?? 0);
        $taxes = (float) ($execution['taxes'] ?? 0);
        $netAmount = $fillValue + $commission + $fees + $taxes;
        return $settlement->createSettlement([
            'execution_id' => $execution['execution_id'],
            'portfolio_id' => $execution['portfolio_id'] ?? '',
            'instrument_id' => $execution['instrument_id'],
            'settlement_type' => 'T_PLUS_2',
            'trade_date' => $tradeDate,
            'settlement_date' => $settlementDate,
            'quantity' => $execution['fill_quantity'],
            'price' => $execution['fill_price'],
            'gross_amount' => $fillValue,
            'commission' => $commission,
            'fees' => $fees,
            'taxes' => $taxes,
            'net_amount' => $netAmount,
            'currency' => $execution['currency'] ?? 'IDR',
        ]);
    }

    /**
     * Audit log a service action.
     *
     * @param string $action
     * @param string $entityType
     * @param string $entityId
     * @param array<string, mixed>|null $oldValues
     * @param array<string, mixed>|null $newValues
     */
    public function audit(
        string $action,
        string $entityType,
        string $entityId,
        ?array $oldValues = null,
        ?array $newValues = null
    ): void {
        $gov = $this->getGovernanceService();
        if ($gov === null) {
            return;
        }
        $gov->auditLog([
            'actor_type' => 'OWNER',
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'correlation_id' => $this->correlationId,
        ]);

        // Also emit event to RabbitMQ (fail-safe)
        EventBus::getInstance()->emit(
            strtolower($entityType) . '.' . strtolower($action),
            [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'action' => $action,
            ],
            $this->correlationId
        );
    }
}
