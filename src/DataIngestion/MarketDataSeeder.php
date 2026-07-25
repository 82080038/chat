<?php
/**
 * Market Data Seeder
 *
 * Fetches historical OHLCV data from Yahoo Finance for:
 * - IDX stocks (BBCA.JK, BBRI.JK, etc.)
 * - Global indices (^JKSE, ^GSPC, ^DJI, ^IXIC, ^N225, ^HSI)
 * - Commodities (GC=F gold, CL=F oil, SI=F silver)
 * - FX pairs (IDR=X, EURUSD=X, JPY=X)
 * - Bond yields (^TNX)
 * - Volatility (^VIX)
 *
 * Features:
 * - Rate limiter: configurable delay between requests (default 2s)
 * - Lookback: 2 years (730 days) for sufficient technical indicator data
 * - Auto-creates instruments if not exists
 * - Idempotent: skips already-ingested dates (INSERT IGNORE)
 */

declare(strict_types=1);

namespace Platform\DataIngestion;

use Platform\Core\Database\MySqlConnection;

final class MarketDataSeeder
{
    private \PDO $db;
    private int $delayMicroseconds;
    private int $lookbackDays;
    private array $symbols;

    /** @var array<int, array{yahoo: string, ticker: string, name: string, asset_class: string, instrument_type: string, currency: string, exchange_mic: string}> */
    private const DEFAULT_SYMBOLS = [
        // IDX Stocks
        ['yahoo' => 'BBCA.JK', 'ticker' => 'BBCA', 'name' => 'Bank Central Asia', 'asset_class' => 'EQUITY', 'instrument_type' => 'COMMON_STOCK', 'currency' => 'IDR', 'exchange_mic' => 'XIDX'],
        ['yahoo' => 'BBRI.JK', 'ticker' => 'BBRI', 'name' => 'Bank Rakyat Indonesia', 'asset_class' => 'EQUITY', 'instrument_type' => 'COMMON_STOCK', 'currency' => 'IDR', 'exchange_mic' => 'XIDX'],
        ['yahoo' => 'BMRI.JK', 'ticker' => 'BMRI', 'name' => 'Bank Mandiri', 'asset_class' => 'EQUITY', 'instrument_type' => 'COMMON_STOCK', 'currency' => 'IDR', 'exchange_mic' => 'XIDX'],
        ['yahoo' => 'TLKM.JK', 'ticker' => 'TLKM', 'name' => 'Telkom Indonesia', 'asset_class' => 'EQUITY', 'instrument_type' => 'COMMON_STOCK', 'currency' => 'IDR', 'exchange_mic' => 'XIDX'],
        ['yahoo' => 'ASII.JK', 'ticker' => 'ASII', 'name' => 'Astra International', 'asset_class' => 'EQUITY', 'instrument_type' => 'COMMON_STOCK', 'currency' => 'IDR', 'exchange_mic' => 'XIDX'],
        ['yahoo' => 'GOTO.JK', 'ticker' => 'GOTO', 'name' => 'GoTo Gojek Tokopedia', 'asset_class' => 'EQUITY', 'instrument_type' => 'COMMON_STOCK', 'currency' => 'IDR', 'exchange_mic' => 'XIDX'],
        ['yahoo' => 'UNVR.JK', 'ticker' => 'UNVR', 'name' => 'Unilever Indonesia', 'asset_class' => 'EQUITY', 'instrument_type' => 'COMMON_STOCK', 'currency' => 'IDR', 'exchange_mic' => 'XIDX'],
        ['yahoo' => 'KLBF.JK', 'ticker' => 'KLBF', 'name' => 'Kalbe Farma', 'asset_class' => 'EQUITY', 'instrument_type' => 'COMMON_STOCK', 'currency' => 'IDR', 'exchange_mic' => 'XIDX'],
        // Global Indices
        ['yahoo' => '^JKSE', 'ticker' => '^JKSE', 'name' => 'Jakarta Composite Index', 'asset_class' => 'INDEX', 'instrument_type' => 'EQUITY_INDEX', 'currency' => 'IDR', 'exchange_mic' => 'XIDX'],
        ['yahoo' => '^GSPC', 'ticker' => '^GSPC', 'name' => 'S&P 500', 'asset_class' => 'INDEX', 'instrument_type' => 'EQUITY_INDEX', 'currency' => 'USD', 'exchange_mic' => 'GLOBAL'],
        ['yahoo' => '^DJI', 'ticker' => '^DJI', 'name' => 'Dow Jones Industrial Average', 'asset_class' => 'INDEX', 'instrument_type' => 'EQUITY_INDEX', 'currency' => 'USD', 'exchange_mic' => 'GLOBAL'],
        ['yahoo' => '^IXIC', 'ticker' => '^IXIC', 'name' => 'Nasdaq Composite', 'asset_class' => 'INDEX', 'instrument_type' => 'EQUITY_INDEX', 'currency' => 'USD', 'exchange_mic' => 'GLOBAL'],
        ['yahoo' => '^N225', 'ticker' => '^N225', 'name' => 'Nikkei 225', 'asset_class' => 'INDEX', 'instrument_type' => 'EQUITY_INDEX', 'currency' => 'JPY', 'exchange_mic' => 'XTKS'],
        ['yahoo' => '^HSI', 'ticker' => '^HSI', 'name' => 'Hang Seng Index', 'asset_class' => 'INDEX', 'instrument_type' => 'EQUITY_INDEX', 'currency' => 'HKD', 'exchange_mic' => 'XHKG'],
        // Commodities
        ['yahoo' => 'GC=F', 'ticker' => 'GC=F', 'name' => 'Gold Futures', 'asset_class' => 'COMMODITY', 'instrument_type' => 'FUTURE', 'currency' => 'USD', 'exchange_mic' => 'XCME'],
        ['yahoo' => 'CL=F', 'ticker' => 'CL=F', 'name' => 'Crude Oil Futures', 'asset_class' => 'COMMODITY', 'instrument_type' => 'FUTURE', 'currency' => 'USD', 'exchange_mic' => 'XCME'],
        ['yahoo' => 'SI=F', 'ticker' => 'SI=F', 'name' => 'Silver Futures', 'asset_class' => 'COMMODITY', 'instrument_type' => 'FUTURE', 'currency' => 'USD', 'exchange_mic' => 'XCME'],
        // FX Pairs
        ['yahoo' => 'IDR=X', 'ticker' => 'IDR=X', 'name' => 'USD/IDR', 'asset_class' => 'CURRENCY', 'instrument_type' => 'FX_PAIR', 'currency' => 'IDR', 'exchange_mic' => 'GLOBAL'],
        ['yahoo' => 'EURUSD=X', 'ticker' => 'EURUSD=X', 'name' => 'EUR/USD', 'asset_class' => 'CURRENCY', 'instrument_type' => 'FX_PAIR', 'currency' => 'USD', 'exchange_mic' => 'GLOBAL'],
        ['yahoo' => 'JPY=X', 'ticker' => 'JPY=X', 'name' => 'USD/JPY', 'asset_class' => 'CURRENCY', 'instrument_type' => 'FX_PAIR', 'currency' => 'JPY', 'exchange_mic' => 'GLOBAL'],
        // Bond Yields
        ['yahoo' => '^TNX', 'ticker' => '^TNX', 'name' => 'US 10-Year Treasury Yield', 'asset_class' => 'FIXED_INCOME', 'instrument_type' => 'BOND_YIELD', 'currency' => 'USD', 'exchange_mic' => 'GLOBAL'],
        // Volatility
        ['yahoo' => '^VIX', 'ticker' => '^VIX', 'name' => 'CBOE Volatility Index', 'asset_class' => 'INDEX', 'instrument_type' => 'VOLATILITY_INDEX', 'currency' => 'USD', 'exchange_mic' => 'GLOBAL'],
    ];

