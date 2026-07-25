<?php
/**
 * Market Data Seeder - CLI Entry Point
 *
 * Usage:
 *   php scripts/seed_market_data.php                    # Fetch all symbols
 *   php scripts/seed_market_data.php --symbol=BBCA.JK   # Fetch specific symbol
 *   php scripts/seed_market_data.php --days=365         # Custom lookback
 *   php scripts/seed_market_data.php --delay=3          # Custom delay (seconds)
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Platform\DataIngestion\MarketDataSeeder;

$options = getopt('', ['symbol::', 'days::', 'delay::']);

$lookback = isset($options['days']) ? (int) $options['days'] : 730;
$delay = isset($options['delay']) ? (int) $options['delay'] : 2;
$filterSymbol = $options['symbol'] ?? null;

echo "==========================================\n";
echo "  Market Data Seeder\n";
echo "  Source: Yahoo Finance (free API)\n";
echo "  Lookback: {$lookback} days (~" . round($lookback / 365, 1) . " years)\n";
echo "  Rate limit delay: {$delay}s between requests\n";
echo "==========================================\n\n";

$seeder = new MarketDataSeeder($lookback, $delay);
$results = $seeder->run($filterSymbol);

echo "\n==========================================\n";
echo "  SEEDING COMPLETE\n";
echo "==========================================\n";

$totalIngested = 0;
$errors = 0;
foreach ($results as $r) {
    if ($r['status'] === 'OK') {
        $totalIngested += $r['records_ingested'];
        echo sprintf("  OK   %-12s  %5d records\n", $r['symbol'], $r['records_ingested']);
    } else {
        $errors++;
        echo sprintf("  FAIL %-12s  ERROR: %s\n", $r['symbol'], $r['error']);
    }
}

echo sprintf("\n  Total records ingested: %d\n", $totalIngested);
echo sprintf("  Errors: %d\n", $errors);
echo "==========================================\n";
