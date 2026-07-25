<?php

declare(strict_types=1);

namespace Platform\DataIngestion;

use Platform\Core\BaseService;
use Platform\Core\Database\TimescaleDbService;
use Platform\Core\EventBus\EventBus;
use Platform\Core\Exceptions\ApiException;

final class DataIngestionService extends BaseService implements DataIngestionServiceInterface
{
    public function ingestOhlcv(array $data): array
    {
        $required = ['instrument_id', 'trade_date', 'open', 'high', 'low', 'close'];
        foreach ($required as $field) {
            if (!array_key_exists($field, $data)) {
                throw new ApiException(
                    422,
                    'VALIDATION_ERROR',
                    "Field {$field} is required",
                    [$field => 'Required']
                );
            }
        }

        $id = $this->uuid();
        $now = $this->now();

        $sql = 'INSERT INTO data_ingestion.ohlcv_daily
            (ohlcv_id, instrument_id, trade_date, open, high, low, close,
             volume, adjusted_close, source, created_at)
            VALUES
            (:id, :instrument_id, :trade_date, :open, :high, :low, :close,
             :volume, :adjusted_close, :source, :created_at)';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id' => $id,
            ':instrument_id' => $data['instrument_id'],
            ':trade_date' => $data['trade_date'],
            ':open' => $data['open'],
            ':high' => $data['high'],
            ':low' => $data['low'],
            ':close' => $data['close'],
            ':volume' => $data['volume'] ?? 0,
            ':adjusted_close' => $data['adjusted_close'] ?? null,
            ':source' => $data['source'] ?? 'MANUAL',
            ':created_at' => $now,
        ]);

        // Dual-write to TimescaleDB if available
        $tsdb = TimescaleDbService::getInstance();
        if ($tsdb->isAvailable()) {
            $tsdb->upsertOhlcvDaily([
                'instrument_id' => $data['instrument_id'],
                'exchange_id' => $data['exchange_id'] ?? 'IDX',
                'date' => $data['trade_date'],
                'open' => $data['open'],
                'high' => $data['high'],
                'low' => $data['low'],
                'close' => $data['close'],
                'volume' => $data['volume'] ?? 0,
                'adjusted_close' => $data['adjusted_close'] ?? null,
                'source' => $data['source'] ?? 'MANUAL',
            ]);
        }

        // Emit event (fail-safe)
        EventBus::getInstance()->emit('data.ohlcv.ingested', [
            'ohlcv_id' => $id,
            'instrument_id' => $data['instrument_id'],
            'trade_date' => $data['trade_date'],
        ]);

        return $this->getOhlcv($id);
    }

    public function getOhlcv(string $id): ?array
    {
        $sql = 'SELECT * FROM data_ingestion.ohlcv_daily WHERE ohlcv_id = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function listOhlcv(array $filters, int $page, int $perPage): array
    {
        [$page, $perPage, $offset] = $this->parsePagination(
            ['page' => $page, 'per_page' => $perPage]
        );

        $where = [];
        $params = [];

        if (isset($filters['instrument_id'])) {
            $where[] = 'instrument_id = :instrument_id';
            $params[':instrument_id'] = $filters['instrument_id'];
        }
        if (isset($filters['source'])) {
            $where[] = 'source = :source';
            $params[':source'] = $filters['source'];
        }
        if (isset($filters['from_date'])) {
            $where[] = 'trade_date >= :from_date';
            $params[':from_date'] = $filters['from_date'];
        }
        if (isset($filters['to_date'])) {
            $where[] = 'trade_date <= :to_date';
            $params[':to_date'] = $filters['to_date'];
        }

        $whereClause = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

        $countSql = "SELECT COUNT(*) FROM data_ingestion.ohlcv_daily {$whereClause}";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT * FROM data_ingestion.ohlcv_daily {$whereClause} "
            . "ORDER BY trade_date DESC LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll();

        return $this->paginate($items, $total, $page, $perPage);
    }

    public function getOhlcvHistory(
        string $instrumentId,
        ?string $fromDate,
        ?string $toDate
    ): array {
        $where = ['instrument_id = :instrument_id'];
        $params = [':instrument_id' => $instrumentId];

        if ($fromDate !== null) {
            $where[] = 'trade_date >= :from_date';
            $params[':from_date'] = $fromDate;
        }
        if ($toDate !== null) {
            $where[] = 'trade_date <= :to_date';
            $params[':to_date'] = $toDate;
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);

        $sql = "SELECT * FROM data_ingestion.ohlcv_daily {$whereClause} "
            . 'ORDER BY trade_date ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getIngestionStatus(): array
    {
        $totalSql = 'SELECT COUNT(*) FROM data_ingestion.ohlcv_daily';
        $totalStmt = $this->db->prepare($totalSql);
        $totalStmt->execute();
        $totalRecords = (int) $totalStmt->fetchColumn();

        $sourceSql = 'SELECT source, COUNT(*) as cnt '
            . 'FROM data_ingestion.ohlcv_daily GROUP BY source';
        $sourceStmt = $this->db->prepare($sourceSql);
        $sourceStmt->execute();
        $bySource = $sourceStmt->fetchAll();

        $latestSql = 'SELECT MAX(trade_date) as latest_date '
            . 'FROM data_ingestion.ohlcv_daily';
        $latestStmt = $this->db->prepare($latestSql);
        $latestStmt->execute();
        $latest = $latestStmt->fetch();

        return [
            'total_records' => $totalRecords,
            'by_source' => $bySource,
            'latest_trade_date' => $latest ? ($latest['latest_date'] ?? null) : null,
        ];
    }

    /**
     * Run data quality checks on OHLCV data for an instrument.
     *
     * Checks:
     * - Missing dates (gaps in trading calendar)
     * - OHLC consistency (high >= low, high >= open/close, low <= open/close)
     * - Zero or negative prices
     * - Duplicate dates
     * - Volume anomalies (zero volume days)
     *
     * @param string $instrumentId
     * @return array{instrument_id: string, checks: array, total_issues: int, passed: bool}
     */
    public function runDataQualityChecks(string $instrumentId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM data_ingestion.ohlcv_daily
             WHERE instrument_id = :id ORDER BY trade_date ASC'
        );
        $stmt->execute([':id' => $instrumentId]);
        $rows = $stmt->fetchAll();

        $checks = [];
        $totalIssues = 0;

        if (count($rows) === 0) {
            return [
                'instrument_id' => $instrumentId,
                'checks' => [
                    ['check' => 'data_exists', 'status' => 'FAIL', 'detail' => 'No OHLCV data found'],
                ],
                'total_issues' => 1,
                'passed' => false,
            ];
        }

        $dates = array_map(fn($r) => $r['trade_date'], $rows);
        $dateSet = array_flip($dates);

        $missingDates = [];
        for ($i = 1; $i < count($dates); $i++) {
            $prev = strtotime($dates[$i - 1]);
            $curr = strtotime($dates[$i]);
            $diff = ($curr - $prev) / 86400;
            if ($diff > 3) {
                for ($d = $prev + 86400; $d < $curr; $d += 86400) {
                    $weekday = date('N', $d);
                    if ($weekday <= 5) {
                        $missingDates[] = date('Y-m-d', $d);
                    }
                }
            }
        }
        $checks[] = [
            'check' => 'missing_dates',
            'status' => count($missingDates) === 0 ? 'PASS' : 'WARN',
            'detail' => count($missingDates) > 0
                ? count($missingDates) . ' potential missing dates (excluding weekends)'
                : 'No gaps detected',
        ];
        $totalIssues += count($missingDates);

        $ohlcViolations = [];
        $zeroPrices = [];
        foreach ($rows as $r) {
            $open = (float) $r['open'];
            $high = (float) $r['high'];
            $low = (float) $r['low'];
            $close = (float) $r['close'];

            if ($open <= 0 || $high <= 0 || $low <= 0 || $close <= 0) {
                $zeroPrices[] = $r['trade_date'];
            }
            if ($high < $low || $high < $open || $high < $close || $low > $open || $low > $close) {
                $ohlcViolations[] = $r['trade_date'];
            }
        }
        $checks[] = [
            'check' => 'ohlc_consistency',
            'status' => count($ohlcViolations) === 0 ? 'PASS' : 'FAIL',
            'detail' => count($ohlcViolations) > 0
                ? count($ohlcViolations) . ' OHLC consistency violations'
                : 'All OHLC values are consistent',
        ];
        $totalIssues += count($ohlcViolations);

        $checks[] = [
            'check' => 'zero_or_negative_prices',
            'status' => count($zeroPrices) === 0 ? 'PASS' : 'FAIL',
            'detail' => count($zeroPrices) > 0
                ? count($zeroPrices) . ' records with zero or negative prices'
                : 'All prices are positive',
        ];
        $totalIssues += count($zeroPrices);

        $duplicates = count($dates) - count(array_unique($dates));
        $checks[] = [
            'check' => 'duplicate_dates',
            'status' => $duplicates === 0 ? 'PASS' : 'FAIL',
            'detail' => $duplicates > 0
                ? $duplicates . ' duplicate dates found'
                : 'No duplicate dates',
        ];
        $totalIssues += $duplicates;

        $zeroVolumeDays = array_filter($rows, fn($r) => (int) $r['volume'] === 0);
        $checks[] = [
            'check' => 'zero_volume_days',
            'status' => count($zeroVolumeDays) === 0 ? 'PASS' : 'WARN',
            'detail' => count($zeroVolumeDays) > 0
                ? count($zeroVolumeDays) . ' days with zero volume'
                : 'All days have non-zero volume',
        ];

        return [
            'instrument_id' => $instrumentId,
            'total_records' => count($rows),
            'date_range' => [
                'from' => $dates[0],
                'to' => $dates[count($dates) - 1],
            ],
            'checks' => $checks,
            'total_issues' => $totalIssues,
            'passed' => $totalIssues === 0,
        ];
    }

    /**
     * Fetch OHLCV data from an external market data provider.
     *
     * Supported providers:
     * - yahoo: Yahoo Finance (free, no API key required)
     * - alphavantage: Alpha Vantage (requires API key in config)
     * - financialmodelingprep: Financial Modeling Prep (requires API key)
     *
     * @param string $provider Provider name (yahoo, alphavantage, financialmodelingprep)
     * @param string $symbol Ticker symbol (e.g. BBCA.JK for Yahoo Finance IDX stocks)
     * @param ?string $fromDate Start date (Y-m-d)
     * @param ?string $toDate End date (Y-m-d)
     * @return array{provider: string, symbol: string, records_ingested: int, date_range: array}
     */
    public function fetchFromExternal(
        string $provider,
        string $symbol,
        ?string $fromDate = null,
        ?string $toDate = null
    ): array {
        $provider = strtolower($provider);

        if ($fromDate === null) {
            $fromDate = date('Y-m-d', strtotime('-30 days'));
        }
        if ($toDate === null) {
            $toDate = date('Y-m-d');
        }

        $ohlcData = match ($provider) {
            'yahoo' => $this->fetchFromYahoo($symbol, $fromDate, $toDate),
            'alphavantage' => $this->fetchFromAlphaVantage($symbol, $fromDate, $toDate),
            'financialmodelingprep', 'fmp' => $this->fetchFromFMP($symbol, $fromDate, $toDate),
            default => throw new ApiException(
                422,
                'UNSUPPORTED_PROVIDER',
                "Provider '{$provider}' is not supported. Use: yahoo, alphavantage, financialmodelingprep"
            ),
        };

        $instrumentId = $this->resolveInstrumentId($symbol);
        if ($instrumentId === null) {
            throw new ApiException(
                404,
                'INSTRUMENT_NOT_FOUND',
                "No instrument found for symbol '{$symbol}'. Create the instrument first."
            );
        }

        $ingested = 0;
        foreach ($ohlcData as $bar) {
            $existing = $this->db->prepare(
                'SELECT ohlcv_id FROM data_ingestion.ohlcv_daily
                 WHERE instrument_id = :inst AND trade_date = :date'
            );
            $existing->execute([
                ':inst' => $instrumentId,
                ':date' => $bar['trade_date'],
            ]);
            if ($existing->fetch() !== false) {
                continue;
            }

            $this->ingestOhlcv([
                'instrument_id' => $instrumentId,
                'trade_date' => $bar['trade_date'],
                'open' => $bar['open'],
                'high' => $bar['high'],
                'low' => $bar['low'],
                'close' => $bar['close'],
                'volume' => $bar['volume'] ?? 0,
                'adjusted_close' => $bar['adjusted_close'] ?? null,
                'source' => strtoupper($provider),
            ]);
            $ingested++;
        }

        return [
            'provider' => $provider,
            'symbol' => $symbol,
            'instrument_id' => $instrumentId,
            'records_ingested' => $ingested,
            'records_skipped' => count($ohlcData) - $ingested,
            'date_range' => [
                'from' => $fromDate,
                'to' => $toDate,
            ],
        ];
    }

    /**
     * Fetch OHLCV from Yahoo Finance chart API.
     *
     * @param string $symbol Yahoo Finance symbol (e.g. BBCA.JK)
     * @param string $fromDate
     * @param string $toDate
     * @return array<int, array{trade_date: string, open: float, high: float, low: float, close: float, volume: int, adjusted_close: float}>
     */
    private function fetchFromYahoo(string $symbol, string $fromDate, string $toDate): array
    {
        $period1 = strtotime($fromDate);
        $period2 = strtotime($toDate) + 86400;

        $url = "https://query1.finance.yahoo.com/v8/finance/chart/"
            . urlencode($symbol)
            . "?period1={$period1}&period2={$period2}"
            . "&interval=1d&includeAdjustedClose=true";

        $response = $this->httpGetJson($url, [
            'User-Agent: Mozilla/5.0 (Platform Trading Bot)',
        ]);

        if (!isset($response['chart']['result'][0]['timestamp'])) {
            throw new ApiException(
                502,
                'EXTERNAL_API_ERROR',
                "Yahoo Finance returned no data for symbol '{$symbol}'"
            );
        }

        $result = $response['chart']['result'][0];
        $timestamps = $result['timestamp'];
        $quote = $result['indicators']['quote'][0];
        $adjClose = $result['indicators']['adjclose'][0]['adjclose'] ?? [];

        $bars = [];
        foreach ($timestamps as $i => $ts) {
            if (!isset($quote['open'][$i], $quote['close'][$i])) {
                continue;
            }
            $bars[] = [
                'trade_date' => date('Y-m-d', $ts),
                'open' => (float) $quote['open'][$i],
                'high' => (float) $quote['high'][$i],
                'low' => (float) $quote['low'][$i],
                'close' => (float) $quote['close'][$i],
                'volume' => (int) ($quote['volume'][$i] ?? 0),
                'adjusted_close' => isset($adjClose[$i]) ? (float) $adjClose[$i] : null,
            ];
        }

        return $bars;
    }

    /**
     * Fetch OHLCV from Alpha Vantage TIME_SERIES_DAILY.
     *
     * @param string $symbol
     * @param string $fromDate
     * @param string $toDate
     * @return array<int, array{trade_date: string, open: float, high: float, low: float, close: float, volume: int, adjusted_close: ?float}>
     */
    private function fetchFromAlphaVantage(string $symbol, string $fromDate, string $toDate): array
    {
        $apiKey = $this->getConfig('alphavantage_api_key', '');
        if ($apiKey === '') {
            throw new ApiException(
                422,
                'MISSING_API_KEY',
                'Alpha Vantage requires an API key. Set alphavantage_api_key in config.'
            );
        }

        $url = "https://www.alphavantage.co/query"
            . "?function=TIME_SERIES_DAILY"
            . "&symbol=" . urlencode($symbol)
            . "&outputsize=full"
            . "&apikey=" . urlencode($apiKey)
            . "&datatype=json";

        $response = $this->httpGetJson($url);

        if (isset($response['Error Message'])) {
            throw new ApiException(502, 'EXTERNAL_API_ERROR', $response['Error Message']);
        }
        if (isset($response['Note'])) {
            throw new ApiException(429, 'RATE_LIMITED', $response['Note']);
        }

        $timeSeries = $response['Time Series (Daily)'] ?? [];
        $bars = [];
        foreach ($timeSeries as $date => $values) {
            if ($date < $fromDate || $date > $toDate) {
                continue;
            }
            $bars[] = [
                'trade_date' => $date,
                'open' => (float) $values['1. open'],
                'high' => (float) $values['2. high'],
                'low' => (float) $values['3. low'],
                'close' => (float) $values['4. close'],
                'volume' => (int) $values['5. volume'],
                'adjusted_close' => null,
            ];
        }

        usort($bars, fn($a, $b) => strcmp($a['trade_date'], $b['trade_date']));
        return $bars;
    }

    /**
     * Fetch OHLCV from Financial Modeling Prep.
     *
     * @param string $symbol
     * @param string $fromDate
     * @param string $toDate
     * @return array<int, array{trade_date: string, open: float, high: float, low: float, close: float, volume: int, adjusted_close: ?float}>
     */
    private function fetchFromFMP(string $symbol, string $fromDate, string $toDate): array
    {
        $apiKey = $this->getConfig('fmp_api_key', '');
        if ($apiKey === '') {
            throw new ApiException(
                422,
                'MISSING_API_KEY',
                'Financial Modeling Prep requires an API key. Set fmp_api_key in config.'
            );
        }

        $url = "https://financialmodelingprep.com/api/v3/historical-price-full/"
            . urlencode($symbol)
            . "?from=" . urlencode($fromDate)
            . "&to=" . urlencode($toDate)
            . "&apikey=" . urlencode($apiKey);

        $response = $this->httpGetJson($url);

        if (isset($response['Error Message'])) {
            throw new ApiException(502, 'EXTERNAL_API_ERROR', $response['Error Message']);
        }

        $historical = $response['historical'] ?? [];
        $bars = [];
        foreach ($historical as $bar) {
            $bars[] = [
                'trade_date' => $bar['date'],
                'open' => (float) $bar['open'],
                'high' => (float) $bar['high'],
                'low' => (float) $bar['low'],
                'close' => (float) $bar['close'],
                'volume' => (int) ($bar['volume'] ?? 0),
                'adjusted_close' => isset($bar['adjClose']) ? (float) $bar['adjClose'] : null,
            ];
        }

        usort($bars, fn($a, $b) => strcmp($a['trade_date'], $b['trade_date']));
        return $bars;
    }

    /**
     * Resolve a market symbol to an instrument_id in the database.
     */
    private function resolveInstrumentId(string $symbol): ?string
    {
        $stmt = $this->db->prepare(
            'SELECT instrument_id FROM market.instrument
             WHERE symbol = :symbol OR ticker = :symbol LIMIT 1'
        );
        $stmt->execute([':symbol' => $symbol]);
        $row = $stmt->fetch();
        return $row === false ? null : $row['instrument_id'];
    }

    /**
     * Get a configuration value from the config table or env.
     */
    private function getConfig(string $key, string $default = ''): string
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT config_value FROM core.config WHERE config_key = :key LIMIT 1'
            );
            $stmt->execute([':key' => $key]);
            $row = $stmt->fetch();
            if ($row !== false && !empty($row['config_value'])) {
                return $row['config_value'];
            }
        } catch (\Throwable) {
        }
        $envVal = getenv(strtoupper($key));
        return $envVal !== false ? $envVal : $default;
    }

    /**
     * HTTP GET request returning parsed JSON.
     *
     * @param string $url
     * @param array<int, string> $headers
     * @return array<string, mixed>
     */
    private function httpGetJson(string $url, array $headers = []): array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => $headers !== [] ? $headers : ['Accept: application/json'],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new ApiException(
                502,
                'EXTERNAL_API_ERROR',
                "Failed to connect to external API: {$error}"
            );
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            throw new ApiException(
                502,
                'EXTERNAL_API_ERROR',
                "External API returned invalid JSON (HTTP {$httpCode})"
            );
        }

        return $data;
    }
}
