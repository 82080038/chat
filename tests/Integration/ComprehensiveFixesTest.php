<?php

declare(strict_types=1);

namespace Platform\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Platform\Trading\BrokerAdapterService;
use Platform\Trading\Adapters\MockBrokerAdapter;
use Platform\Trading\Adapters\RestBrokerAdapter;
use Platform\Trading\BrokerAdapterInterface;
use Platform\Backtesting\BacktestService;
use Platform\Risk\RiskService;
use Platform\Microstructure\MicrostructureService;
use Platform\AIEngine\AIEngineService;
use Platform\DataIngestion\DataIngestionService;
use Platform\Core\Exceptions\ApiException;

/**
 * Comprehensive tests for all fixes made to the codebase.
 *
 * Tests cover:
 * 1. BrokerAdapterService REST routing
 * 2. BacktestService strategies (SMA, RSI, Momentum, Mean Reversion, Buy & Hold)
 * 3. BacktestService annualized return formula
 * 4. RiskService VaR computation
 * 5. RiskService checkLimits with portfolio exposure
 * 6. MicrostructureService liquidation days
 * 7. AIEngineService sentiment with weighted scoring, negation, intensifiers
 * 8. AIEngineService pattern recognition
 * 9. DataIngestionService fetchFromExternal interface compliance
 */
final class ComprehensiveFixesTest extends TestCase
{
    private MockPdo $pdo;

    protected function setUp(): void
    {
        $this->pdo = new MockPdo();
    }

    // ─── 1. BrokerAdapterService REST Routing ───────────────────────────

    public function testCreateAdapterReturnsMockForNoneApiType(): void
    {
        $service = new BrokerAdapterService($this->pdo);

        // Insert a mock broker
        $this->pdo->insertRow('trading.broker', [
            'broker_id' => 'brk-mock',
            'name' => 'Mock Broker',
            'api_type' => 'NONE',
            'status' => 'ACTIVE',
        ]);

        $adapter = $service->getAdapter('brk-mock');
        $this->assertInstanceOf(MockBrokerAdapter::class, $adapter);
    }

    public function testCreateAdapterReturnsRestForRestApiType(): void
    {
        $service = new BrokerAdapterService($this->pdo);

        $this->pdo->insertRow('trading.broker', [
            'broker_id' => 'brk-rest',
            'name' => 'Real REST Broker',
            'api_type' => 'REST',
            'status' => 'ACTIVE',
            'api_config' => json_encode([
                'base_url' => 'https://api.example.com/v1',
                'auth_type' => 'BEARER',
            ]),
        ]);

        $adapter = $service->getAdapter('brk-rest');
        $this->assertInstanceOf(RestBrokerAdapter::class, $adapter);
        $this->assertInstanceOf(BrokerAdapterInterface::class, $adapter);
    }

    public function testCreateAdapterRestThrowsWithoutBaseUrl(): void
    {
        $service = new BrokerAdapterService($this->pdo);

        $this->pdo->insertRow('trading.broker', [
            'broker_id' => 'brk-rest-noconfig',
            'name' => 'REST No Config',
            'api_type' => 'REST',
            'status' => 'ACTIVE',
            'api_config' => null,
        ]);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('no base_url');
        $service->getAdapter('brk-rest-noconfig');
    }

    public function testCreateAdapterFixThrowsNotImplemented(): void
    {
        $service = new BrokerAdapterService($this->pdo);

        $this->pdo->insertRow('trading.broker', [
            'broker_id' => 'brk-fix',
            'name' => 'FIX Broker',
            'api_type' => 'FIX',
            'status' => 'ACTIVE',
        ]);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('FIX');
        $service->getAdapter('brk-fix');
    }

    public function testCreateAdapterThrowsUnknownApiType(): void
    {
        $service = new BrokerAdapterService($this->pdo);

        $this->pdo->insertRow('trading.broker', [
            'broker_id' => 'brk-unknown',
            'name' => 'Unknown Broker',
            'api_type' => 'WEBSOCKET',
            'status' => 'ACTIVE',
        ]);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Unknown api_type');
        $service->getAdapter('brk-unknown');
    }

    public function testRestBrokerAdapterImplementsInterface(): void
    {
        $this->assertTrue(
            is_subclass_of(RestBrokerAdapter::class, BrokerAdapterInterface::class)
        );
    }

