<?php

declare(strict_types=1);

namespace Platform\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Platform\Governance\GovernanceService;
use Platform\Trading\TradingService;
use Platform\Settlement\SettlementService;

/**
 * Integration tests using a mock PDO that simulates database operations.
 * Tests actual CRUD logic without requiring MySQL or SQLite.
 */
final class IntegrationTest extends TestCase
{
    private MockPdo $pdo;

    protected function setUp(): void
    {
        $this->pdo = new MockPdo();
    }

    // ─── Governance Integration Tests ────────────────────────────────────

    public function testGovernanceAuditLogAndRetrieve(): void
    {
        $service = new GovernanceService($this->pdo);
        $service->auditLog([
            'actor_type' => 'OWNER',
            'action' => 'TEST_ACTION',
            'entity_type' => 'TEST',
            'entity_id' => 'ent-001',
        ]);
        $result = $service->listAuditLogs([], 1, 50);
        $this->assertCount(1, $result['data']);
        $this->assertSame('TEST_ACTION', $result['data'][0]['action']);
    }

    public function testGovernanceApprovalLifecycle(): void
    {
        $service = new GovernanceService($this->pdo);
        $approval = $service->requestApproval(
            'ORDER',
            'ord-001',
            'ORDER'
        );
        $this->assertSame('PENDING', $approval['status']);
        $approved = $service->approve($approval['approval_id']);
        $this->assertSame('APPROVED', $approved['status']);
    }

    public function testGovernancePolicyVersioning(): void
    {
        $service = new GovernanceService($this->pdo);
        $policy = $service->createPolicy([
            'policy_type' => 'TRADING',
            'name' => 'Test Policy',
            'rules' => ['max_position' => 0.1],
        ]);
        $this->assertSame(1, (int) $policy['version']);
        $updated = $service->updatePolicy($policy['policy_id'], [
            'name' => 'Test Policy v2',
        ]);
        $this->assertSame(2, (int) $updated['version']);
        $old = $service->getPolicy($policy['policy_id']);
        $this->assertSame('SUPERSEDED', $old['status']);
    }

    public function testGovernanceWorkflowCancel(): void
    {
        $service = new GovernanceService($this->pdo);
        $wf = $service->startWorkflow('APPROVAL', 'ORDER', 'ord-001');
        $this->assertSame('PENDING', $wf['status']);
        $cancelled = $service->cancelWorkflow(
            $wf['workflow_id'],
            'No longer needed'
        );
        $this->assertSame('CANCELLED', $cancelled['status']);
    }

    // ─── Trading Integration Tests ───────────────────────────────────────

    public function testTradingBrokerCRUD(): void
    {
        $service = new TradingService($this->pdo);
        $broker = $service->createBroker([
            'name' => 'Test Broker',
            'country' => 'id',
        ]);
        $this->assertSame('Test Broker', $broker['name']);
        $this->assertSame('ID', $broker['country']);
        $fetched = $service->getBroker($broker['broker_id']);
        $this->assertNotNull($fetched);
        $updated = $service->updateBroker(
            $broker['broker_id'],
            ['status' => 'INACTIVE']
        );
        $this->assertSame('INACTIVE', $updated['status']);
    }

    public function testTradingDecisionLifecycle(): void
    {
        $service = new TradingService($this->pdo);
        $decision = $service->createDecision([
            'portfolio_id' => 'pf-001',
            'instrument_id' => 'inst-001',
            'action' => 'BUY',
            'intended_quantity' => 1000,
            'intended_price' => 5000,
        ]);
        $this->assertSame('PENDING', $decision['status']);
        $approved = $service->approveDecision($decision['decision_id']);
        $this->assertSame('APPROVED', $approved['status']);
    }

    public function testTradingDecisionOverride(): void
    {
        $service = new TradingService($this->pdo);
        $decision = $service->createDecision([
            'portfolio_id' => 'pf-001',
            'instrument_id' => 'inst-001',
            'action' => 'SELL',
        ]);
        $overridden = $service->overrideDecision(
            $decision['decision_id'],
            'Manual override: market dip'
        );
        $this->assertSame(
            'MANUAL_OVERRIDE',
            $overridden['policy_result']
        );
        $this->assertSame(1, (int) $overridden['human_override']);
    }

