<?php

declare(strict_types=1);

namespace Platform\Core\Analytics;

use Platform\Core\Application;

/**
 * Bridge to Python analytics engine for ML/AI computations.
 *
 * Communicates with a Python subprocess or HTTP microservice.
 * Fail-safe: if Python is unavailable, returns null and caller falls back to PHP stub.
 *
 * Blueprint sections 438, 509: PHP backend + Python analytics polyglot architecture.
 */
final class PythonBridge
{
    private static ?PythonBridge $instance = null;

    private string $pythonPath;
    private string $scriptPath;
    private int $timeoutSeconds;
    private bool $enabled;

    private function __construct()
    {
        $app = Application::getInstance();
        $this->pythonPath = $app->getConfig('PYTHON_PATH', 'python3');
        $this->scriptPath = $app->getConfig('PYTHON_SCRIPT_PATH', dirname(__DIR__, 3) . '/scripts/analytics_bridge.py');
        $this->timeoutSeconds = (int) $app->getConfig('PYTHON_TIMEOUT', 30);
        $this->enabled = (bool) $app->getConfig('PYTHON_ENABLED', false);
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function isAvailable(): bool
    {
        if (!$this->enabled) {
            return false;
        }
        return file_exists($this->scriptPath) && $this->pythonPath !== '';
    }

    /**
     * Call a Python analytics function with JSON input/output.
     *
     * @param string $function Name of the Python function to call
     * @param array<string, mixed> $input Input data passed as JSON
     * @return array<string, mixed>|null Result decoded from JSON, or null if unavailable
     */
    public function call(string $function, array $input): ?array
    {
        if (!$this->isAvailable()) {
            return null;
        }

        $payload = json_encode([
            'function' => $function,
            'input' => $input,
        ], JSON_THROW_ON_ERROR);

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $command = sprintf(
            '%s %s --function %s 2>&1',
            escapeshellarg($this->pythonPath),
            escapeshellarg($this->scriptPath),
            escapeshellarg($function)
        );

        $process = proc_open($command, $descriptors, $pipes, null, [
            'PAYLOAD' => $payload,
            'PYTHONUNBUFFERED' => '1',
        ]);

        if (!is_resource($process)) {
            return null;
        }

        fwrite($pipes[0], $payload);
        fclose($pipes[0]);

        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $status = proc_get_status($process);
        proc_close($process);

        if ($status['exitcode'] !== 0) {
            return null;
        }

        $result = json_decode($output, true);
        return is_array($result) ? $result : null;
    }

    /**
     * Calculate technical indicators using Python (TA-Lib / pandas).
     *
     * @param array<int, array<string, mixed>> $ohlcvData
     * @param array<string, mixed> $params
     * @return array<string, mixed>|null
     */
    public function calculateIndicators(array $ohlcvData, array $params): ?array
    {
        return $this->call('calculate_indicators', [
            'ohlcv' => $ohlcvData,
            'params' => $params,
        ]);
    }

    /**
     * Generate trading signals using Python ML model.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>|null
     */
    public function generateSignals(array $input): ?array
    {
        return $this->call('generate_signals', $input);
    }

    /**
     * Generate price forecasts using Python model.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>|null
     */
    public function generateForecast(array $input): ?array
    {
        return $this->call('generate_forecast', $input);
    }

    /**
     * Run sentiment analysis using Python NLP.
     *
     * @param string $text
     * @return array<string, mixed>|null
     */
    public function analyzeSentiment(string $text): ?array
    {
        return $this->call('analyze_sentiment', ['text' => $text]);
    }

    /**
     * Run backtesting using Python engine.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>|null
     */
    public function runBacktest(array $input): ?array
    {
        return $this->call('run_backtest', $input);
    }
}
