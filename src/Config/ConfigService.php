<?php

declare(strict_types=1);

namespace Platform\Config;

use PDO;
use Platform\Core\Application;
use Platform\Core\BaseService;
use Platform\Core\Cache\CacheStoreInterface;
use Platform\Core\Cache\RedisCacheStore;
use Platform\Core\Exceptions\ApiException;

final class ConfigService extends BaseService implements ConfigServiceInterface
{
    private CacheStoreInterface $cache;

    public function __construct(?PDO $db = null, ?CacheStoreInterface $cache = null)
    {
        parent::__construct($db);
        $this->cache = $cache ?? new RedisCacheStore();
    }
    public function listConfigurations(array $filters, int $page, int $perPage): array
    {
        [$page, $perPage, $offset] = $this->parsePagination(['page' => $page, 'per_page' => $perPage]);
        $where = [];
        $params = [];
        if (isset($filters['category'])) {
            $where[] = 'category = :category';
            $params[':category'] = $filters['category'];
        }
        if (isset($filters['status'])) {
            $where[] = 'status = :status';
            $params[':status'] = $filters['status'];
        }
        $clause = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
        $total = $this->countRows('config.configuration', $clause, $params);
        $stmt = $this->db->prepare(
            "SELECT * FROM config.configuration {$clause} "
            . "ORDER BY config_key, version DESC LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return $this->paginate(array_map([$this, 'maskConfiguration'], $stmt->fetchAll()), $total, $page, $perPage);
    }

    public function createConfiguration(array $data): array
    {
        $this->validateRequired($data, ['config_key', 'config_value', 'config_type']);
        $type = strtoupper((string) $data['config_type']);
        $this->assertConfigType($type);
        if (($data['is_sensitive'] ?? false) && $type !== 'ENCRYPTED') {
            throw new ApiException(
                422,
                'SENSITIVE_CONFIG_MUST_BE_ENCRYPTED',
                'Sensitive configuration must use ENCRYPTED type'
            );
        }
        $key = trim((string) $data['config_key']);
        if (!preg_match('/^[a-z0-9._-]{2,200}$/', $key)) {
            throw new ApiException(422, 'VALIDATION_ERROR', 'Configuration key format is invalid');
        }
        if ($this->getConfig($key) !== null) {
            throw new ApiException(409, 'CONFIG_EXISTS', 'An active configuration with this key already exists');
        }

        $id = $this->uuid();
        $stmt = $this->db->prepare(
            'INSERT INTO config.configuration
             (config_id, config_key, config_value, config_type, category, is_sensitive,
              description, effective_from, status, version, created_at)
             VALUES
             (:id, :key, :value, :type, :category, :sensitive,
              :description, :effective_from, :status, 1, :created_at)'
        );
        $now = $this->now();
        $stmt->execute([
            ':id' => $id,
            ':key' => $key,
            ':value' => $this->serializeValue($data['config_value'], $type),
            ':type' => $type,
            ':category' => $data['category'] ?? null,
            ':sensitive' => (int) ($data['is_sensitive'] ?? ($type === 'ENCRYPTED')),
            ':description' => $data['description'] ?? null,
            ':effective_from' => $now,
            ':status' => 'ACTIVE',
            ':created_at' => $now,
        ]);
        return $this->requireConfiguration($id);
    }

    public function getConfiguration(string $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM config.configuration WHERE config_id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ? $this->maskConfiguration($row) : null;
    }

    public function getConfig(string $key): ?array
    {
        $cacheKey = 'cache:config:' . $key;
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            $decoded = json_decode($cached, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $stmt = $this->db->prepare(
            'SELECT * FROM config.configuration
             WHERE config_key = :key AND status = :status
               AND effective_from <= UTC_TIMESTAMP(6)
               AND (effective_until IS NULL OR effective_until > UTC_TIMESTAMP(6))
             ORDER BY version DESC LIMIT 1'
        );
        $stmt->execute([':key' => $key, ':status' => 'ACTIVE']);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $result = $this->maskConfiguration($row);
        $this->cache->set($cacheKey, json_encode($result, JSON_THROW_ON_ERROR), 300);
        return $result;
    }

    public function updateConfiguration(string $id, array $data): array
    {
        $current = $this->rawConfiguration($id);
        if ($current === null) {
            throw new ApiException(404, 'CONFIG_NOT_FOUND', 'Configuration was not found');
        }
        $type = strtoupper((string) ($data['config_type'] ?? $current['config_type']));
        $this->assertConfigType($type);
        $sensitive = (bool) ($data['is_sensitive'] ?? $current['is_sensitive']);
        if ($sensitive && $type !== 'ENCRYPTED') {
            throw new ApiException(
                422,
                'SENSITIVE_CONFIG_MUST_BE_ENCRYPTED',
                'Sensitive configuration must use ENCRYPTED type'
            );
        }
        $value = $data['config_value'] ?? $this->deserializeValue($current['config_value'], $current['config_type']);
        $newId = $this->uuid();
        $now = $this->now();

        $this->db->beginTransaction();
        try {
            $archive = $this->db->prepare(
                'UPDATE config.configuration SET status = :status, effective_until = :until WHERE config_id = :id'
            );
            $archive->execute([':status' => 'ARCHIVED', ':until' => $now, ':id' => $id]);
            $insert = $this->db->prepare(
                'INSERT INTO config.configuration
                 (config_id, config_key, config_value, config_type, category, is_sensitive,
                  description, effective_from, status, version, created_at)
                 VALUES
                 (:id, :key, :value, :type, :category, :sensitive,
                  :description, :effective_from, :status, :version, :created_at)'
            );
            $insert->execute([
                ':id' => $newId,
                ':key' => $current['config_key'],
                ':value' => $this->serializeValue($value, $type),
                ':type' => $type,
                ':category' => $data['category'] ?? $current['category'],
                ':sensitive' => (int) $sensitive,
                ':description' => $data['description'] ?? $current['description'],
                ':effective_from' => $now,
                ':status' => 'ACTIVE',
                ':version' => (int) $current['version'] + 1,
                ':created_at' => $now,
            ]);
            $this->db->commit();
            $this->cache->delete('cache:config:' . $current['config_key']);
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
        return $this->requireConfiguration($newId);
    }

    public function listFeatureFlags(int $page, int $perPage): array
    {
        [$page, $perPage, $offset] = $this->parsePagination(['page' => $page, 'per_page' => $perPage]);
        $total = $this->countRows('config.feature_flag');
        $stmt = $this->db->query(
            "SELECT * FROM config.feature_flag ORDER BY flag_key LIMIT {$perPage} OFFSET {$offset}"
        );
        return $this->paginate($stmt->fetchAll(), $total, $page, $perPage);
    }

    public function createFeatureFlag(array $data): array
    {
        $this->validateRequired($data, ['flag_key', 'flag_name']);
        $id = $this->uuid();
        $stmt = $this->db->prepare(
            'INSERT INTO config.feature_flag
             (flag_id, flag_key, flag_name, description, enabled, effective_from, status, created_at)
             VALUES (:id, :key, :name, :description, :enabled, :effective_from, :status, :created_at)'
        );
        $now = $this->now();
        try {
            $stmt->execute([
                ':id' => $id,
                ':key' => trim((string) $data['flag_key']),
                ':name' => trim((string) $data['flag_name']),
                ':description' => $data['description'] ?? null,
                ':enabled' => (int) ($data['enabled'] ?? false),
                ':effective_from' => $now,
                ':status' => ($data['enabled'] ?? false) ? 'ACTIVE' : 'DISABLED',
                ':created_at' => $now,
            ]);
        } catch (\PDOException $exception) {
            if ($exception->getCode() === '23000') {
                throw new ApiException(409, 'FEATURE_FLAG_EXISTS', 'Feature flag key already exists');
            }
            throw $exception;
        }
        return $this->requireFeatureFlag($id);
    }

    public function getFeatureFlag(string $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM config.feature_flag WHERE flag_id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function getFeatureFlagByKey(string $key): ?array
    {
        $cacheKey = 'cache:feature_flag:' . $key;
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            $decoded = json_decode($cached, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        $stmt = $this->db->prepare('SELECT * FROM config.feature_flag WHERE flag_key = :key LIMIT 1');
        $stmt->execute([':key' => $key]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $this->cache->set($cacheKey, json_encode($row, JSON_THROW_ON_ERROR), 60);
        return $row;
    }

    public function updateFeatureFlag(string $id, array $data): array
    {
        $current = $this->getFeatureFlag($id);
        if ($current === null) {
            throw new ApiException(404, 'FEATURE_FLAG_NOT_FOUND', 'Feature flag was not found');
        }
        $enabled = array_key_exists('enabled', $data) ? (bool) $data['enabled'] : (bool) $current['enabled'];
        $stmt = $this->db->prepare(
            'UPDATE config.feature_flag
             SET flag_name = :name, description = :description, enabled = :enabled,
                 status = :status, effective_until = :effective_until
             WHERE flag_id = :id'
        );
        $stmt->execute([
            ':name' => $data['flag_name'] ?? $current['flag_name'],
            ':description' => $data['description'] ?? $current['description'],
            ':enabled' => (int) $enabled,
            ':status' => $data['status'] ?? ($enabled ? 'ACTIVE' : 'DISABLED'),
            ':effective_until' => $data['effective_until'] ?? $current['effective_until'],
            ':id' => $id,
        ]);
        $this->cache->delete('cache:feature_flag:' . $current['flag_key']);
        return $this->requireFeatureFlag($id);
    }

    public function isFeatureEnabled(string $key): bool
    {
        $flag = $this->getFeatureFlagByKey($key);
        if ($flag === null || $flag['status'] !== 'ACTIVE' || !(bool) $flag['enabled']) {
            return false;
        }
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        return ($flag['effective_from'] === null || new \DateTimeImmutable($flag['effective_from']) <= $now)
            && ($flag['effective_until'] === null || new \DateTimeImmutable($flag['effective_until']) > $now);
    }

    public function listSystemParameters(int $page, int $perPage): array
    {
        [$page, $perPage, $offset] = $this->parsePagination(['page' => $page, 'per_page' => $perPage]);
        $total = $this->countRows('config.system_parameter');
        $stmt = $this->db->query(
            "SELECT * FROM config.system_parameter ORDER BY param_key LIMIT {$perPage} OFFSET {$offset}"
        );
        return $this->paginate($stmt->fetchAll(), $total, $page, $perPage);
    }

    public function getSystemParameter(string $key): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM config.system_parameter WHERE param_key = :key LIMIT 1');
        $stmt->execute([':key' => $key]);
        return $stmt->fetch() ?: null;
    }

    public function updateSystemParameter(string $key, mixed $value): array
    {
        $parameter = $this->getSystemParameter($key);
        if ($parameter === null) {
            throw new ApiException(404, 'PARAMETER_NOT_FOUND', 'System parameter was not found');
        }
        if ((bool) $parameter['is_readonly']) {
            throw new ApiException(422, 'PARAMETER_READONLY', 'System parameter is read-only');
        }
        $stmt = $this->db->prepare(
            'UPDATE config.system_parameter SET param_value = :value, updated_at = :updated_at WHERE param_key = :key'
        );
        $stmt->execute([
            ':value' => $this->serializeValue($value, $parameter['param_type']),
            ':updated_at' => $this->now(),
            ':key' => $key,
        ]);
        return $this->getSystemParameter($key) ?? [];
    }

    public function listStorageObjects(int $page, int $perPage): array
    {
        [$page, $perPage, $offset] = $this->parsePagination(['page' => $page, 'per_page' => $perPage]);
        $total = $this->countRows('config.storage_object', 'WHERE deleted_at IS NULL');
        $stmt = $this->db->query(
            'SELECT * FROM config.storage_object WHERE deleted_at IS NULL '
            . "ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}"
        );
        return $this->paginate($stmt->fetchAll(), $total, $page, $perPage);
    }

    public function registerStorageObject(array $data): array
    {
        $this->validateRequired($data, ['bucket', 'path']);
        $id = $this->uuid();
        $stmt = $this->db->prepare(
            'INSERT INTO config.storage_object
             (object_id, bucket, path, checksum, checksum_algorithm, content_type,
              content_length, version, entity_type, entity_id, created_at)
             VALUES
             (:id, :bucket, :path, :checksum, :algorithm, :content_type,
              :content_length, :version, :entity_type, :entity_id, :created_at)'
        );
        $stmt->execute([
            ':id' => $id,
            ':bucket' => $data['bucket'],
            ':path' => $data['path'],
            ':checksum' => $data['checksum'] ?? null,
            ':algorithm' => $data['checksum_algorithm'] ?? 'SHA256',
            ':content_type' => $data['content_type'] ?? null,
            ':content_length' => $data['content_length'] ?? null,
            ':version' => $data['version'] ?? '1',
            ':entity_type' => $data['entity_type'] ?? null,
            ':entity_id' => $data['entity_id'] ?? null,
            ':created_at' => $this->now(),
        ]);
        return $this->requireStorageObject($id);
    }

    public function getStorageObject(string $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM config.storage_object WHERE object_id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function softDeleteStorageObject(string $id): void
    {
        $stmt = $this->db->prepare(
            'UPDATE config.storage_object SET deleted_at = :deleted_at WHERE object_id = :id AND deleted_at IS NULL'
        );
        $stmt->execute([':deleted_at' => $this->now(), ':id' => $id]);
        if ($stmt->rowCount() !== 1) {
            throw new ApiException(404, 'STORAGE_OBJECT_NOT_FOUND', 'Storage object was not found');
        }
    }

    public function listApiAccessLogs(int $page, int $perPage): array
    {
        return $this->listLogTable('config.api_access_log', $page, $perPage);
    }

    public function logApiAccess(array $data): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO config.api_access_log
             (log_id, endpoint, method, status_code, response_time_ms, request_size,
              response_size, ip_address, user_agent, correlation_id, created_at, retention_until)
             VALUES
             (:id, :endpoint, :method, :status_code, :response_time_ms, :request_size,
              :response_size, :ip_address, :user_agent, :correlation_id, :created_at, :retention_until)'
        );
        $stmt->execute([
            ':id' => $this->uuid(),
            ':endpoint' => $data['endpoint'],
            ':method' => $data['method'],
            ':status_code' => $data['status_code'],
            ':response_time_ms' => $data['response_time_ms'] ?? null,
            ':request_size' => $data['request_size'] ?? null,
            ':response_size' => $data['response_size'] ?? null,
            ':ip_address' => $data['ip_address'] ?? null,
            ':user_agent' => $data['user_agent'] ?? null,
            ':correlation_id' => $data['correlation_id'] ?? null,
            ':created_at' => $this->now(),
            ':retention_until' => $data['retention_until'] ?? null,
        ]);
    }

    public function listOwnerActivityLogs(int $page, int $perPage): array
    {
        return $this->listLogTable('config.owner_activity_log', $page, $perPage);
    }

    public function logOwnerActivity(array $data): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO config.owner_activity_log
             (activity_id, activity_type, entity_type, entity_id, description,
              ip_address, created_at, retention_until)
             VALUES
             (:id, :activity_type, :entity_type, :entity_id, :description,
              :ip_address, :created_at, :retention_until)'
        );
        $stmt->execute([
            ':id' => $this->uuid(),
            ':activity_type' => $data['activity_type'],
            ':entity_type' => $data['entity_type'] ?? null,
            ':entity_id' => $data['entity_id'] ?? null,
            ':description' => $data['description'] ?? null,
            ':ip_address' => $data['ip_address'] ?? null,
            ':created_at' => $this->now(),
            ':retention_until' => $data['retention_until'] ?? null,
        ]);
    }

    private function rawConfiguration(string $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM config.configuration WHERE config_id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    private function requireConfiguration(string $id): array
    {
        $row = $this->getConfiguration($id);
        if ($row === null) {
            throw new ApiException(404, 'CONFIG_NOT_FOUND', 'Configuration was not found');
        }
        return $row;
    }

    private function requireFeatureFlag(string $id): array
    {
        $row = $this->getFeatureFlag($id);
        if ($row === null) {
            throw new ApiException(404, 'FEATURE_FLAG_NOT_FOUND', 'Feature flag was not found');
        }
        return $row;
    }

    private function requireStorageObject(string $id): array
    {
        $row = $this->getStorageObject($id);
        if ($row === null) {
            throw new ApiException(404, 'STORAGE_OBJECT_NOT_FOUND', 'Storage object was not found');
        }
        return $row;
    }

    private function countRows(string $table, string $clause = '', array $params = []): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM {$table} {$clause}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    private function listLogTable(string $table, int $page, int $perPage): array
    {
        [$page, $perPage, $offset] = $this->parsePagination(['page' => $page, 'per_page' => $perPage]);
        $total = $this->countRows($table);
        $stmt = $this->db->query("SELECT * FROM {$table} ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}");
        return $this->paginate($stmt->fetchAll(), $total, $page, $perPage);
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

    private function assertConfigType(string $type): void
    {
        if (!in_array($type, ['STRING', 'INTEGER', 'DECIMAL', 'BOOLEAN', 'JSON', 'ENCRYPTED'], true)) {
            throw new ApiException(422, 'VALIDATION_ERROR', 'Unsupported configuration type');
        }
    }

    private function serializeValue(mixed $value, string $type): string
    {
        return match (strtoupper($type)) {
            'JSON' => json_encode($value, JSON_THROW_ON_ERROR),
            'BOOLEAN' => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false',
            'INTEGER' => (string) (int) $value,
            'DECIMAL' => (string) (float) $value,
            'ENCRYPTED' => $this->encryptValue((string) $value),
            default => (string) $value,
        };
    }

    private function deserializeValue(string $value, string $type): mixed
    {
        return match (strtoupper($type)) {
            'JSON' => json_decode($value, true, 512, JSON_THROW_ON_ERROR),
            'BOOLEAN' => $value === 'true',
            'INTEGER' => (int) $value,
            'DECIMAL' => (float) $value,
            'ENCRYPTED' => $this->decryptValue($value),
            default => $value,
        };
    }

    private function maskConfiguration(array $row): array
    {
        if ((bool) $row['is_sensitive'] || $row['config_type'] === 'ENCRYPTED') {
            $row['config_value'] = '********';
            return $row;
        }
        $row['config_value'] = $this->deserializeValue((string) $row['config_value'], $row['config_type']);
        return $row;
    }

    private function encryptValue(string $value): string
    {
        $key = $this->encryptionKey();
        $nonce = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($value, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag);
        if ($ciphertext === false) {
            throw new ApiException(500, 'ENCRYPTION_FAILED', 'Configuration value encryption failed');
        }
        return base64_encode($nonce . $tag . $ciphertext);
    }

    private function decryptValue(string $value): string
    {
        $payload = base64_decode($value, true);
        if ($payload === false || strlen($payload) < 28) {
            throw new ApiException(500, 'DECRYPTION_FAILED', 'Encrypted configuration payload is invalid');
        }
        $nonce = substr($payload, 0, 12);
        $tag = substr($payload, 12, 16);
        $ciphertext = substr($payload, 28);
        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $this->encryptionKey(),
            OPENSSL_RAW_DATA,
            $nonce,
            $tag
        );
        if ($plaintext === false) {
            throw new ApiException(500, 'DECRYPTION_FAILED', 'Configuration value decryption failed');
        }
        return $plaintext;
    }

    private function encryptionKey(): string
    {
        $encoded = (string) Application::getInstance()->getConfig('APP_ENCRYPTION_KEY', '');
        $key = base64_decode($encoded, true);
        if ($key === false || strlen($key) !== 32) {
            throw new ApiException(
                500,
                'ENCRYPTION_NOT_CONFIGURED',
                'APP_ENCRYPTION_KEY must be a base64-encoded 32-byte key'
            );
        }
        return $key;
    }
}