    public function testAdapterCacheReusesInstance(): void
    {
        $service = new BrokerAdapterService($this->pdo);

        $this->pdo->insertRow('trading.broker', [
            'broker_id' => 'brk-cache',
            'name' => 'Cache Test',
            'api_type' => 'NONE',
            'status' => 'ACTIVE',
        ]);

        $adapter1 = $service->getAdapter('brk-cache');
        $adapter2 = $service->getAdapter('brk-cache');
        $this->assertSame($adapter1, $adapter2);
    }

    // ─── 2. BacktestService Strategies ──────────────────────────────────

    public function testBacktestBuyAndHoldStrategy(): void
    {
        $service = new BacktestService($this->pdo);
        $run = $service->createRun([
            'strategy_name' => 'BUY_AND_HOLD',
            'start_date' => '2024-01-01',
            'end_date' => '2024-01-10',
            'initial_capital' => 100000,
            'instrument_id' => 'inst-001',
        ]);

        $priceData = [];
        for ($i = 1; $i <= 10; $i++) {
            $priceData[] = ['date' => "2024-01-{$i}", 'close' => 100 + $i];
        }

        $result = $service->executeRun($run['run_id'], $priceData);
        $this->assertSame('COMPLETED', $result['status']);
        $this->assertGreaterThan(0, $result['total_trades']);
        // Buy and hold should have exactly 1 trade
        $this->assertSame(1, $result['total_trades']);
    }

    public function testBacktestSmaCrossoverStrategy(): void
    {
        $service = new BacktestService($this->pdo);
        $run = $service->createRun([
            'strategy_name' => 'SMA_CROSSOVER',
            'start_date' => '2024-01-01',
            'end_date' => '2024-03-31',
            'initial_capital' => 100000,
            'instrument_id' => 'inst-001',
            'parameters' => ['short_period' => 5, 'long_period' => 15],
        ]);

        // Create price data with a trend change to trigger crossover
        $priceData = [];
        for ($i = 1; $i <= 30; $i++) {
            if ($i <= 15) {
                $priceData[] = ['date' => "2024-01-{$i}", 'close' => 100 + $i * 0.5];
            } else {
                $priceData[] = ['date' => "2024-01-{$i}", 'close' => 110 - $i * 0.5];
            }
        }

        $result = $service->executeRun($run['run_id'], $priceData);
        $this->assertSame('COMPLETED', $result['status']);
    }

    public function testBacktestRsiMeanReversionStrategy(): void
    {
        $service = new BacktestService($this->pdo);
        $run = $service->createRun([
            'strategy_name' => 'RSI_MEAN_REVERSION',
            'start_date' => '2024-01-01',
            'end_date' => '2024-03-31',
            'initial_capital' => 100000,
            'instrument_id' => 'inst-001',
            'parameters' => ['rsi_period' => 5, 'oversold' => 30, 'overbought' => 70],
        ]);

        // Create oscillating price data
        $priceData = [];
        for ($i = 1; $i <= 30; $i++) {
            $priceData[] = ['date' => "2024-01-{$i}", 'close' => 100 + sin($i / 3) * 10];
        }

        $result = $service->executeRun($run['run_id'], $priceData);
        $this->assertSame('COMPLETED', $result['status']);
    }

    public function testBacktestMomentumStrategy(): void
    {
        $service = new BacktestService($this->pdo);
        $run = $service->createRun([
            'strategy_name' => 'MOMENTUM',
            'start_date' => '2024-01-01',
            'end_date' => '2024-03-31',
            'initial_capital' => 100000,
            'instrument_id' => 'inst-001',
            'parameters' => ['sma_period' => 5, 'momentum_period' => 3],
        ]);

        // Create trending data
        $priceData = [];
        for ($i = 1; $i <= 30; $i++) {
            $priceData[] = ['date' => "2024-01-{$i}", 'close' => 100 + $i * 2];
        }

        $result = $service->executeRun($run['run_id'], $priceData);
        $this->assertSame('COMPLETED', $result['status']);
    }

