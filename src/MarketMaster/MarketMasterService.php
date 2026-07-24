<?php

declare(strict_types=1);

namespace Platform\MarketMaster;

use PDO;
use Platform\Core\BaseService;
use Platform\Core\Cache\CacheStoreInterface;
use Platform\Core\Cache\RedisCacheStore;
use Platform\Core\Exceptions\ApiException;

final class MarketMasterService extends BaseService implements MarketMasterServiceInterface
{
    private CacheStoreInterface $cache;

    public function __construct(?PDO $db = null, ?CacheStoreInterface $cache = null)
    {
        parent::__construct($db);
        $this->cache = $cache ?? new RedisCacheStore();
    }

    // ─── Exchanges ───────────────────────────────────────────────────────

    public function listExchanges(array $filters, int $page, int $perPage): array
    {
        [$page, $perPage, $offset] = $this->parsePagination(['page' => $page, 'per_page' => $perPage]);
        $where = [];
        $params = [];
        if (isset($filters['country'])) {
            $where[] = 'country = :country';
            $params[':country'] = $filters['country'];
        }
        if (isset($filters['status'])) {
            $where[] = 'status = :status';
            $params[':status'] = $filters['status'];
        }
        $clause = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
        $total = $this->countRows('market_master.exchange', $clause, $params);
        $stmt = $this->db->prepare(
            "SELECT * FROM market_master.exchange {$clause} "
            . "ORDER BY name ASC LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return $this->paginate($stmt->fetchAll(), $total, $page, $perPage);
    }

    public function getExchange(string $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM market_master.exchange WHERE exchange_id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function createExchange(array $data): array
    {
        $this->validateRequired($data, ['name', 'mic_code', 'country', 'timezone', 'currency']);
        $mic = strtoupper(trim((string) $data['mic_code']));
        if ($this->getExchangeByMic($mic) !== null) {
            throw new ApiException(409, 'EXCHANGE_EXISTS', 'An exchange with this MIC code already exists');
        }
        $id = $this->uuid();
        $now = $this->now();
        $stmt = $this->db->prepare(
            'INSERT INTO market_master.exchange
             (exchange_id, name, mic_code, country, timezone, currency, status)
             VALUES (:id, :name, :mic, :country, :tz, :currency, :status)'
        );
        $stmt->execute([
            ':id' => $id,
            ':name' => $data['name'],
            ':mic' => $mic,
            ':country' => strtoupper($data['country']),
            ':tz' => $data['timezone'],
            ':currency' => strtoupper($data['currency']),
            ':status' => $data['status'] ?? 'ACTIVE',
        ]);
        return $this->getExchange($id);
    }

    public function updateExchange(string $id, array $data): array
    {
        $existing = $this->getExchange($id);
        if ($existing === null) {
            throw new ApiException(404, 'EXCHANGE_NOT_FOUND', 'Exchange was not found');
        }
        $fields = [];
        $params = [':id' => $id];
        foreach (['name', 'country', 'timezone', 'currency', 'status'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = :{$field}";
                $params[":{$field}"] = $data[$field];
            }
        }
        if ($fields === []) {
            return $existing;
        }
        $stmt = $this->db->prepare(
            'UPDATE market_master.exchange SET ' . implode(', ', $fields) . ' WHERE exchange_id = :id'
        );
        $stmt->execute($params);
        return $this->getExchange($id);
    }

    public function getExchangeCalendar(string $exchangeId, string $fromDate, string $toDate): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM market_master.market_calendar
             WHERE exchange_id = :exchange_id
               AND date BETWEEN :from_date AND :to_date
             ORDER BY date ASC'
        );
        $stmt->execute([
            ':exchange_id' => $exchangeId,
            ':from_date' => $fromDate,
            ':to_date' => $toDate,
        ]);
        return $stmt->fetchAll();
    }