    public function testTradingOrderIntentApproval(): void
    {
        $service = new TradingService($this->pdo);
        $intent = $service->createOrderIntent([
            'decision_id' => 'dec-001',
            'portfolio_id' => 'pf-001',
            'instrument_id' => 'inst-001',
            'side' => 'BUY',
            'target_quantity' => 500,
        ]);
        $this->assertSame('DRAFT', $intent['status']);
        $approved = $service->approveOrderIntent(
            $intent['order_intent_id']
        );
        $this->assertSame('APPROVED', $approved['status']);
    }

    public function testTradingExecutionUpdatesOrder(): void
    {
        $service = new TradingService($this->pdo);
        $intent = $service->createOrderIntent([
            'decision_id' => 'dec-001',
            'portfolio_id' => 'pf-001',
            'instrument_id' => 'inst-001',
            'side' => 'BUY',
            'target_quantity' => 1000,
        ]);
        $order = $service->submitOrder([
            'order_intent_id' => $intent['order_intent_id'],
            'account_id' => 'acc-001',
            'quantity' => 1000,
        ]);
        $this->assertSame('SUBMITTED', $order['status']);
        $this->assertSame(0.0, (float) $order['filled_quantity']);
        $exec = $service->recordExecution([
            'order_id' => $order['order_id'],
            'instrument_id' => 'inst-001',
            'fill_quantity' => 1000,
            'fill_price' => 4975,
            'currency' => 'idr',
        ]);
        $this->assertSame('PENDING_SETTLEMENT', $exec['status']);
        $updated = $service->getOrder($order['order_id']);
        $this->assertSame('FILLED', $updated['status']);
        $this->assertSame(
            1000.0,
            (float) $updated['filled_quantity']
        );
    }

    // ─── Settlement Integration Tests ────────────────────────────────────

    public function testSettlementCreateAndProcess(): void
    {
        $service = new SettlementService($this->pdo);
        $settlement = $service->createSettlement([
            'execution_id' => 'exe-001',
            'portfolio_id' => 'pf-001',
            'instrument_id' => 'inst-001',
            'trade_date' => '2026-07-24',
            'settlement_date' => '2026-07-26',
            'quantity' => 1000,
            'price' => 5000,
            'gross_amount' => 5000000,
            'net_amount' => 4975000,
            'currency' => 'idr',
        ]);
        $this->assertSame('PENDING', $settlement['status']);
        $processed = $service->processSettlement(
            $settlement['settlement_id']
        );
        $this->assertSame('SETTLED', $processed['status']);
    }

    public function testSettlementReconciliationResolve(): void
    {
        $service = new SettlementService($this->pdo);
        $recon = $service->createReconciliation([
            'portfolio_id' => 'pf-001',
            'reconciliation_type' => 'POSITION',
            'reconciliation_date' => '2026-07-24',
            'internal_value' => 1000,
            'broker_value' => 995,
        ]);
        $this->assertSame('PENDING', $recon['status']);
        $this->assertEquals(-5.0, (float) $recon['discrepancy']);
        $resolved = $service->resolveReconciliation(
            $recon['reconciliation_id'],
            'Adjusted to match broker'
        );
        $this->assertSame('RESOLVED', $resolved['status']);
        $this->assertSame(
            'Adjusted to match broker',
            $resolved['resolution']
        );
    }

    public function testSettlementPendingFilter(): void
    {
        $service = new SettlementService($this->pdo);
        $service->createSettlement([
            'execution_id' => 'exe-001',
            'portfolio_id' => 'pf-001',
            'instrument_id' => 'inst-001',
            'trade_date' => '2026-07-24',
            'settlement_date' => '2026-07-26',
            'quantity' => 1000,
            'price' => 5000,
            'gross_amount' => 5000000,
            'net_amount' => 4975000,
            'currency' => 'idr',
        ]);
        $service->createSettlement([
            'execution_id' => 'exe-002',
            'portfolio_id' => 'pf-001',
            'instrument_id' => 'inst-002',
            'trade_date' => '2026-07-24',
            'settlement_date' => '2026-07-26',
            'quantity' => 500,
            'price' => 3000,
            'gross_amount' => 1500000,
            'net_amount' => 1490000,
            'currency' => 'idr',
            'status' => 'SETTLED',
        ]);
        $pending = $service->getPendingSettlements('pf-001');
        $this->assertCount(1, $pending);
        $this->assertSame('PENDING', $pending[0]['status']);
    }
}