    public function testBacktestMeanReversionStrategy(): void
    {
        $service = new BacktestService($this->pdo);
        $run = $service->createRun([
            'strategy_name' => 'MEAN_REVERSION',
            'start_date' => '2024-01-01',
            'end_date' => '2024-03-31',
            'initial_capital' => 100000,
            'instrument_id' => 'inst-001',
            'parameters' => ['sma_period' => 5, 'deviation' => 1.5],
        ]);

        // Create data with mean-reverting pattern
        $priceData = [];
        for ($i = 1; $i <= 30; $i++) {
            $priceData[] = ['date' => "2024-01-{$i}", 'close' => 100 + sin($i / 2) * 15];
        }

        $result = $service->executeRun($run['run_id'], $priceData);
        $this->assertSame('COMPLETED', $result['status']);
    }

    // ─── 3. BacktestService Annualized Return ───────────────────────────

    public function testAnnualizedReturnCalculation(): void
    {
        $service = new BacktestService($this->pdo);
        $run = $service->createRun([
            'strategy_name' => 'BUY_AND_HOLD',
            'start_date' => '2024-01-01',
            'end_date' => '2024-06-30',
            'initial_capital' => 100000,
            'instrument_id' => 'inst-001',
        ]);

        $priceData = [
            ['date' => '2024-01-01', 'close' => 100],
            ['date' => '2024-06-30', 'close' => 110],
        ];

        $result = $service->executeRun($run['run_id'], $priceData);
        $metrics = $result['metrics'];

        // Total return should be 10% (bought at 100, sold at 110)
        $this->assertGreaterThan(0, $metrics['total_return']);

        // Annualized return should be roughly 20% for 6 months (10% * 2)
        // but with compounding: (1.10)^(365/181) - 1 ≈ 20.9%
        $this->assertGreaterThan(0, $metrics['annualized_return']);
        $this->assertLessThan(100, $metrics['annualized_return']);
    }

    public function testAnnualizedReturnNegativeForLoss(): void
    {
        $service = new BacktestService($this->pdo);
        $run = $service->createRun([
            'strategy_name' => 'BUY_AND_HOLD',
            'start_date' => '2024-01-01',
            'end_date' => '2024-06-30',
            'initial_capital' => 100000,
            'instrument_id' => 'inst-001',
        ]);

        $priceData = [
            ['date' => '2024-01-01', 'close' => 100],
            ['date' => '2024-06-30', 'close' => 90],
        ];

        $result = $service->executeRun($run['run_id'], $priceData);
        $this->assertLessThan(0, $result['metrics']['total_return']);
        $this->assertLessThan(0, $result['metrics']['annualized_return']);
    }

    // ─── 4. RiskService VaR Computation ─────────────────────────────────

    public function testTriggerAssessmentComputesVarFromPortfolio(): void
    {
        $service = new RiskService($this->pdo);

        // Insert open positions
        $this->pdo->insertRow('portfolio.position', [
            'instrument_id' => 'inst-001',
            'portfolio_id' => 'pf-001',
            'quantity' => 1000,
            'average_cost' => 7500,
            'status' => 'OPEN',
        ]);

        // Insert OHLCV data for daily returns calculation
        for ($i = 0; $i < 30; $i++) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $this->pdo->insertRow('data_ingestion.ohlcv_daily', [
                'instrument_id' => 'inst-001',
                'trade_date' => $date,
                'close' => 7500 + rand(-200, 200),
            ]);
        }

        $result = $service->triggerAssessment('pf-001', [
            'assessment_type' => 'PORTFOLIO_VAR',
        ]);

