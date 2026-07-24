<?php

declare(strict_types=1);

namespace Platform\Core\Database;

use PDO;
use Platform\Core\Application;

/**
 * TimescaleDB service for time-series data operations.
 * Provides OHLCV, tick, quote, technical indicator storage and retrieval
 * using PostgreSQL + TimescaleDB hypertables.
 *
 * Fail-safe: if PostgreSQL is unavailable, all operations return empty results
 * and writes are silently skipped. The application continues to work with MySQL.
 */
final class TimescaleDbService
{
    private static ?TimescaleDbService $instance = null;
    private ?PDO $db;

    private function __construct()
    {
        $this->db = PgSqlConnection::getInstance();
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
        return $this->db !== null;
    }

    // ─── OHLCV Daily ──────────────────────────────────────────────────

    /**
     * Insert or update OHLCV daily data.
     *
     * @param array<string, mixed> $data
     */
    public function upsertOhlcvDaily(array $data): ?array
    {
        if ($this->db === null) {
            return null;
        }

        $sql = 'INSERT INTO ohlcv.ohlcv_daily
            (instrument_id, exchange_id, date, open, high, low, close, volume, adjusted_close, source)
            VALUES (:instrument_id, :exchange_id, :date, :open, :high, :low, :close, :volume, :adjusted_close, :source)
            ON CONFLICT (instrument_id, exchange_id, date)
            DO UPDATE SET open = EXCLUDED.open, high = EXCLUDED.high, low = EXCLUDED.low,
                close = EXCLUDED.close, volume = EXCLUDED.volume,
                adjusted_close = EXCLUDED.adjusted_close, source = EXCLUDED.source,
                updated_at = NOW()';

        $this->db->prepare($sql)->execute([
            ':instrument_id' => $data['instrument_id'],
            ':exchange_id' => $data['exchange_id'],
            ':date' => $data['date'],
            ':open' => $data['open'],
            ':high' => $data['high'],
            ':low' => $data['low'],
            ':close' => $data['close'],
            ':volume' => $data['volume'],
            ':adjusted_close' => $data['adjusted_close'] ?? null,
            ':source' => $data['source'] ?? 'manual',
        ]);

        return $data;
    }

