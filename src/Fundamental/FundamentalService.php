<?php

declare(strict_types=1);

namespace Platform\Fundamental;

use PDO;
use Platform\Core\BaseService;
use Platform\Core\Exceptions\ApiException;

final class FundamentalService extends BaseService implements FundamentalServiceInterface
{
    // ─── Financial Statements ────────────────────────────────────────────

    public function listFinancialStatements(array $filters, int $page, int $perPage): array
    {
        [$page, $perPage, $offset] = $this->parsePagination(
            ['page' => $page, 'per_page' => $perPage]
        );
        $where = [];
        $params = [];
        if (isset($filters['issuer_id'])) {
            $where[] = 'issuer_id = :issuer_id';
            $params[':issuer_id'] = $filters['issuer_id'];
        }
        if (isset($filters['statement_type'])) {
            $where[] = 'statement_type = :statement_type';
            $params[':statement_type'] = $filters['statement_type'];
        }
        if (isset($filters['fiscal_year'])) {
            $where[] = 'fiscal_year = :fiscal_year';
            $params[':fiscal_year'] = $filters['fiscal_year'];
        }
        if (isset($filters['status'])) {
            $where[] = 'status = :status';
            $params[':status'] = $filters['status'];
        }
        $clause = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
        $total = $this->countRows('fundamental.financial_statement', $clause, $params);
        $stmt = $this->db->prepare(
            "SELECT * FROM fundamental.financial_statement {$clause} "
            . "ORDER BY fiscal_year DESC, fiscal_quarter DESC, version DESC "
            . "LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return $this->paginate($stmt->fetchAll(), $total, $page, $perPage);
    }

    public function createFinancialStatement(array $data): array
    {
        $this->validateRequired(
            $data,
            ['issuer_id', 'statement_type', 'fiscal_period_type', 'fiscal_year',
             'period_start', 'period_end', 'currency', 'source']
        );
        $this->assertStatementType((string) $data['statement_type']);
        $this->assertPeriodType((string) $data['fiscal_period_type']);

        $id = $this->uuid();
        $now = $this->now();
        $availableTime = $data['available_time'] ?? $now;

        $stmt = $this->db->prepare(
            'INSERT INTO fundamental.financial_statement
             (financial_statement_id, issuer_id, statement_type, fiscal_period_type,
              fiscal_year, fiscal_quarter, period_start, period_end, publication_date,
              available_time, currency, unit, source, source_document_id, version,
              revision_of, status, created_at)
             VALUES
             (:id, :issuer_id, :statement_type, :fiscal_period_type,
              :fiscal_year, :fiscal_quarter, :period_start, :period_end,
              :publication_date, :available_time, :currency, :unit, :source,
              :source_document_id, 1, NULL, :status, :now)'
        );
        $stmt->execute([
            ':id' => $id,
            ':issuer_id' => $data['issuer_id'],
            ':statement_type' => $data['statement_type'],
            ':fiscal_period_type' => $data['fiscal_period_type'],
            ':fiscal_year' => (int) $data['fiscal_year'],
            ':fiscal_quarter' => $data['fiscal_quarter'] ?? null,
            ':period_start' => $data['period_start'],
            ':period_end' => $data['period_end'],
            ':publication_date' => $data['publication_date'] ?? null,
            ':available_time' => $availableTime,
            ':currency' => strtoupper($data['currency']),
            ':unit' => $data['unit'] ?? null,
            ':source' => $data['source'],
            ':source_document_id' => $data['source_document_id'] ?? null,
            ':status' => $data['status'] ?? 'PUBLISHED',
            ':now' => $now,
        ]);

        if (!empty($data['lines'])) {
            $this->insertLines($id, $data['lines']);
        }

        return $this->getFinancialStatement($id);
    }

    public function getFinancialStatement(string $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM fundamental.financial_statement WHERE financial_statement_id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        return $row;
    }

    public function getFinancialStatementLines(string $id): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM fundamental.financial_statement_line
             WHERE financial_statement_id = :id
             ORDER BY order_position ASC'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetchAll();
    }