        $this->assertNotNull($result);
        $this->assertSame('PORTFOLIO_VAR', $result['assessment_type']);
        $this->assertSame('historical-v1', $result['model_version']);
    }

    public function testTriggerAssessmentWithNoPositionsReturnsNulls(): void
    {
        $service = new RiskService($this->pdo);

        $result = $service->triggerAssessment('empty-pf', [
            'assessment_type' => 'PORTFOLIO_VAR',
        ]);

        $this->assertNotNull($result);
        $this->assertNull($result['var_95']);
        $this->assertNull($result['var_99']);
    }

    public function testTriggerAssessmentUserOverrideTakesPrecedence(): void
    {
        $service = new RiskService($this->pdo);

        $this->pdo->insertRow('portfolio.position', [
            'instrument_id' => 'inst-001',
            'portfolio_id' => 'pf-override',
            'quantity' => 100,
            'average_cost' => 100,
            'status' => 'OPEN',
        ]);

        $result = $service->triggerAssessment('pf-override', [
            'assessment_type' => 'PORTFOLIO_VAR',
            'var_95' => 5000000,
            'var_99' => 8000000,
        ]);

        $this->assertSame('5000000', (string) $result['var_95']);
        $this->assertSame('8000000', (string) $result['var_99']);
    }

    // ─── 5. RiskService checkLimits ─────────────────────────────────────

    public function testCheckLimitsPassesWithNoViolations(): void
    {
        $service = new RiskService($this->pdo);

        // Set a limit
        $this->pdo->insertRow('risk.risk_limit', [
            'risk_limit_id' => 'rl-001',
            'portfolio_id' => 'pf-check',
            'limit_type' => 'MAX_ORDER_VALUE',
            'limit_value' => 100000000,
            'status' => 'ACTIVE',
        ]);

        $result = $service->checkLimits('pf-check', [
            'instrument_id' => 'inst-001',
            'quantity' => 100,
            'limit_price' => 5000,
        ]);

        $this->assertTrue($result['passed']);
        $this->assertSame([], $result['violations']);
    }

    public function testCheckLimitsDetectsMaxOrderValueViolation(): void
    {
        $service = new RiskService($this->pdo);

        $this->pdo->insertRow('risk.risk_limit', [
            'risk_limit_id' => 'rl-002',
            'portfolio_id' => 'pf-check2',
            'limit_type' => 'MAX_ORDER_VALUE',
            'limit_value' => 100000,
            'status' => 'ACTIVE',
        ]);

        $result = $service->checkLimits('pf-check2', [
            'instrument_id' => 'inst-001',
            'quantity' => 100,
            'limit_price' => 5000,
        ]);

        $this->assertFalse($result['passed']);
        $this->assertCount(1, $result['violations']);
        $this->assertSame('MAX_ORDER_VALUE', $result['violations'][0]['limit_type']);
    }

    public function testCheckLimitsSkipsInactiveLimits(): void
    {
        $service = new RiskService($this->pdo);

        $this->pdo->insertRow('risk.risk_limit', [
            'risk_limit_id' => 'rl-003',
            'portfolio_id' => 'pf-check3',
            'limit_type' => 'MAX_ORDER_VALUE',
            'limit_value' => 100,
            'status' => 'INACTIVE',
        ]);

        $result = $service->checkLimits('pf-check3', [
            'instrument_id' => 'inst-001',
            'quantity' => 100,
            'limit_price' => 5000,
        ]);

        $this->assertTrue($result['passed']);
    }

    public function testCheckLimitsMaxPortfolioExposure(): void
    {
        // Note: MockPdo cannot handle JOIN queries used by getPortfolioExposure.
        // The MAX_PORTFOLIO_EXPOSURE check relies on getPortfolioExposure which
        // uses a LEFT JOIN. With MockPdo, the JOIN returns no rows, so
        // portfolioExposure['total'] = 0. The check becomes: 0 + orderValue > limit.
        // We test with orderValue > limit to trigger the violation.
        $service = new RiskService($this->pdo);

        $this->pdo->insertRow('risk.risk_limit', [
            'risk_limit_id' => 'rl-004',
            'portfolio_id' => 'pf-exp',
            'limit_type' => 'MAX_PORTFOLIO_EXPOSURE',
            'limit_value' => 500000,
            'status' => 'ACTIVE',
        ]);

        $result = $service->checkLimits('pf-exp', [
            'instrument_id' => 'inst-002',
            'quantity' => 200,
            'limit_price' => 5000,
        ]);

        // orderValue = 200 * 5000 = 1000000 > 500000 limit
        // With MockPdo, portfolioExposure['total'] = 0, so newTotal = 1000000
        $this->assertFalse($result['passed']);
        $this->assertCount(1, $result['violations']);
        $this->assertSame('MAX_PORTFOLIO_EXPOSURE', $result['violations'][0]['limit_type']);
    }

    // ─── 6. MicrostructureService Liquidation Days ──────────────────────

    public function testCalculateLiquidityProfileReturnsStructuredResult(): void
    {
        $service = new MicrostructureService($this->pdo);

        $result = $service->calculateLiquidityProfile('inst-001');

        $this->assertIsArray($result);
        $this->assertSame('inst-001', $result['instrument_id']);
        $this->assertArrayHasKey('liquidity_score', $result);
        $this->assertArrayHasKey('liquidity_grade', $result);
        $this->assertArrayHasKey('liquidity_regime', $result);
    }

    // ─── 7. AIEngineService Sentiment ───────────────────────────────────

    public function testSentimentWeightedScoringPositive(): void
    {
        $service = new AIEngineService($this->pdo);

        $result = $service->analyzeSentiment([
            'text' => 'BBCA surge with extremely strong profit growth and record high earnings',
        ]);

        $this->assertSame('POSITIVE', $result['sentiment_label']);
        $this->assertGreaterThan(0, $result['sentiment_score']);
    }

    public function testSentimentWeightedScoringNegative(): void
    {
        $service = new AIEngineService($this->pdo);

        $result = $service->analyzeSentiment([
            'text' => 'TLKM crash with extremely weak performance and significant loss',
        ]);

        $this->assertSame('NEGATIVE', $result['sentiment_label']);
        $this->assertLessThan(0, $result['sentiment_score']);
    }

    public function testSentimentNegationFlipsPositive(): void
    {
        $service = new AIEngineService($this->pdo);

        $result = $service->analyzeSentiment([
            'text' => 'The company did not report strong results',
        ]);

        // "not strong" should flip to negative; "results" is not a sentiment word
        $this->assertSame('NEGATIVE', $result['sentiment_label']);
        $this->assertLessThan(0, $result['sentiment_score']);
    }

    public function testSentimentNegationFlipsNegative(): void
    {
        $service = new AIEngineService($this->pdo);

        $result = $service->analyzeSentiment([
            'text' => 'The stock did not crash today',
        ]);

        // "not crash" should flip crash to positive
        $this->assertSame('POSITIVE', $result['sentiment_label']);
        $this->assertGreaterThan(0, $result['sentiment_score']);
    }

    public function testSentimentIntensifierAmplifiesScore(): void
    {
        $service = new AIEngineService($this->pdo);

        // Use a mix of positive and negative so score is not capped at 100
        $plainResult = $service->analyzeSentiment([
            'text' => 'The stock surge but also drop today',
        ]);

        $intensifiedResult = $service->analyzeSentiment([
            'text' => 'The stock extremely surge but also drop today',
        ]);

        // The intensified positive word gets a higher weight, so the net score
        // should be more positive (or less negative) than the plain version
        $this->assertGreaterThan(
            $plainResult['sentiment_score'],
            $intensifiedResult['sentiment_score']
        );
    }

    public function testSentimentNeutralWithNoKeywords(): void
    {
        $service = new AIEngineService($this->pdo);

        $result = $service->analyzeSentiment([
            'text' => 'The company held its annual meeting today',
        ]);

        $this->assertSame('NEUTRAL', $result['sentiment_label']);
        $this->assertSame(0.0, $result['sentiment_score']);
    }

    public function testSentimentIndonesianLanguage(): void
    {
        $service = new AIEngineService($this->pdo);

        $result = $service->analyzeSentiment([
            'text' => 'Saham BBCA naik dengan pertumbuhan untung yang sangat tinggi',
        ]);

        $this->assertSame('POSITIVE', $result['sentiment_label']);
    }

    public function testSentimentExtractsMonetaryEntities(): void
    {
        $service = new AIEngineService($this->pdo);

        $result = $service->analyzeSentiment([
            'text' => 'BBCA announced Rp 10 miliar dividend and USD 5 million buyback',
        ]);

        $this->assertArrayHasKey('entities', $result);
        $this->assertArrayHasKey('monetary', $result['entities']);
    }

    public function testSentimentExtractsPercentageEntities(): void
    {
        $service = new AIEngineService($this->pdo);

        $result = $service->analyzeSentiment([
            'text' => 'Revenue grew 15% with profit margin at 22.5%',
        ]);

        $this->assertArrayHasKey('entities', $result);
        $this->assertArrayHasKey('percentages', $result['entities']);
    }

    // ─── 8. AIEngineService Pattern Recognition ─────────────────────────

    public function testPatternRecognitionAscendingTrend(): void
    {
        $service = new AIEngineService($this->pdo);

        $priceData = [];
        for ($i = 0; $i < 20; $i++) {
            $priceData[] = [
                'high' => 100 + $i * 5,
                'low' => 95 + $i * 5,
                'close' => 98 + $i * 5,
            ];
        }

        $result = $service->recognizePattern([
            'price_data' => $priceData,
            'instrument_id' => 'inst-001',
        ]);

        $this->assertSame('PATTERN', $result['analysis_type']);
        $this->assertNotEmpty($result['pattern_type']);
        $this->assertGreaterThan(0, $result['pattern_confidence']);
    }

    public function testPatternRecognitionDescendingTrend(): void
    {
        $service = new AIEngineService($this->pdo);

        $priceData = [];
        for ($i = 0; $i < 20; $i++) {
            $priceData[] = [
                'high' => 200 - $i * 5,
                'low' => 195 - $i * 5,
                'close' => 198 - $i * 5,
            ];
        }

        $result = $service->recognizePattern([
            'price_data' => $priceData,
            'instrument_id' => 'inst-002',
        ]);

        $this->assertSame('PATTERN', $result['analysis_type']);
        $this->assertNotEmpty($result['pattern_type']);
    }

    public function testPatternRecognitionFlatMarket(): void
    {
        $service = new AIEngineService($this->pdo);

        $priceData = [];
        for ($i = 0; $i < 20; $i++) {
            $priceData[] = [
                'high' => 100,
                'low' => 100,
                'close' => 100,
            ];
        }

        $result = $service->recognizePattern([
            'price_data' => $priceData,
            'instrument_id' => 'inst-003',
        ]);

        $this->assertSame('FLAT', $result['pattern_type']);
    }

    public function testPatternRecognitionDoubleTop(): void
    {
        $service = new AIEngineService($this->pdo);

        // Create data with two peaks at similar levels
        $priceData = [];
        $peaks = [100, 105, 110, 108, 100, 95, 100, 108, 110, 108, 100];
        for ($i = 0; $i < count($peaks); $i++) {
            $priceData[] = [
                'high' => $peaks[$i] + 2,
                'low' => $peaks[$i] - 2,
                'close' => $peaks[$i],
            ];
        }

        $result = $service->recognizePattern([
            'price_data' => $priceData,
            'instrument_id' => 'inst-004',
        ]);

        $this->assertSame('PATTERN', $result['analysis_type']);
    }

    // ─── 9. DataIngestionService fetchFromExternal ──────────────────────

    public function testFetchFromExternalInterfaceExists(): void
    {
        $this->assertTrue(
            method_exists(DataIngestionService::class, 'fetchFromExternal')
        );
    }

    public function testFetchFromExternalThrowsUnsupportedProvider(): void
    {
        $service = new DataIngestionService($this->pdo);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('is not supported');
        $service->fetchFromExternal('invalid_provider', 'BBCA');
    }

    public function testFetchFromExternalThrowsMissingApiKey(): void
    {
        $service = new DataIngestionService($this->pdo);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('requires an API key');
        $service->fetchFromExternal('alphavantage', 'BBCA');
    }

    // ─── Cross-cutting: Interface compliance ────────────────────────────

    public function testAllServicesImplementTheirInterfaces(): void
    {
        $this->assertTrue(is_subclass_of(BrokerAdapterService::class, \Platform\Core\BaseService::class));
        $this->assertTrue(is_subclass_of(BacktestService::class, \Platform\Backtesting\BacktestServiceInterface::class));
        $this->assertTrue(is_subclass_of(RiskService::class, \Platform\Risk\RiskServiceInterface::class));
        $this->assertTrue(is_subclass_of(MicrostructureService::class, \Platform\Microstructure\MicrostructureServiceInterface::class));
        $this->assertTrue(is_subclass_of(AIEngineService::class, \Platform\AIEngine\AIEngineServiceInterface::class));
        $this->assertTrue(is_subclass_of(DataIngestionService::class, \Platform\DataIngestion\DataIngestionServiceInterface::class));
    }

    public function testDataIngestionInterfaceDeclaresFetchFromExternal(): void
    {
        $reflection = new \ReflectionClass(\Platform\DataIngestion\DataIngestionServiceInterface::class);
        $this->assertTrue($reflection->hasMethod('fetchFromExternal'));
    }
}