    /**
     * Batch upsert OHLCV daily records.
     *
     * @param array<int, array<string, mixed>> $records
     */
    public function batchUpsertOhlcvDaily(array $records): int
    {
        if ($this->db === null || $records === []) {
            return 0;
        }

        $count = 0;
        $this->db->beginTransaction();
        try {
            foreach ($records as $record) {
                $this->upsertOhlcvDaily($record);
                $count++;
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            return 0;
        }
        return $count;
    }

    /**
     * Get OHLCV daily history for an instrument.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getOhlcvDailyHistory(
        string $instrumentId,
        ?string $exchangeId = null,
        ?string $startDate = null,
        ?string $endDate = null,
        int $limit = 1000
    ): array {
        if ($this->db === null) {
            return [];
        }

        $where = ['instrument_id = :instrument_id'];
        $params = [':instrument_id' => $instrumentId];

        if ($exchangeId !== null) {
            $where[] = 'exchange_id = :exchange_id';
            $params[':exchange_id'] = $exchangeId;
        }
        if ($startDate !== null) {
            $where[] = 'date >= :start_date';
            $params[':start_date'] = $startDate;
        }
        if ($endDate !== null) {
            $where[] = 'date <= :end_date';
            $params[':end_date'] = $endDate;
        }

        $clause = implode(' AND ', $where);
        $sql = "SELECT * FROM ohlcv.ohlcv_daily WHERE {$clause}
            ORDER BY date DESC LIMIT {$limit}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Get latest OHLCV daily for an instrument.
     *
     * @return array<string, mixed>|null
     */
    public function getLatestOhlcvDaily(string $instrumentId, ?string $exchangeId = null): ?array
    {
        if ($this->db === null) {
            return null;
        }

        $where = 'instrument_id = :instrument_id';
        $params = [':instrument_id' => $instrumentId];

        if ($exchangeId !== null) {
            $where .= ' AND exchange_id = :exchange_id';
            $params[':exchange_id'] = $exchangeId;
        }

        $sql = "SELECT * FROM ohlcv.ohlcv_daily WHERE {$where}
            ORDER BY date DESC LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    // ─── OHLCV Intraday ───────────────────────────────────────────────

    /**
     * @param array<string, mixed> $data
     */
    public function insertOhlcvIntraday(array $data): ?array
    {
        if ($this->db === null) {
            return null;
        }

        $sql = 'INSERT INTO ohlcv.ohlcv_intraday
            (instrument_id, exchange_id, timestamp, interval_seconds, open, high, low, close, volume)
            VALUES (:instrument_id, :exchange_id, :timestamp, :interval_seconds, :open, :high, :low, :close, :volume)
            ON CONFLICT (instrument_id, exchange_id, timestamp, interval_seconds)
            DO NOTHING';

        $this->db->prepare($sql)->execute([
            ':instrument_id' => $data['instrument_id'],
            ':exchange_id' => $data['exchange_id'],
            ':timestamp' => $data['timestamp'],
            ':interval_seconds' => $data['interval_seconds'] ?? 300,
            ':open' => $data['open'],
            ':high' => $data['high'],
            ':low' => $data['low'],
            ':close' => $data['close'],
            ':volume' => $data['volume'],
        ]);

        return $data;
    }

    // ─── Technical Indicators ─────────────────────────────────────────

    /**
     * @param array<string, mixed> $data
     */
    public function upsertTechnicalIndicator(array $data): ?array
    {
        if ($this->db === null) {
            return null;
        }

        $sql = 'INSERT INTO technical.technical_indicator
            (instrument_id, exchange_id, timestamp, indicator_type, parameters_hash, value, metadata)
            VALUES (:instrument_id, :exchange_id, :timestamp, :indicator_type, :parameters_hash, :value, :metadata)
            ON CONFLICT (instrument_id, exchange_id, timestamp, indicator_type, parameters_hash)
            DO UPDATE SET value = EXCLUDED.value, metadata = EXCLUDED.metadata';

        $this->db->prepare($sql)->execute([
            ':instrument_id' => $data['instrument_id'],
            ':exchange_id' => $data['exchange_id'],
            ':timestamp' => $data['timestamp'],
            ':indicator_type' => $data['indicator_type'],
            ':parameters_hash' => $data['parameters_hash'] ?? 'default',
            ':value' => $data['value'],
            ':metadata' => $data['metadata'] ?? null,
        ]);

        return $data;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTechnicalIndicators(
        string $instrumentId,
        string $indicatorType,
        ?string $startDate = null,
        ?string $endDate = null,
        int $limit = 500
    ): array {
        if ($this->db === null) {
            return [];
        }

        $where = 'instrument_id = :instrument_id AND indicator_type = :indicator_type';
        $params = [
            ':instrument_id' => $instrumentId,
            ':indicator_type' => $indicatorType,
        ];

        if ($startDate !== null) {
            $where .= ' AND timestamp >= :start_date';
            $params[':start_date'] = $startDate;
        }
        if ($endDate !== null) {
            $where .= ' AND timestamp <= :end_date';
            $params[':end_date'] = $endDate;
        }

        $sql = "SELECT * FROM technical.technical_indicator WHERE {$where}
            ORDER BY timestamp DESC LIMIT {$limit}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // ─── Tick Data ────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $data
     */
    public function insertTick(array $data): ?array
    {
        if ($this->db === null) {
            return null;
        }

        $sql = 'INSERT INTO tick.tick
            (instrument_id, exchange_id, timestamp, price, volume, side, source)
            VALUES (:instrument_id, :exchange_id, :timestamp, :price, :volume, :side, :source)';

        $this->db->prepare($sql)->execute([
            ':instrument_id' => $data['instrument_id'],
            ':exchange_id' => $data['exchange_id'],
            ':timestamp' => $data['timestamp'],
            ':price' => $data['price'],
            ':volume' => $data['volume'],
            ':side' => $data['side'] ?? 'TRADE',
            ':source' => $data['source'] ?? 'feed',
        ]);

        return $data;
    }

    // ─── Quote Data ───────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $data
     */
    public function insertQuote(array $data): ?array
    {
        if ($this->db === null) {
            return null;
        }

        $sql = 'INSERT INTO quote.quote
            (instrument_id, exchange_id, timestamp, bid_price, bid_volume,
             ask_price, ask_volume, mid_price, source)
            VALUES (:instrument_id, :exchange_id, :timestamp, :bid_price,
                    :bid_volume, :ask_price, :ask_volume, :mid_price, :source)';

        $this->db->prepare($sql)->execute([
            ':instrument_id' => $data['instrument_id'],
            ':exchange_id' => $data['exchange_id'],
            ':timestamp' => $data['timestamp'],
            ':bid_price' => $data['bid_price'],
            ':bid_volume' => $data['bid_volume'] ?? 0,
            ':ask_price' => $data['ask_price'],
            ':ask_volume' => $data['ask_volume'] ?? 0,
            ':mid_price' => $data['mid_price'] ?? (($data['bid_price'] + $data['ask_price']) / 2),
            ':source' => $data['source'] ?? 'feed',
        ]);

        return $data;
    }

    // ─── Valuation Metrics ────────────────────────────────────────────

    /**
     * @param array<string, mixed> $data
     */
    public function upsertValuationMetric(array $data): ?array
    {
        if ($this->db === null) {
            return null;
        }

        $sql = 'INSERT INTO valuation.valuation_metric
            (instrument_id, exchange_id, date, metric_type, value, source, metadata)
            VALUES (:instrument_id, :exchange_id, :date, :metric_type, :value, :source, :metadata)
            ON CONFLICT (instrument_id, exchange_id, date, metric_type)
            DO UPDATE SET value = EXCLUDED.value, source = EXCLUDED.source, metadata = EXCLUDED.metadata';

        $this->db->prepare($sql)->execute([
            ':instrument_id' => $data['instrument_id'],
            ':exchange_id' => $data['exchange_id'],
            ':date' => $data['date'],
            ':metric_type' => $data['metric_type'],
            ':value' => $data['value'],
            ':source' => $data['source'] ?? 'calculation',
            ':metadata' => isset($data['metadata']) ? json_encode($data['metadata']) : null,
        ]);

        return $data;
    }

    // ─── Data Source Meta ─────────────────────────────────────────────

    /**
     * Log an ingestion event.
     *
     * @param array<string, mixed> $data
     */
    public function logIngestion(array $data): void
    {
        if ($this->db === null) {
            return;
        }

        $sql = 'INSERT INTO meta.ingestion_log
            (source_id, status, records_ingested, records_failed,
             started_at, completed_at, error_message)
            VALUES (:source_id, :status, :records_ingested, :records_failed,
                    :started_at, :completed_at, :error_message)';

        $this->db->prepare($sql)->execute([
            ':source_id' => $data['source_id'],
            ':status' => $data['status'] ?? 'COMPLETED',
            ':records_ingested' => $data['records_ingested'] ?? 0,
            ':records_failed' => $data['records_failed'] ?? 0,
            ':started_at' => $data['started_at'] ?? gmdate('Y-m-d H:i:s'),
            ':completed_at' => $data['completed_at'] ?? gmdate('Y-m-d H:i:s'),
            ':error_message' => $data['error_message'] ?? null,
        ]);
    }
}