    public function getExchangeInstruments(string $exchangeId, int $page, int $perPage): array
    {
        [$page, $perPage, $offset] = $this->parsePagination(['page' => $page, 'per_page' => $perPage]);
        $total = $this->countRows(
            'market_master.listing',
            'WHERE exchange_id = :exchange_id AND status = :status',
            [':exchange_id' => $exchangeId, ':status' => 'ACTIVE']
        );
        $stmt = $this->db->prepare(
            "SELECT i.*, l.ticker, l.isin, l.listing_id
             FROM market_master.instrument i
             INNER JOIN market_master.listing l ON l.instrument_id = i.instrument_id
             WHERE l.exchange_id = :exchange_id AND l.status = 'ACTIVE'
             ORDER BY l.ticker ASC LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute([':exchange_id' => $exchangeId]);
        return $this->paginate($stmt->fetchAll(), $total, $page, $perPage);
    }

    // ─── Issuers ─────────────────────────────────────────────────────────

    public function listIssuers(array $filters, int $page, int $perPage): array
    {
        [$page, $perPage, $offset] = $this->parsePagination(['page' => $page, 'per_page' => $perPage]);
        $where = [];
        $params = [];
        if (isset($filters['country'])) {
            $where[] = 'country = :country';
            $params[':country'] = $filters['country'];
        }
        if (isset($filters['status'])) {
            $where[] = 'status = :status';
            $params[':status'] = $filters['status'];
        }
        if (isset($filters['search'])) {
            $where[] = '(legal_name LIKE :search OR short_name LIKE :search)';
            $params[':search'] = '%' . $filters['search'] . '%';
        }
        $clause = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
        $total = $this->countRows('market_master.issuer', $clause, $params);
        $stmt = $this->db->prepare(
            "SELECT * FROM market_master.issuer {$clause} "
            . "ORDER BY legal_name ASC LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return $this->paginate($stmt->fetchAll(), $total, $page, $perPage);
    }

    public function getIssuer(string $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM market_master.issuer WHERE issuer_id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function createIssuer(array $data): array
    {
        $this->validateRequired($data, ['legal_name', 'country']);
        $id = $this->uuid();
        $now = $this->now();
        $stmt = $this->db->prepare(
            'INSERT INTO market_master.issuer
             (issuer_id, legal_name, short_name, country, jurisdiction,
              legal_entity_identifier, status, incorporation_date,
              sector_code, industry_code, created_at, updated_at)
             VALUES
             (:id, :legal_name, :short_name, :country, :jurisdiction,
              :lei, :status, :incorp_date, :sector, :industry, :now, :now)'
        );
        $stmt->execute([
            ':id' => $id,
            ':legal_name' => $data['legal_name'],
            ':short_name' => $data['short_name'] ?? null,
            ':country' => strtoupper($data['country']),
            ':jurisdiction' => $data['jurisdiction'] ?? null,
            ':lei' => $data['legal_entity_identifier'] ?? null,
            ':status' => $data['status'] ?? 'ACTIVE',
            ':incorp_date' => $data['incorporation_date'] ?? null,
            ':sector' => $data['sector_code'] ?? null,
            ':industry' => $data['industry_code'] ?? null,
            ':now' => $now,
        ]);
        return $this->getIssuer($id);
    }

    public function updateIssuer(string $id, array $data): array
    {
        $existing = $this->getIssuer($id);
        if ($existing === null) {
            throw new ApiException(404, 'ISSUER_NOT_FOUND', 'Issuer was not found');
        }
        $fields = [];
        $params = [':id' => $id];
        foreach (
            [
            'legal_name', 'short_name', 'country', 'jurisdiction',
            'legal_entity_identifier', 'status', 'incorporation_date',
            'sector_code', 'industry_code',
            ] as $field
        ) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = :{$field}";
                $params[":{$field}"] = $data[$field];
            }
        }
        if ($fields === []) {
            return $existing;
        }
        $stmt = $this->db->prepare(
            'UPDATE market_master.issuer SET ' . implode(', ', $fields)
            . ', updated_at = :now WHERE issuer_id = :id'
        );
        $params[':now'] = $this->now();
        $stmt->execute($params);
        return $this->getIssuer($id);
    }

    public function getIssuerSecurities(string $issuerId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM market_master.security WHERE issuer_id = :issuer_id ORDER BY security_type'
        );
        $stmt->execute([':issuer_id' => $issuerId]);
        return $stmt->fetchAll();
    }

    // ─── Securities ──────────────────────────────────────────────────────

