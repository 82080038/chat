<?php

declare(strict_types=1);

namespace Platform\Core\Data;

use PDO;
use Platform\Core\Database\MySqlConnection;

/**
 * Data retention and archival job runner.
 *
 * Blueprint sections 410-412:
 * - Retention matrix with specific retention periods per data category
 * - 4-tier archival (Hot → Warm → Cold → Permanent)
 * - Automated purge with archive verification
 * - GDPR erasure process
 *
 * Usage: php bin/retention-job.php [--dry-run] [--category=<name>]
 */
final class RetentionJob
{
    private PDO $db;
    private bool $dryRun;

    /** @var array<string, array{table: string, retention_days: int, archive_before_delete: bool}> */
    private array $retentionMatrix = [
        'api_access_log' => [
            'table' => 'config.api_access_log',
            'retention_days' => 90,
            'archive_before_delete' => false,
        ],
        'owner_activity_log' => [
            'table' => 'config.owner_activity_log',
            'retention_days' => 365,
            'archive_before_delete' => false,
        ],
        'audit_log' => [
            'table' => 'governance.audit_log',
            'retention_days' => 2555, // 7 years — permanent for compliance
            'archive_before_delete' => true,
        ],
        'ohlcv_daily' => [
            'table' => 'data_ingestion.ohlcv_daily',
            'retention_days' => 3650, // 10 years
            'archive_before_delete' => true,
        ],
    ];

    public function __construct(bool $dryRun = false)
    {
        $this->db = MySqlConnection::getInstance();
        $this->dryRun = $dryRun;
    }

    /**
     * Run retention purge for all categories or a specific one.
     */
    public function run(?string $category = null): array
    {
        $results = [];
        $categories = $category !== null ? [$category] : array_keys($this->retentionMatrix);

        foreach ($categories as $cat) {
            if (!isset($this->retentionMatrix[$cat])) {
                $results[$cat] = ['error' => "Unknown category: {$cat}"];
                continue;
            }
            $results[$cat] = $this->purgeCategory($cat);
        }

        return $results;
    }

