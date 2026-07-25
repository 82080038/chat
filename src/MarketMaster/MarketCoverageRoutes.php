<?php

declare(strict_types=1);

namespace Platform\MarketMaster;

use Platform\Core\Database\MySqlConnection;
use Platform\Core\Http\Request;
use Platform\Core\Http\Response;
use Platform\Core\Http\Router;

final class MarketCoverageRoutes
{
    public static function register(Router $router): void
    {
        $router->get('/market-coverage', [self::class, 'getCoverage'], ['bearer']);
    }

    public static function getCoverage(Request $request): Response
    {
        $db = MySqlConnection::getInstance();

        // 1. Define all supported market types with capabilities
        $marketTypes = self::MARKET_TYPES;

        // 2. Query actual instrument counts per asset_class + instrument_type
        $stmt = $db->prepare(
            'SELECT asset_class, instrument_type, COUNT(*) as instrument_count
             FROM market_master.instrument
             WHERE status = "ACTIVE"
             GROUP BY asset_class, instrument_type'
        );
        $stmt->execute();
        $instrumentCounts = [];
        foreach ($stmt->fetchAll() as $row) {
            $key = $row['asset_class'] . ':' . $row['instrument_type'];
            $instrumentCounts[$key] = (int) $row['instrument_count'];
        }

        // 3. Query OHLCV data availability per instrument
        $stmt = $db->prepare(
            'SELECT i.asset_class, i.instrument_type,
                    COUNT(DISTINCT o.instrument_id) as instruments_with_data,
                    COUNT(*) as total_ohlcv_records,
                    MIN(o.trade_date) as earliest_date,
                    MAX(o.trade_date) as latest_date
             FROM data_ingestion.ohlcv_daily o
             INNER JOIN market_master.instrument i ON o.instrument_id = i.instrument_id
             GROUP BY i.asset_class, i.instrument_type'
        );
        $stmt->execute();
        $ohlcvStats = [];
        foreach ($stmt->fetchAll() as $row) {
            $key = $row['asset_class'] . ':' . $row['instrument_type'];
            $ohlcvStats[$key] = [
                'instruments_with_data' => (int) $row['instruments_with_data'],
                'total_records' => (int) $row['total_ohlcv_records'],
                'earliest_date' => $row['earliest_date'],
                'latest_date' => $row['latest_date'],
            ];
        }

        // 4. Query active signals per asset class (signal uses invalidated_at, not status)
        $stmt = $db->prepare(
            'SELECT i.asset_class, i.instrument_type,
                    COUNT(*) as signal_count,
                    MAX(sig.created_at) as latest_signal
             FROM analytics.signal sig
             INNER JOIN market_master.instrument i ON sig.instrument_id = i.instrument_id
             WHERE sig.invalidated_at IS NULL
             GROUP BY i.asset_class, i.instrument_type'
        );
        $stmt->execute();
        $signalStats = [];
        foreach ($stmt->fetchAll() as $row) {
            $key = $row['asset_class'] . ':' . $row['instrument_type'];
            $signalStats[$key] = [
                'signal_count' => (int) $row['signal_count'],
                'latest_signal' => $row['latest_signal'],
            ];
        }

        // 5. Query active recommendations per asset class
        $stmt = $db->prepare(
            'SELECT i.asset_class, i.instrument_type,
                    COUNT(*) as rec_count,
                    MAX(r.created_at) as latest_rec
             FROM analytics.recommendation r
             INNER JOIN market_master.instrument i ON r.instrument_id = i.instrument_id
             WHERE r.status = "ACTIVE"
             GROUP BY i.asset_class, i.instrument_type'
        );
        $stmt->execute();
        $recStats = [];
        foreach ($stmt->fetchAll() as $row) {
            $key = $row['asset_class'] . ':' . $row['instrument_type'];
            $recStats[$key] = [
                'rec_count' => (int) $row['rec_count'],
                'latest_rec' => $row['latest_rec'],
            ];
        }

        // 6. Query portfolio positions per asset class (no market_value column, use unrealized_pnl)
        $stmt = $db->prepare(
            'SELECT i.asset_class, i.instrument_type,
                    COUNT(*) as position_count,
                    SUM(p.quantity) as total_quantity,
                    SUM(p.unrealized_pnl) as total_unrealized_pnl
             FROM portfolio.position p
             INNER JOIN market_master.instrument i ON p.instrument_id = i.instrument_id
             WHERE p.status = "OPEN"
             GROUP BY i.asset_class, i.instrument_type'
        );
        $stmt->execute();
        $positionStats = [];
        foreach ($stmt->fetchAll() as $row) {
            $key = $row['asset_class'] . ':' . $row['instrument_type'];
            $positionStats[$key] = [
                'position_count' => (int) $row['position_count'],
                'total_quantity' => (float) $row['total_quantity'],
                'total_unrealized_pnl' => (float) $row['total_unrealized_pnl'],
            ];
        }

        // 7. Query exchange list
        $stmt = $db->prepare(
            'SELECT name, country, mic_code, currency, status
             FROM market_master.exchange WHERE status = "ACTIVE" ORDER BY name'
        );
        $stmt->execute();
        $exchanges = $stmt->fetchAll();

        // 8. Query latest recommendations with action
        $stmt = $db->prepare(
            'SELECT r.recommendation_id, r.action, r.confidence, r.confidence_level, r.status, r.created_at,
                    i.asset_class, i.instrument_type, l.ticker, iss.short_name
             FROM analytics.recommendation r
             INNER JOIN market_master.instrument i ON r.instrument_id = i.instrument_id
             LEFT JOIN market_master.listing l ON i.instrument_id = l.instrument_id
             LEFT JOIN market_master.security s ON i.security_id = s.security_id
             LEFT JOIN market_master.issuer iss ON s.issuer_id = iss.issuer_id
             ORDER BY r.created_at DESC LIMIT 20'
        );
        $stmt->execute();
        $recentRecommendations = $stmt->fetchAll();

        // 9. Query latest signals (signal has no status column, use invalidated_at)
        $stmt = $db->prepare(
            'SELECT sig.signal_id, sig.signal_type, sig.direction, sig.strength, sig.created_at,
                    sig.invalidated_at, i.asset_class, i.instrument_type, l.ticker
             FROM analytics.signal sig
             INNER JOIN market_master.instrument i ON sig.instrument_id = i.instrument_id
             LEFT JOIN market_master.listing l ON i.instrument_id = l.instrument_id
             ORDER BY sig.created_at DESC LIMIT 20'
        );
        $stmt->execute();
        $recentSignals = $stmt->fetchAll();

        // 10. Build the response by merging static definitions with live data
        $result = [];
        foreach ($marketTypes as $mt) {
            $key = $mt['asset_class'] . ':' . $mt['instrument_type'];
            $result[] = [
                'asset_class' => $mt['asset_class'],
                'instrument_type' => $mt['instrument_type'],
                'market_name' => $mt['market_name'],
                'description' => $mt['description'],
                'capabilities' => $mt['capabilities'],
                'data_source' => $mt['data_source'],
                'instrument_count' => $instrumentCounts[$key] ?? 0,
                'ohlcv' => $ohlcvStats[$key] ?? null,
                'signals' => $signalStats[$key] ?? null,
                'recommendations' => $recStats[$key] ?? null,
                'positions' => $positionStats[$key] ?? null,
                'is_active' => ($instrumentCounts[$key] ?? 0) > 0,
                'has_live_data' => isset($ohlcvStats[$key]),
            ];
        }

        // 11. Summary stats
        $totalInstruments = array_sum($instrumentCounts);
        $totalWithLiveData = count(array_filter($ohlcvStats, fn($s) => $s['total_records'] > 0));
        $activeMarketTypes = count(array_filter($result, fn($r) => $r['is_active']));

        return Response::ok([
            'market_types' => $result,
            'exchanges' => $exchanges,
            'recent_recommendations' => $recentRecommendations,
            'recent_signals' => $recentSignals,
            'summary' => [
                'total_supported_types' => count($marketTypes),
                'active_market_types' => $activeMarketTypes,
                'total_instruments' => $totalInstruments,
                'instruments_with_live_data' => $totalWithLiveData,
                'total_exchanges' => count($exchanges),
            ],
        ]);
    }