    public function listSecurities(array $filters, int $page, int $perPage): array
    {
        [$page, $perPage, $offset] = $this->parsePagination(['page' => $page, 'per_page' => $perPage]);
        $where = [];
        $params = [];
        if (isset($filters['issuer_id'])) {
            $where[] = 'issuer_id = :issuer_id';
            $params[':issuer_id'] = $filters['issuer_id'];
        }
        if (isset($filters['security_type'])) {
            $where[] = 'security_type = :security_type';
            $params[':security_type'] = $filters['security_type'];
        }
        $clause = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
        $total = $this->countRows('market_master.security', $clause, $params);
        $stmt = $this->db->prepare(
            "SELECT * FROM market_master.security {$clause} "
            . "ORDER BY security_type LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return $this->paginate($stmt->fetchAll(), $total, $page, $perPage);
    }

    public function getSecurity(string $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM market_master.security WHERE security_id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    // ─── Instruments ─────────────────────────────────────────────────────

    public function listInstruments(array $filters, int $page, int $perPage): array
    {
        [$page, $perPage, $offset] = $this->parsePagination(['page' => $page, 'per_page' => $perPage]);
        $where = [];
        $params = [];
        if (isset($filters['asset_class'])) {
            $where[] = 'i.asset_class = :asset_class';
            $params[':asset_class'] = $filters['asset_class'];
        }
        if (isset($filters['status'])) {
            $where[] = 'i.status = :status';
            $params[':status'] = $filters['status'];
        }
        if (isset($filters['search'])) {
            $where[] = '(l.ticker LIKE :search OR i.instrument_type LIKE :search)';
            $params[':search'] = '%' . $filters['search'] . '%';
        }
        $clause = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
        $total = $this->countRows(
            'market_master.instrument i LEFT JOIN market_master.listing l ON l.instrument_id = i.instrument_id',
            $clause,
            $params
        );
        $stmt = $this->db->prepare(
            "SELECT i.*, l.listing_id, l.ticker, l.isin, l.exchange_id,
                    e.mic_code AS exchange_mic, e.name AS exchange_name,
                    iss.legal_name AS issuer_name, iss.short_name AS issuer_short
             FROM market_master.instrument i
             LEFT JOIN market_master.listing l ON l.instrument_id = i.instrument_id AND l.status = 'ACTIVE'
             LEFT JOIN market_master.exchange e ON e.exchange_id = l.exchange_id
             LEFT JOIN market_master.security s ON s.security_id = i.security_id
             LEFT JOIN market_master.issuer iss ON iss.issuer_id = s.issuer_id
             {$clause}
             ORDER BY i.instrument_type ASC LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return $this->paginate($stmt->fetchAll(), $total, $page, $perPage);
    }

    public function createInstrument(array $data): array
    {
        $this->validateRequired($data, ['security_id', 'asset_class', 'instrument_type', 'currency']);
        $id = $this->uuid();
        $now = $this->now();
        $stmt = $this->db->prepare(
            'INSERT INTO market_master.instrument
             (instrument_id, security_id, asset_class, instrument_type, currency,
              status, status_changed_at)
             VALUES (:id, :security_id, :asset_class, :instrument_type, :currency, :status, :now)'
        );
        $stmt->execute([
            ':id' => $id,
            ':security_id' => $data['security_id'],
            ':asset_class' => strtoupper($data['asset_class']),
            ':instrument_type' => $data['instrument_type'],
            ':currency' => strtoupper($data['currency']),
            ':status' => $data['status'] ?? 'ACTIVE',
            ':now' => $now,
        ]);
        $this->cache->delete("instrument:{$id}");
        return $this->getInstrumentById($id);
    }

    public function getInstrumentById(string $id): ?array
    {
        $cacheKey = "instrument:{$id}";
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return json_decode($cached, true);
        }
        $stmt = $this->db->prepare(
            'SELECT i.*, s.security_type, s.issuer_id,
                    iss.legal_name AS issuer_name, iss.short_name AS issuer_short
             FROM market_master.instrument i
             INNER JOIN market_master.security s ON s.security_id = i.security_id
             INNER JOIN market_master.issuer iss ON iss.issuer_id = s.issuer_id
             WHERE i.instrument_id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        $row['listings'] = $this->getInstrumentListings($id);
        $this->cache->set($cacheKey, json_encode($row), 300);
        return $row;
    }

    public function updateInstrument(string $id, array $data): array
    {
        $existing = $this->getInstrumentById($id);
        if ($existing === null) {
            throw new ApiException(404, 'INSTRUMENT_NOT_FOUND', 'Instrument was not found');
        }
        $fields = [];
        $params = [':id' => $id];
        foreach (['asset_class', 'instrument_type', 'currency', 'status'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = :{$field}";
                $params[":{$field}"] = $data[$field];
            }
        }
        if ($fields === []) {
            return $existing;
        }
        if (in_array('status', array_keys($data), true)) {
            $fields[] = 'status_changed_at = :now';
            $params[':now'] = $this->now();
        }
        $stmt = $this->db->prepare(
            'UPDATE market_master.instrument SET ' . implode(', ', $fields)
            . ' WHERE instrument_id = :id'
        );
        $stmt->execute($params);
        $this->cache->delete("instrument:{$id}");
        return $this->getInstrumentById($id);
    }

    public function getInstrumentByTicker(string $exchangeMic, string $ticker): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT i.* FROM market_master.instrument i
             INNER JOIN market_master.listing l ON l.instrument_id = i.instrument_id
             INNER JOIN market_master.exchange e ON e.exchange_id = l.exchange_id
             WHERE e.mic_code = :mic AND l.ticker = :ticker AND l.status = :status
             LIMIT 1'
        );
        $stmt->execute([
            ':mic' => strtoupper($exchangeMic),
            ':ticker' => strtoupper($ticker),
            ':status' => 'ACTIVE',
        ]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        return $this->getInstrumentById($row['instrument_id']);
    }

    public function getInstrumentByIsin(string $isin): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT i.instrument_id FROM market_master.instrument i
             INNER JOIN market_master.listing l ON l.instrument_id = i.instrument_id
             WHERE l.isin = :isin AND l.status = :status LIMIT 1'
        );
        $stmt->execute([':isin' => $isin, ':status' => 'ACTIVE']);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        return $this->getInstrumentById($row['instrument_id']);
    }