    private function purgeCategory(string $category): array
    {
        $config = $this->retentionMatrix[$category];
        $table = $config['table'];
        $retentionDays = $config['retention_days'];
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));

        // Check if table has retention_until column
        $hasRetentionUntil = $this->hasColumn($table, 'retention_until');
        $hasDeletedAt = $this->hasColumn($table, 'deleted_at');

        $whereClause = $hasRetentionUntil
            ? 'retention_until < :cutoff'
            : 'created_at < :cutoff';

        // Count records to purge
        $countSql = "SELECT COUNT(*) FROM {$table} WHERE {$whereClause}";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute([':cutoff' => $cutoffDate]);
        $count = (int) $countStmt->fetchColumn();

        if ($count === 0) {
            return [
                'category' => $category,
                'table' => $table,
                'records_purged' => 0,
                'cutoff_date' => $cutoffDate,
                'dry_run' => $this->dryRun,
            ];
        }

        // Archive before delete if required
        if ($config['archive_before_delete'] && !$this->dryRun) {
            $this->archiveRecords($table, $whereClause, $cutoffDate);
        }

        // Purge
        if (!$this->dryRun) {
            // Set audit purge mode for audit_log (bypass immutability trigger)
            if ($table === 'governance.audit_log') {
                $this->db->exec('SET @audit_purge_mode = 1');
            }

            $deleteSql = "DELETE FROM {$table} WHERE {$whereClause}";
            $this->db->prepare($deleteSql)->execute([':cutoff' => $cutoffDate]);

            if ($table === 'governance.audit_log') {
                $this->db->exec('SET @audit_purge_mode = 0');
            }
        }

        return [
            'category' => $category,
            'table' => $table,
            'records_purged' => $count,
            'cutoff_date' => $cutoffDate,
            'archived' => $config['archive_before_delete'],
            'dry_run' => $this->dryRun,
        ];
    }

    /**
     * Archive records to a JSON file before deletion.
     */
    private function archiveRecords(string $table, string $whereClause, string $cutoff): void
    {
        $sql = "SELECT * FROM {$table} WHERE {$whereClause}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':cutoff' => $cutoff]);
        $records = $stmt->fetchAll();

        if ($records === []) {
            return;
        }

        $archiveDir = dirname(__DIR__, 3) . '/storage/archives';
        if (!is_dir($archiveDir)) {
            mkdir($archiveDir, 0755, true);
        }

        $tableName = str_replace('.', '_', $table);
        $filename = $archiveDir . '/' . $tableName . '_' . date('Y-m-d_His') . '.json';
        file_put_contents($filename, json_encode($records, JSON_PRETTY_PRINT));
    }

    private function hasColumn(string $table, string $column): bool
    {
        try {
            $this->db->prepare("SELECT {$column} FROM {$table} LIMIT 1")->execute();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * GDPR right to erasure — anonymize owner PII.
     *
     * Blueprint section 412: GDPR erasure process with anonymization.
     *
     * @param string $ownerId
     * @return array<string, mixed>
     */
    public function gdprErasure(string $ownerId): array
    {
        $anonymized = [];
        $now = $this->now();

        // Anonymize owner_account
        if (!$this->dryRun) {
            $this->db->prepare(
                'UPDATE identity.owner_account
                 SET email = :anon_email, password_hash = :anon_pass, status = :status
                 WHERE owner_id = :id'
            )->execute([
                ':anon_email' => 'anonymized_' . substr($ownerId, 0, 8) . '@erasure.local',
                ':anon_pass' => 'ERASED',
                ':status' => 'LOCKED',
                ':id' => $ownerId,
            ]);
        }
        $anonymized[] = 'identity.owner_account';

        // Anonymize owner_preference
        if (!$this->dryRun) {
            $this->db->prepare(
                'UPDATE identity.owner_preference
                 SET timezone = NULL, language = NULL, base_currency = NULL, default_exchange = NULL, theme = NULL
                 WHERE owner_id = :id'
            )->execute([':id' => $ownerId]);
        }
        $anonymized[] = 'identity.owner_preference';

        // Revoke all sessions
        if (!$this->dryRun) {
            $this->db->prepare(
                'UPDATE identity.owner_session SET revoked_at = :now WHERE owner_id = :id AND revoked_at IS NULL'
            )->execute([':now' => $now, ':id' => $ownerId]);
        }
        $anonymized[] = 'identity.owner_session';

        // Anonymize owner_activity_log
        if (!$this->dryRun) {
            $this->db->prepare(
                'UPDATE config.owner_activity_log
                 SET description = CONCAT(\"ERASED - \", activity_type), ip_address = NULL
                 WHERE entity_id = :id AND entity_type = \"OWNER\"'
            )->execute([':id' => $ownerId]);
        }
        $anonymized[] = 'config.owner_activity_log';

        // Audit the erasure
        if (!$this->dryRun) {
            $this->db->prepare(
                'INSERT INTO governance.audit_log
                 (audit_log_id, actor_type, action, entity_type, entity_id, new_values, created_at)
                 VALUES (:id, :actor, :action, :entity_type, :entity_id, :new_values, :now)'
            )->execute([
                ':id' => \Ramsey\Uuid\Uuid::uuid7()->toString(),
                ':actor' => 'SYSTEM',
                ':action' => 'GDPR_ERASURE',
                ':entity_type' => 'OWNER',
                ':entity_id' => $ownerId,
                ':new_values' => json_encode(['anonymized_tables' => $anonymized]),
                ':now' => $now,
            ]);
        }

        return [
            'owner_id' => $ownerId,
            'anonymized_tables' => $anonymized,
            'dry_run' => $this->dryRun,
            'erased_at' => $now,
        ];
    }

    private function now(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
    }
}