    private const MARKET_TYPES = [
        [
            'asset_class' => 'EQUITY',
            'instrument_type' => 'STOCK',
            'market_name' => 'Pasar Saham (Equity Market)',
            'description' => 'Individual stocks from IDX and global exchanges',
            'capabilities' => ['ohlcv', 'indicators', 'signals', 'screening', 'valuation', 'trading', 'portfolio', 'risk', 'backtest'],
            'data_source' => 'Yahoo Finance',
        ],
        [
            'asset_class' => 'EQUITY',
            'instrument_type' => 'ETF',
            'market_name' => 'Pasar ETF (Exchange Traded Fund)',
            'description' => 'Exchange-traded funds tracking indices, sectors, or regions',
            'capabilities' => ['ohlcv', 'indicators', 'signals', 'screening', 'portfolio', 'risk', 'backtest'],
            'data_source' => 'Yahoo Finance',
        ],
        [
            'asset_class' => 'EQUITY',
            'instrument_type' => 'MUTUAL_FUND',
            'market_name' => 'Pasar Reksa Dana (Mutual Fund)',
            'description' => 'Managed investment funds (reksa dana) - equity, fixed income, mixed',
            'capabilities' => ['portfolio', 'nav_tracking', 'risk'],
            'data_source' => 'Manual / External API',
        ],
        [
            'asset_class' => 'INDEX',
            'instrument_type' => 'EQUITY_INDEX',
            'market_name' => 'Pasar Indeks Saham (Stock Index Market)',
            'description' => 'Global equity indices (S&P 500, Nikkei, Hang Seng, JKSE, etc.)',
            'capabilities' => ['ohlcv', 'indicators', 'regime', 'factors', 'screening'],
            'data_source' => 'Yahoo Finance',
        ],
        [
            'asset_class' => 'INDEX',
            'instrument_type' => 'VOLATILITY_INDEX',
            'market_name' => 'Pasar Indeks Volatilitas (Volatility Index)',
            'description' => 'CBOE VIX and similar volatility indices',
            'capabilities' => ['ohlcv', 'indicators', 'factors', 'risk'],
            'data_source' => 'Yahoo Finance',
        ],
        [
            'asset_class' => 'COMMODITY',
            'instrument_type' => 'FUTURE',
            'market_name' => 'Pasar Komoditas (Commodity Futures)',
            'description' => 'Gold, crude oil, silver futures contracts',
            'capabilities' => ['ohlcv', 'indicators', 'signals', 'factors', 'risk', 'backtest'],
            'data_source' => 'Yahoo Finance',
        ],
        [
            'asset_class' => 'CURRENCY',
            'instrument_type' => 'FX_PAIR',
            'market_name' => 'Pasar Valuta Asing (Forex Market)',
            'description' => 'Foreign exchange pairs (USD/IDR, EUR/USD, USD/JPY)',
            'capabilities' => ['ohlcv', 'indicators', 'signals', 'factors', 'risk', 'backtest'],
            'data_source' => 'Yahoo Finance',
        ],
        [
            'asset_class' => 'FIXED_INCOME',
            'instrument_type' => 'BOND_YIELD',
            'market_name' => 'Pasar Obligasi (Bond Yield Market)',
            'description' => 'Government bond yields (US 10Y Treasury, etc.)',
            'capabilities' => ['ohlcv', 'indicators', 'factors', 'risk'],
            'data_source' => 'Yahoo Finance',
        ],
        [
            'asset_class' => 'FIXED_INCOME',
            'instrument_type' => 'SUKUK',
            'market_name' => 'Pasar Sukuk (Sukuk Market)',
            'description' => 'Indonesia government sukuk (sharia-compliant bonds)',
            'capabilities' => ['portfolio', 'risk', 'yield_tracking'],
            'data_source' => 'Manual / External API',
        ],
        [
            'asset_class' => 'FIXED_INCOME',
            'instrument_type' => 'REPO',
            'market_name' => 'Pasar Repo (Repurchase Agreement)',
            'description' => 'Short-term repurchase agreements for liquidity management',
            'capabilities' => ['portfolio', 'risk'],
            'data_source' => 'Manual / OTC',
        ],
        [
            'asset_class' => 'CRYPTO',
            'instrument_type' => 'SPOT',
            'market_name' => 'Pasar Crypto (Cryptocurrency Market)',
            'description' => 'Spot cryptocurrency trading (Bitcoin, Ethereum)',
            'capabilities' => ['ohlcv', 'indicators', 'signals', 'risk', 'backtest'],
            'data_source' => 'Yahoo Finance',
        ],
        [
            'asset_class' => 'DERIVATIVE',
            'instrument_type' => 'OPTION',
            'market_name' => 'Pasar Opsi (Options Market)',
            'description' => 'Equity options for hedging and speculation',
            'capabilities' => ['portfolio', 'risk', 'greeks'],
            'data_source' => 'Manual / External API',
        ],
        [
            'asset_class' => 'DERIVATIVE',
            'instrument_type' => 'SWAP',
            'market_name' => 'Pasar Swap (Swap Market)',
            'description' => 'Interest rate and currency swaps for hedging',
            'capabilities' => ['portfolio', 'risk'],
            'data_source' => 'Manual / OTC',
        ],
        [
            'asset_class' => 'MIXED',
            'instrument_type' => 'MUTUAL_FUND',
            'market_name' => 'Reksa Dana Campuran (Mixed Mutual Fund)',
            'description' => 'Diversified mutual funds with equity and fixed income components',
            'capabilities' => ['portfolio', 'risk'],
            'data_source' => 'Manual / External API',
        ],
    ];
}