    public function __construct(int $lookbackDays = 730, int $delaySeconds = 2)
    {
        $this->db = MySqlConnection::getInstance();
        $this->lookbackDays = $lookbackDays;
        $this->delayMicroseconds = $delaySeconds * 1_000_000;
        $this->symbols = self::DEFAULT_SYMBOLS;
    }

    /**
     * Run the seeder. If filterSymbol is provided, only fetch that symbol.
     *
     * @return array<int, array{symbol: string, name: string, instrument_id?: string, records_ingested?: int, status: string, error?: string}>
     */
    public function run(?string $filterSymbol = null): array
    {
        $symbols = $this->symbols;
        if ($filterSymbol !== null) {
            $symbols = array_filter(
                $this->symbols,
                fn($s) => strcasecmp($s['yahoo'], $filterSymbol) === 0
            );
        }

        $results = [];
        $total = count($symbols);
        $idx = 0;

        foreach ($symbols as $sym) {
            $idx++;
            $yahooSymbol = $sym['yahoo'];

            try {
                $instrumentId = $this->ensureInstrument($sym);
                $ingested = $this->fetchAndStore($yahooSymbol, $instrumentId);
                $results[] = [
                    'symbol' => $yahooSymbol,
                    'name' => $sym['name'],
                    'instrument_id' => $instrumentId,
                    'records_ingested' => $ingested,
                    'status' => 'OK',
                ];
            } catch (\Throwable $e) {
                $results[] = [
                    'symbol' => $yahooSymbol,
                    'name' => $sym['name'],
                    'status' => 'ERROR',
                    'error' => $e->getMessage(),
                ];
            }

            // Rate limiter: sleep between requests (skip after last)
            if ($idx < $total) {
                usleep($this->delayMicroseconds);
            }
        }

        return $results;
    }

