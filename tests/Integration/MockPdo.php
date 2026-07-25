<?php

declare(strict_types=1);

namespace Platform\Tests\Integration;

use PDO;
use PDOStatement;

/**
 * Mock PDO that simulates database operations in-memory.
 */
final class MockPdo extends PDO
{
    /** @var array<string, array<int, array<string, mixed>>> */
    private array $tables = [];

    public function __construct()
    {
    }

    public function prepare(
        string $sql,
        array $options = []
    ): PDOStatement|false {
        return new MockPdoStatement($this, $sql);
    }

    public function exec(string $sql): int|false
    {
        return 0;
    }

    public function beginTransaction(): bool
    {
        return true;
    }

    public function commit(): bool
    {
        return true;
    }

    public function rollBack(): bool
    {
        return true;
    }

    public function inTransaction(): bool
    {
        return false;
    }

    // ─── Internal API ────────────────────────────────────────────────────

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchAllRows(string $table): array
    {
        return $this->tables[$table] ?? [];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertRow(string $table, array $data): void
    {
        $this->tables[$table] ??= [];
        $this->tables[$table][] = $data;
    }

    public function updateWhere(
        string $table,
        string $whereColumn,
        mixed $whereValue,
        array $data
    ): int {
        $rows = $this->tables[$table] ?? [];
        $count = 0;
        foreach ($rows as $i => $row) {
            if (($row[$whereColumn] ?? null) == $whereValue) {
                foreach ($data as $key => $val) {
                    $rows[$i][$key] = $val;
                }
                $count++;
            }
        }
        $this->tables[$table] = $rows;
        return $count;
    }
}