    // ─── Listings ────────────────────────────────────────────────────────

    public function listListings(array $filters, int $page, int $perPage): array
    {
        [$page, $perPage, $offset] = $this->parsePagination(['page' => $page, 'per_page' => $perPage]);
        $where = [];
        $params = [];
        if (isset($filters['exchange_id'])) {
            $where[] = 'l.exchange_id = :exchange_id';
            $params[':exchange_id'] = $filters['exchange_id'];
        }
        if (isset($filters['status'])) {
            $where[] = 'l.status = :status';
            $params[':status'] = $filters['status'];
        }
        if (isset($filters['ticker'])) {
            $where[] = 'l.ticker LIKE :ticker';
            $params[':ticker'] = '%' . $filters['ticker'] . '%';
        }
        $clause = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
        $total = $this->countRows('market_master.listing l', $clause, $params);
        $stmt = $this->db->prepare(
            "SELECT l.*, e.mic_code AS exchange_mic, e.name AS exchange_name
             FROM market_master.listing l
             INNER JOIN market_master.exchange e ON e.exchange_id = l.exchange_id
             {$clause} ORDER BY l.ticker ASC LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return $this->paginate($stmt->fetchAll(), $total, $page, $perPage);
    }

    public function getListing(string $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT l.*, e.mic_code AS exchange_mic, e.name AS exchange_name
             FROM market_master.listing l
             INNER JOIN market_master.exchange e ON e.exchange_id = l.exchange_id
             WHERE l.listing_id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function getListingByTicker(string $exchangeMic, string $ticker): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT l.*, e.mic_code AS exchange_mic, e.name AS exchange_name
             FROM market_master.listing l
             INNER JOIN market_master.exchange e ON e.exchange_id = l.exchange_id
             WHERE e.mic_code = :mic AND l.ticker = :ticker
             ORDER BY l.status DESC LIMIT 1'
        );
        $stmt->execute([
            ':mic' => strtoupper($exchangeMic),
            ':ticker' => strtoupper($ticker),
        ]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function getListingByIsin(string $isin): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT l.*, e.mic_code AS exchange_mic, e.name AS exchange_name
             FROM market_master.listing l
             INNER JOIN market_master.exchange e ON e.exchange_id = l.exchange_id
             WHERE l.isin = :isin ORDER BY l.status DESC LIMIT 1'
        );
        $stmt->execute([':isin' => $isin]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function createListing(array $data): array
    {
        $this->validateRequired($data, ['instrument_id', 'exchange_id', 'ticker', 'currency']);
        $id = $this->uuid();
        $stmt = $this->db->prepare(
            'INSERT INTO market_master.listing
             (listing_id, instrument_id, exchange_id, ticker, isin, currency,
              listing_date, delisting_date, status)
             VALUES (:id, :instrument_id, :exchange_id, :ticker, :isin, :currency,
                     :listing_date, :delisting_date, :status)'
        );
        $stmt->execute([
            ':id' => $id,
            ':instrument_id' => $data['instrument_id'],
            ':exchange_id' => $data['exchange_id'],
            ':ticker' => strtoupper($data['ticker']),
            ':isin' => $data['isin'] ?? null,
            ':currency' => strtoupper($data['currency']),
            ':listing_date' => $data['listing_date'] ?? null,
            ':delisting_date' => $data['delisting_date'] ?? null,
            ':status' => $data['status'] ?? 'ACTIVE',
        ]);
        $this->cache->delete("instrument:{$data['instrument_id']}");
        return $this->getListing($id);
    }

    public function getInstrumentListings(string $instrumentId): array
    {
        $stmt = $this->db->prepare(
            'SELECT l.*, e.mic_code AS exchange_mic, e.name AS exchange_name
             FROM market_master.listing l
             INNER JOIN market_master.exchange e ON e.exchange_id = l.exchange_id
             WHERE l.instrument_id = :instrument_id
             ORDER BY l.status DESC, l.ticker ASC'
        );
        $stmt->execute([':instrument_id' => $instrumentId]);
        return $stmt->fetchAll();
    }

    // ─── Corporate Actions ───────────────────────────────────────────────

    public function listCorporateActions(array $filters, int $page, int $perPage): array
    {
        [$page, $perPage, $offset] = $this->parsePagination(['page' => $page, 'per_page' => $perPage]);
        $where = [];
        $params = [];
        if (isset($filters['instrument_id'])) {
            $where[] = 'instrument_id = :instrument_id';
            $params[':instrument_id'] = $filters['instrument_id'];
        }
        if (isset($filters['action_type'])) {
            $where[] = 'action_type = :action_type';
            $params[':action_type'] = $filters['action_type'];
        }
        $clause = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
        $total = $this->countRows('market_master.corporate_action', $clause, $params);
        $stmt = $this->db->prepare(
            "SELECT * FROM market_master.corporate_action {$clause} "
            . "ORDER BY effective_date DESC LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return $this->paginate($stmt->fetchAll(), $total, $page, $perPage);
    }

    public function getCorporateAction(string $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM market_master.corporate_action WHERE corporate_action_id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function createCorporateAction(array $data): array
    {
        $this->validateRequired($data, ['instrument_id', 'action_type', 'effective_date']);
        $id = $this->uuid();
        $stmt = $this->db->prepare(
            'INSERT INTO market_master.corporate_action
             (corporate_action_id, instrument_id, action_type, announcement_date,
              ex_date, record_date, payment_date, effective_date, ratio, amount,
              currency, source, source_record_id)
             VALUES
             (:id, :instrument_id, :action_type, :announcement_date, :ex_date,
              :record_date, :payment_date, :effective_date, :ratio, :amount,
              :currency, :source, :source_record_id)'
        );
        $stmt->execute([
            ':id' => $id,
            ':instrument_id' => $data['instrument_id'],
            ':action_type' => $data['action_type'],
            ':announcement_date' => $data['announcement_date'] ?? null,
            ':ex_date' => $data['ex_date'] ?? null,
            ':record_date' => $data['record_date'] ?? null,
            ':payment_date' => $data['payment_date'] ?? null,
            ':effective_date' => $data['effective_date'],
            ':ratio' => $data['ratio'] ?? null,
            ':amount' => $data['amount'] ?? null,
            ':currency' => $data['currency'] ?? null,
            ':source' => $data['source'] ?? 'MANUAL',
            ':source_record_id' => $data['source_record_id'] ?? null,
        ]);
        return $this->getCorporateAction($id);
    }

    public function getCorporateActions(
        string $instrumentId,
        string $fromDate,
        string $toDate
    ): array {
        $stmt = $this->db->prepare(
            'SELECT * FROM market_master.corporate_action
             WHERE instrument_id = :instrument_id
               AND effective_date BETWEEN :from_date AND :to_date
             ORDER BY effective_date DESC'
        );
        $stmt->execute([
            ':instrument_id' => $instrumentId,
            ':from_date' => $fromDate,
            ':to_date' => $toDate,
        ]);
        return $stmt->fetchAll();
    }

    // ─── Index Master ────────────────────────────────────────────────────

    public function listIndices(array $filters, int $page, int $perPage): array
    {
        [$page, $perPage, $offset] = $this->parsePagination(['page' => $page, 'per_page' => $perPage]);
        $where = [];
        $params = [];
        if (isset($filters['exchange_id'])) {
            $where[] = 'exchange_id = :exchange_id';
            $params[':exchange_id'] = $filters['exchange_id'];
        }
        if (isset($filters['status'])) {
            $where[] = 'status = :status';
            $params[':status'] = $filters['status'];
        }
        $clause = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
        $total = $this->countRows('market_master.index_master', $clause, $params);
        $stmt = $this->db->prepare(
            "SELECT * FROM market_master.index_master {$clause} "
            . "ORDER BY name ASC LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return $this->paginate($stmt->fetchAll(), $total, $page, $perPage);
    }

    public function getIndex(string $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM market_master.index_master WHERE index_id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function getIndexMembers(string $indexId, string $asOfDate): array
    {
        $stmt = $this->db->prepare(
            'SELECT im.*, l.ticker, l.isin, i.instrument_type
             FROM market_master.index_membership im
             INNER JOIN market_master.instrument i ON i.instrument_id = im.instrument_id
             LEFT JOIN market_master.listing l ON l.instrument_id = im.instrument_id
               AND l.status = :status
             WHERE im.index_id = :index_id
               AND im.effective_date <= :as_of
               AND (im.end_date IS NULL OR im.end_date >= :as_of)
             ORDER BY im.weight DESC'
        );
        $stmt->execute([
            ':index_id' => $indexId,
            ':as_of' => $asOfDate,
            ':status' => 'ACTIVE',
        ]);
        return $stmt->fetchAll();
    }

    public function createIndex(array $data): array
    {
        $this->validateRequired($data, ['name', 'exchange_id', 'currency']);
        $id = $this->uuid();
        $stmt = $this->db->prepare(
            'INSERT INTO market_master.index_master
             (index_id, name, exchange_id, currency, methodology, status)
             VALUES (:id, :name, :exchange_id, :currency, :methodology, :status)'
        );
        $stmt->execute([
            ':id' => $id,
            ':name' => $data['name'],
            ':exchange_id' => $data['exchange_id'],
            ':currency' => strtoupper($data['currency']),
            ':methodology' => $data['methodology'] ?? null,
            ':status' => $data['status'] ?? 'ACTIVE',
        ]);
        return $this->getIndex($id);
    }

    // ─── Market Calendar ─────────────────────────────────────────────────

    public function getCalendar(string $exchangeId, string $fromDate, string $toDate): array
    {
        return $this->getExchangeCalendar($exchangeId, $fromDate, $toDate);
    }

    public function createCalendarEntry(array $data): array
    {
        $this->validateRequired($data, ['exchange_id', 'date', 'day_type']);
        $id = $this->uuid();
        $stmt = $this->db->prepare(
            'INSERT INTO market_master.market_calendar
             (calendar_id, exchange_id, date, day_type, open_time, close_time, description)
             VALUES (:id, :exchange_id, :date, :day_type, :open_time, :close_time, :description)'
        );
        $stmt->execute([
            ':id' => $id,
            ':exchange_id' => $data['exchange_id'],
            ':date' => $data['date'],
            ':day_type' => $data['day_type'],
            ':open_time' => $data['open_time'] ?? null,
            ':close_time' => $data['close_time'] ?? null,
            ':description' => $data['description'] ?? null,
        ]);
        $stmt = $this->db->prepare(
            'SELECT * FROM market_master.market_calendar WHERE calendar_id = :id'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    // ─── Utility ─────────────────────────────────────────────────────────

    public function isTradingDay(string $exchangeId, string $date): bool
    {
        $cacheKey = "trading_day:{$exchangeId}:{$date}";
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached === 'true';
        }
        $stmt = $this->db->prepare(
            'SELECT day_type FROM market_master.market_calendar
             WHERE exchange_id = :exchange_id AND date = :date'
        );
        $stmt->execute([':exchange_id' => $exchangeId, ':date' => $date]);
        $row = $stmt->fetch();
        $isTrading = $row !== false && in_array($row['day_type'], ['TRADING', 'HALF_DAY'], true);
        $this->cache->set($cacheKey, $isTrading ? 'true' : 'false', 3600);
        return $isTrading;
    }

    public function getActiveListingsByExchange(string $exchangeId): array
    {
        $stmt = $this->db->prepare(
            "SELECT l.*, i.instrument_type, i.asset_class
             FROM market_master.listing l
             INNER JOIN market_master.instrument i ON i.instrument_id = l.instrument_id
             WHERE l.exchange_id = :exchange_id AND l.status = 'ACTIVE'
             ORDER BY l.ticker ASC"
        );
        $stmt->execute([':exchange_id' => $exchangeId]);
        return $stmt->fetchAll();
    }

    // ─── Private Helpers ─────────────────────────────────────────────────

    private function getExchangeByMic(string $mic): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM market_master.exchange WHERE mic_code = :mic');
        $stmt->execute([':mic' => $mic]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
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