    /**
     * Ensure instrument exists in database. If not, create it.
     */
    private function ensureInstrument(array $sym): string
    {
        $ticker = $sym['ticker'];

        // Try to find existing instrument via market.instrument view
        $stmt = $this->db->prepare(
            'SELECT instrument_id FROM market.instrument
             WHERE ticker = :t1 OR symbol = :t2 LIMIT 1'
        );
        $stmt->execute([':t1' => $ticker, ':t2' => $ticker]);
        $row = $stmt->fetch();

        if ($row !== false) {
            return $row['instrument_id'];
        }

        // Also try listing table directly
        $stmt = $this->db->prepare(
            'SELECT instrument_id FROM market_master.listing
             WHERE ticker = :ticker AND status = "ACTIVE" LIMIT 1'
        );
        $stmt->execute([':ticker' => $ticker]);
        $row = $stmt->fetch();

        if ($row !== false) {
            return $row['instrument_id'];
        }

        // Create new instrument chain: issuer -> security -> instrument -> listing
        $issuerId = $this->uuid();
        $securityId = $this->uuid();
        $instrumentId = $this->uuid();
        $listingId = $this->uuid();
        $now = gmdate('Y-m-d H:i:s');

        // Get exchange ID
        $exchangeStmt = $this->db->prepare(
            'SELECT exchange_id FROM market_master.exchange WHERE mic_code = :mic LIMIT 1'
        );
        $exchangeStmt->execute([':mic' => $sym['exchange_mic']]);
        $exchangeRow = $exchangeStmt->fetch();
        $exchangeId = $exchangeRow !== false ? $exchangeRow['exchange_id'] : null;

        if ($exchangeId === null) {
            $exchangeStmt = $this->db->prepare(
                'SELECT exchange_id FROM market_master.exchange WHERE mic_code = "GLOBAL" LIMIT 1'
            );
            $exchangeStmt->execute();
            $exchangeRow = $exchangeStmt->fetch();
            $exchangeId = $exchangeRow !== false ? $exchangeRow['exchange_id'] : null;
        }

        // Create issuer
        $this->db->prepare(
            'INSERT INTO market_master.issuer (issuer_id, legal_name, short_name, country, sector_code, status)
             VALUES (:id, :name, :short, :country, :sector, "ACTIVE")'
        )->execute([
            ':id' => $issuerId,
            ':name' => $sym['name'],
            ':short' => strtoupper(substr($sym['name'], 0, 10)),
            ':country' => $sym['exchange_mic'] === 'XIDX' ? 'ID' : 'XX',
            ':sector' => $sym['asset_class'],
        ]);

        // Create security
        $this->db->prepare(
            'INSERT INTO market_master.security (security_id, issuer_id, security_type, currency, status)
             VALUES (:id, :issuer, :type, :currency, "ACTIVE")'
        )->execute([
            ':id' => $securityId,
            ':issuer' => $issuerId,
            ':type' => $sym['asset_class'] === 'EQUITY' ? 'STOCK' : $sym['asset_class'],
            ':currency' => $sym['currency'],
        ]);

        // Create instrument
        $this->db->prepare(
            'INSERT INTO market_master.instrument (instrument_id, security_id, asset_class, instrument_type, currency, status, status_changed_at)
             VALUES (:id, :sec, :class, :type, :currency, "ACTIVE", :now)'
        )->execute([
            ':id' => $instrumentId,
            ':sec' => $securityId,
            ':class' => $sym['asset_class'],
            ':type' => $sym['instrument_type'],
            ':currency' => $sym['currency'],
            ':now' => $now,
        ]);

        // Create listing
        if ($exchangeId !== null) {
            $this->db->prepare(
                'INSERT INTO market_master.listing (listing_id, instrument_id, exchange_id, ticker, currency, status)
                 VALUES (:id, :inst, :exch, :ticker, :currency, "ACTIVE")'
            )->execute([
                ':id' => $listingId,
                ':inst' => $instrumentId,
                ':exch' => $exchangeId,
                ':ticker' => $ticker,
                ':currency' => $sym['currency'],
            ]);
        }

        return $instrumentId;
    }

