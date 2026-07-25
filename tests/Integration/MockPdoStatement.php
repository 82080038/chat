<?php

declare(strict_types=1);

namespace Platform\Tests\Integration;

use PDO;
use PDOStatement;

/**
 * Mock PDOStatement that interprets SQL and uses MockPdo storage.
 */
final class MockPdoStatement extends PDOStatement
{
    private MockPdo $pdo;
    private string $sql;
    /** @var array<string, mixed> */
    private array $params = [];
    /** @var array<int, array<string, mixed>> */
    private array $resultRows = [];
    private int $fetchIndex = 0;
    private int $rowCount = 0;

    public function __construct(MockPdo $pdo, string $sql)
    {
        $this->pdo = $pdo;
        $this->sql = $sql;
    }

    /** @var array<string, mixed> */
    private array $boundValues = [];

    public function bindValue(
        string|int $parameter,
        mixed $value,
        int $type = PDO::PARAM_STR
    ): bool {
        $this->boundValues[(string) $parameter] = $value;
        return true;
    }

    public function bindParam(
        string|int $param,
        mixed &$var,
        int $type = PDO::PARAM_STR,
        int $maxLength = 0,
        mixed $driverOptions = null
    ): bool {
        $this->boundValues[(string) $param] = &$var;
        return true;
    }

    public function execute(?array $params = null): bool
    {
        $this->params = array_merge($this->boundValues, $params ?? []);
        $sqlLower = strtolower(trim($this->sql));

        if (str_starts_with($sqlLower, 'insert')) {
            $this->handleInsert();
            return true;
        }
        if (str_starts_with($sqlLower, 'update')) {
            $this->handleUpdate();
            return true;
        }
        if (str_starts_with($sqlLower, 'select count(*)')) {
            $this->resultRows = [['count' => $this->handleCount()]];
            return true;
        }
        if (str_starts_with($sqlLower, 'select')) {
            $this->resultRows = $this->handleSelect();
            return true;
        }
        return true;
    }

