<?php

declare(strict_types=1);

namespace Platform\Tests\AIEngine;

use PHPUnit\Framework\TestCase;
use Platform\AIEngine\AIEngineService;
use Platform\Tests\Integration\MockPdo;

final class AIEngineServiceTest extends TestCase
{
    private MockPdo $pdo;

    protected function setUp(): void
    {
        $this->pdo = new MockPdo();
    }

    public function testServiceImplementsInterface(): void
    {
        $this->assertTrue(is_subclass_of(
            AIEngineService::class,
            \Platform\AIEngine\AIEngineServiceInterface::class
        ));
    }

    public function testAnalyzeSentimentPositive(): void
    {
        $service = new AIEngineService($this->pdo);
        $result = $service->analyzeSentiment([
            'text' => 'BBCA stock surge with strong profit growth and record high earnings',
            'instrument_id' => 'inst-001',
        ]);
        $this->assertSame('SENTIMENT', $result['analysis_type']);
        $this->assertSame('POSITIVE', $result['sentiment_label']);
        $this->assertGreaterThan(0, $result['sentiment_score']);
    }

    public function testAnalyzeSentimentNegative(): void
    {
        $service = new AIEngineService($this->pdo);
        $result = $service->analyzeSentiment([
            'text' => 'TLKM stock drop with weak performance and loss decline crash',
        ]);
        $this->assertSame('NEGATIVE', $result['sentiment_label']);
        $this->assertLessThan(0, $result['sentiment_score']);
    }

    public function testAnalyzeSentimentNeutral(): void
    {
        $service = new AIEngineService($this->pdo);
        $result = $service->analyzeSentiment([
            'text' => 'The company held its annual meeting today',
        ]);
        $this->assertSame('NEUTRAL', $result['sentiment_label']);
        $this->assertSame(0.0, $result['sentiment_score']);
    }

    public function testAnalyzeSentimentMissingTextThrows(): void
    {
        $service = new AIEngineService($this->pdo);
        $this->expectException(\Platform\Core\Exceptions\ApiException::class);
        $service->analyzeSentiment([]);
    }

    public function testAnalyzeSentimentExtractsEntities(): void
    {
        $service = new AIEngineService($this->pdo);
        $result = $service->analyzeSentiment([
            'text' => 'BBCA and TLKM announced strong earnings today',
        ]);
        $this->assertArrayHasKey('entities', $result);
        $this->assertArrayHasKey('tickers', $result['entities']);
    }

    public function testAnalyzeSentimentExtractsEvents(): void
    {
        $service = new AIEngineService($this->pdo);
        $result = $service->analyzeSentiment([
            'text' => 'Company announced dividend and stock split plans',
        ]);
        $this->assertContains('DIVIDEND', $result['events']);
        $this->assertContains('STOCK_SPLIT', $result['events']);
    }

    public function testRecognizePatternAscending(): void
    {
        $service = new AIEngineService($this->pdo);
        $priceData = [];
        for ($i = 0; $i < 10; $i++) {
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

    public function testRecognizePatternInsufficientDataThrows(): void
    {
        $service = new AIEngineService($this->pdo);
        $this->expectException(\Platform\Core\Exceptions\ApiException::class);
        $service->recognizePattern([
            'price_data' => [['high' => 100, 'low' => 95, 'close' => 98]],
            'instrument_id' => 'inst-001',
        ]);
    }

    public function testRecognizePatternMissingFieldThrows(): void
    {
        $service = new AIEngineService($this->pdo);
        $this->expectException(\Platform\Core\Exceptions\ApiException::class);
        $service->recognizePattern(['price_data' => []]);
    }

    public function testDetectAnomalyWithSpike(): void
    {
        $service = new AIEngineService($this->pdo);
        $values = [10, 11, 10, 12, 10, 50, 11, 10];
        $result = $service->detectAnomaly([
            'values' => $values,
            'instrument_id' => 'inst-001',
        ]);
        $this->assertSame('ANOMALY', $result['analysis_type']);
        $this->assertGreaterThan(0, $result['anomaly_score']);
        $this->assertSame('SPIKE', $result['anomaly_type']);
        $this->assertGreaterThan(0, $result['anomaly_count']);
    }

    public function testDetectAnomalyNoAnomaly(): void
    {
        $service = new AIEngineService($this->pdo);
        $values = [10, 11, 10, 11, 10, 11, 10, 11];
        $result = $service->detectAnomaly([
            'values' => $values,
            'instrument_id' => 'inst-001',
        ]);
        $this->assertSame('NONE', $result['anomaly_type']);
        $this->assertSame(0, $result['anomaly_count']);
    }

    public function testDetectAnomalyInsufficientDataThrows(): void
    {
        $service = new AIEngineService($this->pdo);
        $this->expectException(\Platform\Core\Exceptions\ApiException::class);
        $service->detectAnomaly([
            'values' => [10],
            'instrument_id' => 'inst-001',
        ]);
    }

    public function testListAnalyses(): void
    {
        $service = new AIEngineService($this->pdo);
        $service->analyzeSentiment(['text' => 'Good news']);
        $result = $service->listAnalyses([], 1, 50);
        $this->assertCount(1, $result['data']);
    }

    public function testCreateModelRun(): void
    {
        $service = new AIEngineService($this->pdo);
        $result = $service->createModelRun([
            'model_name' => 'sentiment_v1',
            'model_version' => '1.0.0',
            'input_count' => 100,
        ]);
        $this->assertSame('RUNNING', $result['status']);
        $this->assertSame('sentiment_v1', $result['model_name']);
    }

    public function testCreateModelRunMissingFieldThrows(): void
    {
        $service = new AIEngineService($this->pdo);
        $this->expectException(\Platform\Core\Exceptions\ApiException::class);
        $service->createModelRun(['model_name' => 'test']);
    }

    public function testUpdateModelRun(): void
    {
        $service = new AIEngineService($this->pdo);
        $run = $service->createModelRun([
            'model_name' => 'test_model',
            'model_version' => '1.0',
        ]);
        $result = $service->updateModelRun($run['run_id'], [
            'status' => 'COMPLETED',
            'output_count' => 95,
        ]);
        $this->assertSame('COMPLETED', $result['status']);
        $this->assertSame(95, $result['output_count']);
    }

    public function testUpdateModelRunNotFoundThrows(): void
    {
        $service = new AIEngineService($this->pdo);
        $this->expectException(\Platform\Core\Exceptions\ApiException::class);
        $service->updateModelRun('nonexistent', ['status' => 'COMPLETED']);
    }
}
