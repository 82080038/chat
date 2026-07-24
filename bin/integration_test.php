<?php

/**
 * Integration test runner — tests new features against live database.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Platform\Core\Application;
use Platform\Core\Data\DataQualityEngine;
use Platform\Core\Data\RetentionJob;
use Platform\Core\Analytics\MarketFactorMatrix;
use Platform\Core\Analytics\ExplainableAI;
use Platform\Identity\IdentityService;
use Platform\Trading\TradingService;
use Platform\Analytics\AnalyticsService;
use Platform\Governance\GovernanceService;
use Platform\DataIngestion\DataIngestionService;
use Platform\Fundamental\FundamentalService;
use Platform\Config\ConfigService;
use Platform\Microstructure\MicrostructureService;

$app = Application::getInstance();

// Register services
$db = \Platform\Core\Database\MySqlConnection::getInstance();
$identity = new IdentityService($db);
$app->registerService('identity', $identity);
$trading = new TradingService($db);
$app->registerService('trading', $trading);
$analytics = new AnalyticsService($db);
$app->registerService('analytics', $analytics);
$governance = new GovernanceService($db);
$app->registerService('governance', $governance);
$dataIngestion = new DataIngestionService($db);
$app->registerService('data_ingestion', $dataIngestion);
$fundamental = new FundamentalService($db);
$app->registerService('fundamental', $fundamental);
$config = new ConfigService($db);
$app->registerService('config', $config);
$microstructure = new MicrostructureService($db);
$app->registerService('microstructure', $microstructure);

$pass = 0;
$fail = 0;

function assert_true(bool $cond, string $label): void
{
    global $pass, $fail;
    if ($cond) {
        echo "  ✓ PASS: {$label}\n";
        $pass++;
    } else {
        echo "  ✗ FAIL: {$label}\n";
        $fail++;
    }
}

function section(string $name): void
{
    echo "\n── {$name} ──\n";
}

// ─── Test 1: Kill Switch ─────────────────────────────────────────────
section('Kill Switch');

// Reset any leftover state from previous runs
if ($identity->isKillSwitchActive()) {
    $identity->deactivateKillSwitch();
}

$initialActive = $identity->isKillSwitchActive();
assert_true(!$initialActive, 'Kill switch inactive initially');

$activateResult = $identity->activateKillSwitch('Integration test emergency');
assert_true($activateResult['status'] === 'LOCKED', 'Kill switch activates → status LOCKED');
assert_true($identity->isKillSwitchActive(), 'Kill switch is active after activation');

// Verify sessions revoked
$stmt = $db->prepare('SELECT COUNT(*) FROM identity.owner_session WHERE revoked_at IS NULL');
$stmt->execute();
$activeSessions = (int) $stmt->fetchColumn();
assert_true($activeSessions === 0, 'All sessions revoked after kill switch');

$deactivateResult = $identity->deactivateKillSwitch();
assert_true($deactivateResult['status'] === 'ACTIVE', 'Kill switch deactivates → status ACTIVE');
assert_true(!$identity->isKillSwitchActive(), 'Kill switch inactive after deactivation');

// ─── Test 2: Audit Log Immutability ──────────────────────────────────
section('Audit Log Immutability');

$stmt = $db->query('SELECT COUNT(*) FROM governance.audit_log');
$countBefore = (int) $stmt->fetchColumn();
assert_true($countBefore > 0, "Audit log has {$countBefore} entries");

// Try to INSERT a new audit log (should work)
$governance->auditLog([
    'actor_type' => 'OWNER',
    'action' => 'INTEGRATION_TEST',
    'entity_type' => 'TEST',
    'entity_id' => 'test-001',
]);
$stmt = $db->query('SELECT COUNT(*) FROM governance.audit_log');
$countAfter = (int) $stmt->fetchColumn();
assert_true($countAfter === $countBefore + 1, 'New audit log entry inserted');

// Try to UPDATE (should fail due to trigger)
try {
    $db->exec('UPDATE governance.audit_log SET action = "HACKED" WHERE action = "INTEGRATION_TEST" LIMIT 1');
    assert_true(false, 'UPDATE on audit_log should be rejected');
} catch (\PDOException $e) {
    assert_true(
        str_contains($e->getMessage(), 'append-only') || str_contains($e->getMessage(), '45000'),
        'UPDATE on audit_log rejected by trigger'
    );
}

// Try to DELETE (should fail due to trigger)
try {
    $db->exec('SET @audit_purge_mode = NULL');
    $db->exec('DELETE FROM governance.audit_log WHERE action = "INTEGRATION_TEST" LIMIT 1');
    assert_true(false, 'DELETE on audit_log should be rejected');
} catch (\PDOException $e) {
    assert_true(
        str_contains($e->getMessage(), 'append-only') || str_contains($e->getMessage(), '45000'),
        'DELETE on audit_log rejected by trigger'
    );
}

// ─── Test 3: PIT Query (as_of filter) ────────────────────────────────
section('Point-in-Time Query (as_of)');

// Test fundamental service with as_of filter
$fsResult = $fundamental->listFinancialStatements(['as_of' => '2020-01-01'], 1, 10);
assert_true(is_array($fsResult), 'Fundamental listFinancialStatements with as_of returns array');
assert_true(isset($fsResult['data']), 'PIT query returns paginated data structure');

// Test analytics signals with as_of filter
$signalResult = $analytics->listSignals(['as_of' => '2025-01-01'], 1, 10);
assert_true(is_array($signalResult), 'Analytics listSignals with as_of returns array');

// Test recommendations with as_of filter
$recResult = $analytics->listRecommendations(['as_of' => '2025-01-01'], 1, 10);
assert_true(is_array($recResult), 'Analytics listRecommendations with as_of returns array');

// ─── Test 4: Duplicate Order Detection ───────────────────────────────
section('Duplicate Order Detection');

// Get an existing instrument for testing
$stmt = $db->query('SELECT instrument_id FROM market_master.instrument LIMIT 1');
$instrumentId = $stmt->fetchColumn();

if ($instrumentId) {
    $dupResult = $trading->checkDuplicateOrder([
        'instrument_id' => $instrumentId,
        'side' => 'BUY',
        'quantity' => 999999,
        'window_seconds' => 60,
    ]);
    assert_true(is_array($dupResult), 'checkDuplicateOrder returns array');
    assert_true(isset($dupResult['is_duplicate']), 'Result has is_duplicate field');
    assert_true(!$dupResult['is_duplicate'], 'No duplicate for unique quantity 999999');

    // Test with existing order data if available
    $stmt = $db->query('SELECT instrument_id, side, quantity FROM trading.order LIMIT 1');
    $existingOrder = $stmt->fetch();
    if ($existingOrder) {
        $dupResult2 = $trading->checkDuplicateOrder([
            'instrument_id' => $existingOrder['instrument_id'],
            'side' => $existingOrder['side'],
            'quantity' => $existingOrder['quantity'],
            'window_seconds' => 999999999,
        ]);
        assert_true(is_array($dupResult2), 'checkDuplicateOrder with existing data returns array');
    }
} else {
    echo "  ⚠ SKIP: No instruments found for duplicate order test\n";
}

// ─── Test 5: Data Quality Engine ─────────────────────────────────────
section('Data Quality Engine');

// Get an instrument with OHLCV data
$stmt = $db->query('SELECT instrument_id FROM data_ingestion.ohlcv_daily GROUP BY instrument_id LIMIT 1');
$ohlcvInstrument = $stmt->fetchColumn();

if ($ohlcvInstrument) {
    $dqResult = DataQualityEngine::getInstance()->assessOhlcvQuality($ohlcvInstrument);
    assert_true(isset($dqResult['quality_score']), 'Data quality score returned');
    assert_true($dqResult['quality_score'] >= 0 && $dqResult['quality_score'] <= 1, 'Quality score in range 0-1');
    assert_true(in_array($dqResult['trust_level'], ['UNVERIFIED', 'VALIDATED', 'TRUSTED']), 'Trust level is valid');
    assert_true(count($dqResult['checks']) >= 5, 'At least 5 quality checks performed');
} else {
    echo "  ⚠ SKIP: No OHLCV data found for quality test\n";
}

// ─── Test 6: Market Factor Matrix ────────────────────────────────────
section('Market Factor Matrix');

if ($ohlcvInstrument) {
    $factorResult = MarketFactorMatrix::getInstance()->calculateInstrumentFactors($ohlcvInstrument, 60);
    assert_true(isset($factorResult['factors']), 'Factor matrix returns factors');
    if (isset($factorResult['factors']) && count($factorResult['factors']) > 0) {
        assert_true(isset($factorResult['factors']['momentum']), 'Momentum factor calculated');
        assert_true(isset($factorResult['factors']['volatility']), 'Volatility factor calculated');
        assert_true(isset($factorResult['factors']['liquidity']), 'Liquidity factor calculated');
    }
} else {
    echo "  ⚠ SKIP: No OHLCV data for factor matrix test\n";
}

// ─── Test 7: Explainable AI ──────────────────────────────────────────
section('Explainable AI');

$xai = ExplainableAI::getInstance();

$explainRec = $xai->explainRecommendation([
    'action' => 'BUY',
    'confidence' => 0.85,
    'model_version' => 'test-v1',
], [
    'rsi' => 25,
    'macd' => 0.5,
    'momentum' => 0.03,
    'volume' => 1500000,
]);
assert_true(isset($explainRec['explanation']), 'Explain recommendation returns explanation text');
assert_true(str_contains($explainRec['explanation'], 'buy'), 'Explanation mentions buy action');
assert_true(count($explainRec['factors']) > 0, 'Explanation has contributing factors');

$explainSig = $xai->explainSignal([
    'direction' => 'BULLISH',
    'signal_type' => 'RSI_OVERSOLD',
    'strength' => 'STRONG',
], [
    'rsi' => 25,
    'macd' => 0.5,
]);
assert_true(isset($explainSig['explanation']), 'Explain signal returns explanation text');
assert_true(isset($explainSig['indicators']), 'Explain signal returns indicator interpretations');

$shapValues = $xai->calculateShapValues(['rsi' => 0.3, 'macd' => 0.7, 'volume' => 0.8]);
assert_true(count($shapValues) === 3, 'SHAP values calculated for 3 features');

// ─── Test 8: Model Deploy/Retire ─────────────────────────────────────
section('Model Deploy/Retire Governance');

// Get a DRAFT or VALIDATED model if exists
$stmt = $db->query(
    "SELECT model_id, status FROM analytics.model_registry"
    . " WHERE status IN ('DRAFT','VALIDATED') LIMIT 1"
);
$model = $stmt->fetch();

if (!$model) {
    // Try to reuse a RETIRED model by resetting to DRAFT
    $stmt = $db->query(
        "SELECT model_id FROM analytics.model_registry"
        . " WHERE model_name = 'Integration Test Model' LIMIT 1"
    );
    $existingModelId = $stmt->fetchColumn();
    if ($existingModelId) {
        $db->prepare(
            "UPDATE analytics.model_registry"
            . " SET status = 'DRAFT', deployed_at = NULL"
            . " WHERE model_id = :id"
        )->execute([':id' => $existingModelId]);
        $model = ['model_id' => $existingModelId, 'status' => 'DRAFT'];
        echo "  ℹ Reset existing test model to DRAFT\n";
    } else {
        // Create a new test model
        $model = $analytics->createModel([
            'model_name' => 'Integration Test Model',
            'model_version' => 'test-v1.0',
            'model_type' => 'CLASSIFICATION',
            'description' => 'Temporary model for integration testing',
            'metrics' => ['accuracy' => 0.85, 'precision' => 0.82],
            'status' => 'DRAFT',
        ]);
        echo "  ℹ Created test model: {$model['model_id']}\n";
    }
}

if ($model) {
    $modelId = $model['model_id'] ?? $model['model_id'];
    $deployed = $analytics->deployModel($modelId);
    assert_true($deployed['status'] === 'DEPLOYED', 'Model deployed successfully');
    assert_true($deployed['deployed_at'] !== null, 'Deployed_at timestamp set');

    $retired = $analytics->retireModel($modelId, 'Test retirement');
    assert_true($retired['status'] === 'RETIRED', 'Model retired successfully');
} else {
    echo "  ⚠ SKIP: Could not create or find model for deploy/retire test\n";
}

// ─── Test 9: Retention Job (dry run) ─────────────────────────────────
section('Retention Job (Dry Run)');

$job = new RetentionJob(true);
$jobResult = $job->run();
assert_true(is_array($jobResult), 'Retention job returns results array');
assert_true(count($jobResult) > 0, 'Retention job processed categories');
foreach ($jobResult as $cat => $result) {
    if (isset($result['dry_run'])) {
        assert_true($result['dry_run'] === true, "Category {$cat}: dry run mode confirmed");
    }
}

// ─── Test 10: Correlation ID propagation ─────────────────────────────
section('Correlation ID Propagation');

$hub = \Platform\Core\ServiceHub::getInstance();
$testCorrelationId = \Ramsey\Uuid\Uuid::uuid7()->toString();
$hub->setCorrelationId($testCorrelationId);
assert_true($hub->getCorrelationId() === $testCorrelationId, 'Correlation ID set on ServiceHub');

// Audit with correlation ID
$governance->auditLog([
    'actor_type' => 'OWNER',
    'action' => 'CORRELATION_TEST',
    'entity_type' => 'TEST',
    'entity_id' => 'corr-test-001',
    'correlation_id' => $testCorrelationId,
]);

$stmt = $db->prepare(
    'SELECT correlation_id FROM governance.audit_log'
    . ' WHERE action = "CORRELATION_TEST"'
    . ' ORDER BY created_at DESC LIMIT 1'
);
$stmt->execute();
$storedCorrId = $stmt->fetchColumn();
assert_true($storedCorrId === $testCorrelationId, 'Correlation ID stored in audit log');

// ─── Test 11: Market Microstructure ──────────────────────────────────
section('Market Microstructure');

// Capture an order book snapshot
$obResult = $microstructure->captureOrderBook([
    'instrument_id' => '0193a403-2c7b-7134-89aa-1234567890ab',
    'exchange_id' => '0193a403-2c7b-7134-89aa-123456789001',
    'timestamp' => date('Y-m-d H:i:s'),
    'bid_price_1' => 9990,
    'bid_volume_1' => 10000,
    'bid_price_2' => 9980,
    'bid_volume_2' => 5000,
    'ask_price_1' => 10010,
    'ask_volume_1' => 8000,
    'ask_price_2' => 10020,
    'ask_volume_2' => 3000,
]);
assert_true(isset($obResult['snapshot_id']), 'Order book snapshot captured');
assert_true($obResult['mid_price'] > 0, 'Mid price calculated');
assert_true($obResult['spread'] > 0, 'Spread calculated');
assert_true($obResult['spread_bps'] > 0, 'Spread in bps calculated');
assert_true($obResult['total_bid_volume'] > 0, 'Total bid volume aggregated');
assert_true($obResult['total_ask_volume'] > 0, 'Total ask volume aggregated');

// Get latest order book
$latestOb = $microstructure->getLatestOrderBook(
    '0193a403-2c7b-7134-89aa-1234567890ab'
);
assert_true($latestOb !== null, 'Latest order book retrieved');

// Calculate spread analysis
$spreadResult = $microstructure->calculateSpreadAnalysis(
    '0193a403-2c7b-7134-89aa-1234567890ab',
    30
);
assert_true(isset($spreadResult['liquidity_regime']), 'Spread analysis returns regime');
assert_true(
    in_array($spreadResult['liquidity_regime'], ['NORMAL', 'THIN', 'STRESS']),
    'Liquidity regime is valid enum'
);

// Calculate market impact
$impactResult = $microstructure->calculateMarketImpact(
    '0193a403-2c7b-7134-89aa-1234567890ab',
    5000,
    'BUY'
);
assert_true(isset($impactResult['filled_quantity']), 'Market impact returns filled qty');
assert_true(isset($impactResult['avg_execution_price']), 'Market impact returns avg price');
assert_true(isset($impactResult['market_impact_bps']), 'Market impact returns bps');

// Calculate liquidity profile
$liqResult = $microstructure->calculateLiquidityProfile(
    '0193a403-2c7b-7134-89aa-1234567890ab'
);
assert_true(isset($liqResult['liquidity_score']), 'Liquidity profile returns score');
assert_true($liqResult['liquidity_score'] >= 0 && $liqResult['liquidity_score'] <= 100, 'Liquidity score in range 0-100');
assert_true(
    in_array($liqResult['liquidity_grade'], ['EXCELLENT', 'GOOD', 'FAIR', 'POOR', 'ILLIQUID']),
    'Liquidity grade is valid'
);

// ─── Summary ─────────────────────────────────────────────────────────
echo "\n" . str_repeat('═', 60) . "\n";
echo "INTEGRATION TEST RESULTS\n";
echo str_repeat('═', 60) . "\n";
echo "  Passed: {$pass}\n";
echo "  Failed: {$fail}\n";
echo "  Total:  " . ($pass + $fail) . "\n";
echo str_repeat('═', 60) . "\n";

if ($fail > 0) {
    echo "STATUS: FAILED\n";
    exit(1);
} else {
    echo "STATUS: ALL PASSED\n";
    exit(0);
}