    public function fetch(
        int $mode = PDO::FETCH_BOTH,
        int $cursorMode = 0,
        int $cursorOffset = 0
    ): array|false {
        if ($this->fetchIndex < count($this->resultRows)) {
            return $this->resultRows[$this->fetchIndex++];
        }
        return false;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchAll(
        int $mode = PDO::FETCH_BOTH,
        ...$args
    ): array {
        return $this->resultRows;
    }

    public function fetchColumn(int $column = 0): mixed
    {
        if ($this->resultRows === []) {
            return false;
        }
        $row = $this->resultRows[0];
        $values = array_values($row);
        return $values[$column] ?? false;
    }

    public function rowCount(): int
    {
        return $this->rowCount;
    }

    // ─── SQL Handlers ────────────────────────────────────────────────────

    private function getTable(): string
    {
        $sql = $this->sql;
        if (preg_match('/\bfrom\s+(\S+)/i', $sql, $m)) {
            return str_replace('`', '', $m[1]);
        }
        if (preg_match('/\binto\s+(\S+)/i', $sql, $m)) {
            return str_replace('`', '', $m[1]);
        }
        if (preg_match('/\bupdate\s+(\S+)/i', $sql, $m)) {
            return str_replace('`', '', $m[1]);
        }
        return '';
    }

    private function handleInsert(): void
    {
        $table = $this->getTable();
        $row = [];
        if (
            preg_match(
                '/insert\s+into\s+\S+\s*\(([^)]+)\)\s*values\s*\(([^)]+)\)/i',
                $this->sql,
                $m
            )
        ) {
            $cols = array_map('trim', explode(',', $m[1]));
            $placeholders = array_map('trim', explode(',', $m[2]));
            for ($i = 0; $i < count($cols); $i++) {
                $col = $cols[$i];
                $ph = trim($placeholders[$i]);
                $row[$col] = $this->params[$ph] ?? null;
            }
        }
        $this->pdo->insertRow($table, $row);
        $this->rowCount = 1;
    }

    private function handleUpdate(): void
    {
        $table = $this->getTable();
        $setCols = [];
        if (preg_match('/set\s+(.+?)(?:\bwhere\b|$)/is', $this->sql, $m)) {
            $setPart = $m[1];
            // Parse column = :param pairs
            preg_match_all(
                '/(\w+)\s*=\s*:(\w+)/i',
                $setPart,
                $pairs,
                PREG_SET_ORDER
            );
            foreach ($pairs as $pair) {
                $col = $pair[1];
                $paramKey = ':' . $pair[2];
                $setCols[$col] = $this->params[$paramKey] ?? null;
            }
            // Parse column = literal pairs (e.g., human_override = 1)
            preg_match_all(
                '/(\w+)\s*=\s*(\d+(?:\.\d+)?|NULL|TRUE|FALSE)/i',
                $setPart,
                $literals,
                PREG_SET_ORDER
            );
            foreach ($literals as $lit) {
                $col = $lit[1];
                if (!isset($setCols[$col])) {
                    $val = $lit[2];
                    if (strtoupper($val) === 'NULL') {
                        $setCols[$col] = null;
                    } elseif (strtoupper($val) === 'TRUE') {
                        $setCols[$col] = 1;
                    } elseif (strtoupper($val) === 'FALSE') {
                        $setCols[$col] = 0;
                    } else {
                        $setCols[$col] = strpos($val, '.')
                            ? (float) $val
                            : (int) $val;
                    }
                }
            }
        }
        // Extract WHERE: support single condition (first match)
        $whereCol = null;
        $whereVal = null;
        if (preg_match('/where\s+(\w+)\s*=\s*:(\w+)/i', $this->sql, $m)) {
            $whereCol = $m[1];
            $whereVal = $this->params[':' . $m[2]] ?? null;
        }
        if ($whereCol !== null) {
            $this->rowCount = $this->pdo->updateWhere(
                $table,
                $whereCol,
                $whereVal,
                $setCols
            );
        }
    }

    private function handleCount(): int
    {
        $table = $this->getTable();
        $rows = $this->pdo->fetchAllRows($table);
        $rows = $this->applyWhereConditions($rows);
        return count($rows);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function handleSelect(): array
    {
        $table = $this->getTable();
        $rows = $this->pdo->fetchAllRows($table);

        // Handle COALESCE(SUM(col), 0) aggregate queries
        if (
            preg_match(
                '/coalesce\s*\(\s*sum\s*\(\s*(\w+)\s*\)/i',
                $this->sql,
                $m
            )
        ) {
            $sumCol = $m[1];
            $rows = $this->applyWhereConditions($rows);
            $total = 0.0;
            foreach ($rows as $r) {
                $total += (float) ($r[$sumCol] ?? 0);
            }
            return [['total_filled' => $total]];
        }

        // Apply all WHERE conditions
        $rows = $this->applyWhereConditions($rows);

        // Apply LIMIT and OFFSET
        if (
            preg_match(
                '/limit\s+(\d+)\s+offset\s+(\d+)/i',
                $this->sql,
                $m
            )
        ) {
            $rows = array_slice($rows, (int) $m[2], (int) $m[1]);
        } elseif (preg_match('/limit\s+(\d+)/i', $this->sql, $m)) {
            $rows = array_slice($rows, 0, (int) $m[1]);
        }
        return $rows;
    }

    /**
     * Apply all WHERE col = :param conditions to filter rows.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function applyWhereConditions(array $rows): array
    {
        preg_match_all(
            '/(\w+)\s*=\s*:(\w+)/i',
            $this->sql,
            $matches,
            PREG_SET_ORDER
        );
        foreach ($matches as $match) {
            $col = $match[1];
            $paramKey = ':' . $match[2];
            $val = $this->params[$paramKey] ?? null;
            $rows = array_values(array_filter(
                $rows,
                fn ($r) => ($r[$col] ?? null) == $val
            ));
        }
        return $rows;
    }
}