    /**
     * Fetch OHLCV from Yahoo Finance and store in database.
     */
    private function fetchAndStore(string $yahooSymbol, string $instrumentId): int
    {
        $fromDate = date('Y-m-d', strtotime("-{$this->lookbackDays} days"));
        $toDate = date('Y-m-d');

        $period1 = strtotime($fromDate);
        $period2 = strtotime($toDate) + 86400;

        $url = "https://query1.finance.yahoo.com/v8/finance/chart/"
            . urlencode($yahooSymbol)
            . "?period1={$period1}&period2={$period2}"
            . "&interval=1d&includeAdjustedClose=true";

        $response = $this->httpGetJson($url, [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        ]);

        if (!isset($response['chart']['result'][0]['timestamp'])) {
            throw new \RuntimeException("Yahoo Finance returned no data for '{$yahooSymbol}'");
        }

        $result = $response['chart']['result'][0];
        $timestamps = $result['timestamp'];
        $quote = $result['indicators']['quote'][0];
        $adjClose = $result['indicators']['adjclose'][0]['adjclose'] ?? [];

        $ingested = 0;
        $insertStmt = $this->db->prepare(
            'INSERT IGNORE INTO data_ingestion.ohlcv_daily
             (ohlcv_id, instrument_id, trade_date, open, high, low, close, volume, adjusted_close, source, created_at)
             VALUES (:id, :inst, :date, :open, :high, :low, :close, :vol, :adj, :src, :now)'
        );

        foreach ($timestamps as $i => $ts) {
            if (!isset($quote['open'][$i], $quote['close'][$i])) {
                continue;
            }
            $date = date('Y-m-d', $ts);
            $open = (float) $quote['open'][$i];
            $high = (float) ($quote['high'][$i] ?? $open);
            $low = (float) ($quote['low'][$i] ?? $open);
            $close = (float) $quote['close'][$i];
            $volume = (int) ($quote['volume'][$i] ?? 0);
            $adj = isset($adjClose[$i]) ? (float) $adjClose[$i] : null;

            $insertStmt->execute([
                ':id' => $this->uuid(),
                ':inst' => $instrumentId,
                ':date' => $date,
                ':open' => $open,
                ':high' => $high,
                ':low' => $low,
                ':close' => $close,
                ':vol' => $volume,
                ':adj' => $adj,
                ':src' => 'YAHOO',
                ':now' => gmdate('Y-m-d H:i:s'),
            ]);

            if ($insertStmt->rowCount() > 0) {
                $ingested++;
            }
        }

        return $ingested;
    }

    private function httpGetJson(string $url, array $headers = []): array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => $headers !== [] ? $headers : ['Accept: application/json'],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException("HTTP request failed: {$error}");
        }

        if ($httpCode === 429) {
            throw new \RuntimeException("Yahoo Finance rate limited (HTTP 429). Increase delay between requests.");
        }

        if ($httpCode >= 400) {
            throw new \RuntimeException("Yahoo Finance returned HTTP {$httpCode}");
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            throw new \RuntimeException("Yahoo Finance returned invalid JSON");
        }

        return $data;
    }

    private function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
