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
}