    public function getFinancialStatementRevisions(string $id): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM fundamental.financial_statement
             WHERE financial_statement_id = :id
                OR revision_of = :id
             ORDER BY version ASC'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetchAll();
    }

    public function reviseFinancialStatement(string $id, array $data): array
    {
        $original = $this->getFinancialStatement($id);
        if ($original === null) {
            throw new ApiException(
                404,
                'FINANCIAL_STATEMENT_NOT_FOUND',
                'Financial statement was not found'
            );
        }

        $newId = $this->uuid();
        $now = $this->now();
        $availableTime = $data['available_time'] ?? $now;
        $nextVersion = (int) $original['version'] + 1;

        $stmt = $this->db->prepare(
            'INSERT INTO fundamental.financial_statement
             (financial_statement_id, issuer_id, statement_type, fiscal_period_type,
              fiscal_year, fiscal_quarter, period_start, period_end, publication_date,
              available_time, currency, unit, source, source_document_id, version,
              revision_of, status, created_at)
             VALUES
             (:id, :issuer_id, :statement_type, :fiscal_period_type,
              :fiscal_year, :fiscal_quarter, :period_start, :period_end,
              :publication_date, :available_time, :currency, :unit, :source,
              :source_document_id, :version, :revision_of, :status, :now)'
        );
        $stmt->execute([
            ':id' => $newId,
            ':issuer_id' => $original['issuer_id'],
            ':statement_type' => $original['statement_type'],
            ':fiscal_period_type' => $original['fiscal_period_type'],
            ':fiscal_year' => $original['fiscal_year'],
            ':fiscal_quarter' => $original['fiscal_quarter'],
            ':period_start' => $data['period_start'] ?? $original['period_start'],
            ':period_end' => $data['period_end'] ?? $original['period_end'],
            ':publication_date' => $data['publication_date'] ?? $original['publication_date'],
            ':available_time' => $availableTime,
            ':currency' => $data['currency'] ?? $original['currency'],
            ':unit' => $data['unit'] ?? $original['unit'],
            ':source' => $data['source'] ?? $original['source'],
            ':source_document_id' => $data['source_document_id']
                ?? $original['source_document_id'],
            ':version' => $nextVersion,
            ':revision_of' => $id,
            ':status' => 'REVISED',
            ':now' => $now,
        ]);

        $updateStmt = $this->db->prepare(
            'UPDATE fundamental.financial_statement
             SET status = :status WHERE financial_statement_id = :id'
        );
        $updateStmt->execute([':status' => 'SUPERSEDED', ':id' => $id]);

        if (!empty($data['lines'])) {
            $this->insertLines($newId, $data['lines']);
        }

        return $this->getFinancialStatement($newId);
    }

    public function getLatestFinancialStatement(string $issuerId, string $type): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM fundamental.financial_statement
             WHERE issuer_id = :issuer_id
               AND statement_type = :statement_type
               AND status IN (:status1, :status2)
             ORDER BY fiscal_year DESC, fiscal_quarter DESC, version DESC
             LIMIT 1'
        );
        $stmt->execute([
            ':issuer_id' => $issuerId,
            ':statement_type' => $type,
            ':status1' => 'PUBLISHED',
            ':status2' => 'REVISED',
        ]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    // ─── Financial Metrics ───────────────────────────────────────────────

    public function listFinancialMetrics(array $filters, int $page, int $perPage): array
    {
        [$page, $perPage, $offset] = $this->parsePagination(
            ['page' => $page, 'per_page' => $perPage]
        );
        $where = [];
        $params = [];
        if (isset($filters['issuer_id'])) {
            $where[] = 'issuer_id = :issuer_id';
            $params[':issuer_id'] = $filters['issuer_id'];
        }
        if (isset($filters['metric_type'])) {
            $where[] = 'metric_type = :metric_type';
            $params[':metric_type'] = $filters['metric_type'];
        }
        if (isset($filters['fiscal_year'])) {
            $where[] = 'fiscal_year = :fiscal_year';
            $params[':fiscal_year'] = $filters['fiscal_year'];
        }
        $clause = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
        $total = $this->countRows('fundamental.financial_metric', $clause, $params);
        $stmt = $this->db->prepare(
            "SELECT * FROM fundamental.financial_metric {$clause} "
            . "ORDER BY fiscal_year DESC, fiscal_quarter DESC "
            . "LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return $this->paginate($stmt->fetchAll(), $total, $page, $perPage);
    }

    public function getFinancialMetric(string $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM fundamental.financial_metric WHERE metric_id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function getIssuerMetrics(string $issuerId, ?string $metricType): array
    {
        if ($metricType !== null) {
            $stmt = $this->db->prepare(
                'SELECT * FROM fundamental.financial_metric
                 WHERE issuer_id = :issuer_id AND metric_type = :metric_type
                 ORDER BY fiscal_year DESC, fiscal_quarter DESC'
            );
            $stmt->execute([
                ':issuer_id' => $issuerId,
                ':metric_type' => $metricType,
            ]);
        } else {
            $stmt = $this->db->prepare(
                'SELECT * FROM fundamental.financial_metric
                 WHERE issuer_id = :issuer_id
                 ORDER BY metric_type, fiscal_year DESC, fiscal_quarter DESC'
            );
            $stmt->execute([':issuer_id' => $issuerId]);
        }
        return $stmt->fetchAll();
    }

    public function createFinancialMetric(array $data): array
    {
        $this->validateRequired(
            $data,
            ['issuer_id', 'metric_type', 'fiscal_period_type', 'fiscal_year',
             'calculation_version', 'available_time']
        );
        $id = $this->uuid();
        $now = $this->now();
        $stmt = $this->db->prepare(
            'INSERT INTO fundamental.financial_metric
             (metric_id, issuer_id, metric_type, value, unit,
              fiscal_period_type, fiscal_year, fiscal_quarter,
              calculation_version, available_time, calculated_at)
             VALUES
             (:id, :issuer_id, :metric_type, :value, :unit,
              :fiscal_period_type, :fiscal_year, :fiscal_quarter,
              :calculation_version, :available_time, :now)'
        );
        $stmt->execute([
            ':id' => $id,
            ':issuer_id' => $data['issuer_id'],
            ':metric_type' => $data['metric_type'],
            ':value' => $data['value'] ?? null,
            ':unit' => $data['unit'] ?? null,
            ':fiscal_period_type' => $data['fiscal_period_type'],
            ':fiscal_year' => (int) $data['fiscal_year'],
            ':fiscal_quarter' => $data['fiscal_quarter'] ?? null,
            ':calculation_version' => $data['calculation_version'],
            ':available_time' => $data['available_time'],
            ':now' => $now,
        ]);
        return $this->getFinancialMetric($id);
    }

    // ─── Economic Indicators ─────────────────────────────────────────────

    public function listEconomicIndicators(array $filters, int $page, int $perPage): array
    {
        [$page, $perPage, $offset] = $this->parsePagination(
            ['page' => $page, 'per_page' => $perPage]
        );
        $where = [];
        $params = [];
        if (isset($filters['country'])) {
            $where[] = 'country = :country';
            $params[':country'] = $filters['country'];
        }
        if (isset($filters['indicator_type'])) {
            $where[] = 'indicator_type = :indicator_type';
            $params[':indicator_type'] = $filters['indicator_type'];
        }
        if (isset($filters['period'])) {
            $where[] = 'period = :period';
            $params[':period'] = $filters['period'];
        }
        $clause = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
        $total = $this->countRows('fundamental.economic_indicator', $clause, $params);
        $stmt = $this->db->prepare(
            "SELECT * FROM fundamental.economic_indicator {$clause} "
            . "ORDER BY period DESC, revision_number DESC "
            . "LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return $this->paginate($stmt->fetchAll(), $total, $page, $perPage);
    }

    public function getEconomicIndicator(string $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM fundamental.economic_indicator WHERE indicator_id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function createEconomicIndicator(array $data): array
    {
        $this->validateRequired(
            $data,
            ['country', 'indicator_type', 'period', 'source', 'available_time']
        );
        $id = $this->uuid();
        $stmt = $this->db->prepare(
            'INSERT INTO fundamental.economic_indicator
             (indicator_id, country, indicator_type, frequency, period, value,
              unit, publication_date, available_time, revision_number, revision_of,
              source, source_record_id)
             VALUES
             (:id, :country, :indicator_type, :frequency, :period, :value,
              :unit, :publication_date, :available_time, 1, NULL,
              :source, :source_record_id)'
        );
        $stmt->execute([
            ':id' => $id,
            ':country' => strtoupper($data['country']),
            ':indicator_type' => $data['indicator_type'],
            ':frequency' => $data['frequency'] ?? null,
            ':period' => $data['period'],
            ':value' => $data['value'] ?? null,
            ':unit' => $data['unit'] ?? null,
            ':publication_date' => $data['publication_date'] ?? null,
            ':available_time' => $data['available_time'],
            ':source' => $data['source'],
            ':source_record_id' => $data['source_record_id'] ?? null,
        ]);
        return $this->getEconomicIndicator($id);
    }

    public function getEconomicIndicators(string $country, string $indicatorType): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM fundamental.economic_indicator
             WHERE country = :country AND indicator_type = :indicator_type
             ORDER BY period DESC, revision_number DESC'
        );
        $stmt->execute([
            ':country' => strtoupper($country),
            ':indicator_type' => $indicatorType,
        ]);
        return $stmt->fetchAll();
    }

    // ─── News ────────────────────────────────────────────────────────────

    public function listNews(array $filters, int $page, int $perPage): array
    {
        [$page, $perPage, $offset] = $this->parsePagination(
            ['page' => $page, 'per_page' => $perPage]
        );
        $where = [];
        $params = [];
        if (isset($filters['instrument_id'])) {
            $where[] = 'ni.instrument_id = :instrument_id';
            $params[':instrument_id'] = $filters['instrument_id'];
        }
        if (isset($filters['sentiment'])) {
            $where[] = 'n.sentiment_label = :sentiment';
            $params[':sentiment'] = $filters['sentiment'];
        }
        if (isset($filters['search'])) {
            $where[] = '(n.title LIKE :search OR n.content_summary LIKE :search)';
            $params[':search'] = '%' . $filters['search'] . '%';
        }
        $clause = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
        $baseTable = 'fundamental.news_item n'
            . (isset($filters['instrument_id'])
                ? ' INNER JOIN fundamental.news_instrument ni ON ni.news_id = n.news_id'
                : '');
        $total = $this->countRows($baseTable, $clause, $params);
        $stmt = $this->db->prepare(
            "SELECT n.* FROM {$baseTable} {$clause} "
            . "ORDER BY n.published_at DESC LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return $this->paginate($stmt->fetchAll(), $total, $page, $perPage);
    }

    public function getNewsItem(string $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM fundamental.news_item WHERE news_id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function createNewsItem(array $data): array
    {
        $this->validateRequired($data, ['title', 'source', 'available_time']);
        $id = $this->uuid();
        $stmt = $this->db->prepare(
            'INSERT INTO fundamental.news_item
             (news_id, title, content_summary, source, source_url, published_at,
              available_time, sentiment_score, sentiment_label, language,
              storage_object_id)
             VALUES
             (:id, :title, :content_summary, :source, :source_url, :published_at,
              :available_time, :sentiment_score, :sentiment_label, :language,
              :storage_object_id)'
        );
        $stmt->execute([
            ':id' => $id,
            ':title' => $data['title'],
            ':content_summary' => $data['content_summary'] ?? null,
            ':source' => $data['source'],
            ':source_url' => $data['source_url'] ?? null,
            ':published_at' => $data['published_at'] ?? null,
            ':available_time' => $data['available_time'],
            ':sentiment_score' => $data['sentiment_score'] ?? null,
            ':sentiment_label' => $data['sentiment_label'] ?? null,
            ':language' => $data['language'] ?? null,
            ':storage_object_id' => $data['storage_object_id'] ?? null,
        ]);

        if (!empty($data['instrument_ids'])) {
            $this->linkNewsInstruments($id, (array) $data['instrument_ids']);
        }

        return $this->getNewsItem($id);
    }

    public function getNewsByInstrument(string $instrumentId, int $limit): array
    {
        $limit = min(100, max(1, $limit));
        $stmt = $this->db->prepare(
            "SELECT n.*, ni.relevance_score
             FROM fundamental.news_item n
             INNER JOIN fundamental.news_instrument ni ON ni.news_id = n.news_id
             WHERE ni.instrument_id = :instrument_id
             ORDER BY n.published_at DESC
             LIMIT {$limit}"
        );
        $stmt->execute([':instrument_id' => $instrumentId]);
        return $stmt->fetchAll();
    }

    // ─── Private Helpers ─────────────────────────────────────────────────

    private function insertLines(string $statementId, array $lines): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO fundamental.financial_statement_line
             (line_id, financial_statement_id, line_item_code, line_item_name,
              value, unit, currency, order_position, is_subtotal)
             VALUES
             (:line_id, :statement_id, :code, :name, :value, :unit, :currency,
              :position, :is_subtotal)'
        );
        $position = 1;
        foreach ($lines as $line) {
            $stmt->execute([
                ':line_id' => $this->uuid(),
                ':statement_id' => $statementId,
                ':code' => $line['line_item_code'],
                ':name' => $line['line_item_name'],
                ':value' => $line['value'] ?? null,
                ':unit' => $line['unit'] ?? null,
                ':currency' => $line['currency'] ?? null,
                ':position' => $line['order_position'] ?? $position,
                ':is_subtotal' => !empty($line['is_subtotal']) ? 1 : 0,
            ]);
            $position++;
        }
    }

    private function linkNewsInstruments(string $newsId, array $instrumentIds): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO fundamental.news_instrument
             (news_id, instrument_id, relevance_score)
             VALUES (:news_id, :instrument_id, :relevance_score)'
        );
        foreach ($instrumentIds as $entry) {
            if (is_array($entry)) {
                $stmt->execute([
                    ':news_id' => $newsId,
                    ':instrument_id' => $entry['instrument_id'],
                    ':relevance_score' => $entry['relevance_score'] ?? null,
                ]);
            } else {
                $stmt->execute([
                    ':news_id' => $newsId,
                    ':instrument_id' => $entry,
                    ':relevance_score' => null,
                ]);
            }
        }
    }

    private function assertStatementType(string $type): void
    {
        $valid = ['INCOME', 'BALANCE', 'CASHFLOW', 'COMPREHENSIVE'];
        if (!in_array($type, $valid, true)) {
            throw new ApiException(
                422,
                'VALIDATION_ERROR',
                'Invalid statement type. Must be one of: ' . implode(', ', $valid)
            );
        }
    }

    private function assertPeriodType(string $type): void
    {
        $valid = ['Q1', 'Q2', 'Q3', 'Q4', 'FY', 'H1', 'H2', 'YTD'];
        if (!in_array($type, $valid, true)) {
            throw new ApiException(
                422,
                'VALIDATION_ERROR',
                'Invalid fiscal period type. Must be one of: ' . implode(', ', $valid)
            );
        }
    }

    private function validateRequired(array $data, array $fields): void
    {
        $errors = [];
        foreach ($fields as $field) {
            if (!array_key_exists($field, $data) || $data[$field] === '') {
                $errors[$field][] = 'This field is required';
            }
        }
        if ($errors !== []) {
            throw new ApiException(422, 'VALIDATION_ERROR', 'Required fields are missing', $errors);
        }
    }

    private function countRows(string $table, string $clause = '', array $params = []): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM {$table} {$clause}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }
}
