<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Platform\Core\Application;
use Platform\MarketData\RealTimeDataService;

if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->safeLoad();
}

$app = Application::getInstance();
$intervalSeconds = (int) $app->getConfig('REALTIME_WORKER_INTERVAL_SECONDS', '30');
if ($intervalSeconds < 5) {
    $intervalSeconds = 5;
}

$symbols = [
    // IDX stocks
    'BBCA.JK', 'BBRI.JK', 'BMRI.JK', 'TLKM.JK', 'ASII.JK',
    'GOTO.JK', 'UNVR.JK', 'KLBF.JK',
    // Indices & FX
    '^JKSE', '^GSPC', 'IDR=X',
    // Crypto
    'BTC-USD', 'ETH-USD',
];

$service = new RealTimeDataService();

echo "[realtime-worker] Starting with interval {$intervalSeconds}s and " . count($symbols) . " symbols\n";

while (true) {
    $startedAt = microtime(true);
    $results = $service->refreshBatch($symbols);

    $success = 0;
    $failed = 0;
    foreach ($results as $symbol => $result) {
        if (isset($result['error'])) {
            $failed++;
            echo json_encode([
                'ts' => date('c'),
                'symbol' => $symbol,
                'status' => 'ERROR',
                'error' => $result['error'],
            ], JSON_UNESCAPED_UNICODE) . "\n";
        } else {
            $success++;
            echo json_encode([
                'ts' => date('c'),
                'symbol' => $symbol,
                'status' => 'OK',
                'price' => $result['price'],
                'currency' => $result['currency'],
                'cached' => $result['cached'],
            ], JSON_UNESCAPED_UNICODE) . "\n";
        }
    }

    $elapsed = microtime(true) - $startedAt;
    echo "[realtime-worker] Cycle complete: {$success} OK, {$failed} failed, "
        . number_format($elapsed, 3) . "s\n";

    sleep($intervalSeconds);
}
