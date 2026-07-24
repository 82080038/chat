<?php

declare(strict_types=1);

namespace Platform\DataIngestion;

use Platform\Core\BaseService;
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
}
