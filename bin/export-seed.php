<?php

declare(strict_types=1);

/**
 * Export live MySQL data to a reusable seed SQL file.
 *
 * Usage:
 *   php bin/export-seed.php              # master/reference data only
 *   php bin/export-seed.php --mode=full  # all tables (may be huge)
 *
 * The generated file is written to database/seeds/exported_seed_<mode>_<timestamp>.sql
 * and can be imported on another machine after migrations are run.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

$root = dirname(__DIR__);
if (file_exists($root . '/.env')) {
    $dotenv = Dotenv::createImmutable($root);
    $dotenv->safeLoad();
}

$host = $_ENV['DB_HOST'] ?? '127.0.0.1';
$port = (int) ($_ENV['DB_PORT'] ?? 3306);
$user = $_ENV['DB_USER'] ?? 'root';
$pass = $_ENV['DB_PASS'] ?? '';

$mode = 'master';
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--mode=')) {
        $mode = substr($arg, 7);
    }
}

$dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
$pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->exec('SET NAMES utf8mb4');

$systemSchemas = ['mysql', 'information_schema', 'performance_schema', 'sys', 'phpmyadmin', 'test'];

$logLikePatterns = [
    'api_access_log',
    'owner_activity_log',
    'audit_log',
    'session',
    'ohlcv_',
    'market_quote_',
];

$allowedSchemas = discoverAppSchemas($root);

$schemas = [];
$stmt = $pdo->query('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $name = $row['SCHEMA_NAME'];
    if (in_array($name, $allowedSchemas, true) && !in_array($name, $systemSchemas, true)) {
        $schemas[] = $name;
    }
}

if ($schemas === []) {
    throw new RuntimeException('No application schemas found. Did migrations run?');
}

$tables = [];
$placeholders = implode(',', array_fill(0, count($schemas), '?'));
$stmt = $pdo->prepare(
    "SELECT table_schema, table_name, table_rows "
    . "FROM information_schema.tables "
    . "WHERE table_schema IN ({$placeholders}) "
    . "AND table_type = 'BASE TABLE' "
    . "ORDER BY table_schema, table_name"
);
$stmt->execute($schemas);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $tableName = $row['table_name'];
    if ($mode !== 'full') {
        $skip = false;
        foreach ($logLikePatterns as $pattern) {
            if (str_contains($tableName, $pattern)) {
                $skip = true;
                break;
            }
        }
        if ($skip) {
            continue;
        }
    }
    $tables[] = [
        'schema' => $row['table_schema'],
        'table' => $tableName,
        'rows' => (int) $row['table_rows'],
    ];
}

$timestamp = date('Ymd_His');
$outDir = $root . '/database/seeds';
if (!is_dir($outDir)) {
    mkdir($outDir, 0755, true);
}
$outFile = "{$outDir}/exported_seed_{$mode}_{$timestamp}.sql";
$handle = fopen($outFile, 'wb');
if ($handle === false) {
    throw new RuntimeException("Cannot write to {$outFile}");
}

fwrite($handle, "-- Exported seed data\n");
fwrite($handle, "-- Mode: {$mode}\n");
fwrite($handle, "-- Generated: " . date('c') . "\n");
fwrite($handle, "-- Source host: {$host}\n");
fwrite($handle, "SET FOREIGN_KEY_CHECKS = 0;\n");
fwrite($handle, "SET NAMES utf8mb4;\n\n");

$totalRows = 0;
$totalTables = 0;

foreach ($tables as $table) {
    $schema = $table['schema'];
    $name = $table['table'];
    $fullName = "`{$schema}`.`{$name}`";

    echo "Exporting {$fullName} (approx {$table['rows']} rows)\n";

    $colStmt = $pdo->query("SHOW COLUMNS FROM {$fullName}");
    $columns = [];
    $generatedColumns = [];
    while ($col = $colStmt->fetch(PDO::FETCH_ASSOC)) {
        $extra = $col['Extra'] ?? '';
        if (str_contains($extra, 'GENERATED')) {
            $generatedColumns[] = $col['Field'];
            continue;
        }
        $columns[] = $col['Field'];
    }

    if ($columns === []) {
        continue;
    }

    $colList = '`' . implode('`, `', $columns) . '`';
    $selectCols = array_diff($columns, $generatedColumns);
    $selectList = '`' . implode('`, `', $selectCols) . '`';

    $rowsStmt = $pdo->query("SELECT {$selectList} FROM {$fullName}");
    $rowCount = 0;

    while ($row = $rowsStmt->fetch(PDO::FETCH_ASSOC)) {
        $values = [];
        foreach ($columns as $col) {
            if (array_key_exists($col, $row)) {
                $values[] = formatValue($pdo, $row[$col]);
            } else {
                $values[] = 'NULL';
            }
        }
        $line = "INSERT INTO {$fullName} ({$colList}) VALUES (" . implode(', ', $values) . ");\n";
        fwrite($handle, $line);
        $rowCount++;
    }

    if ($rowCount > 0) {
        $totalTables++;
        $totalRows += $rowCount;
        fwrite($handle, "\n");
    }
}

fwrite($handle, "SET FOREIGN_KEY_CHECKS = 1;\n");
fclose($handle);

echo "\n";
echo "Exported {$totalRows} rows from {$totalTables} tables to:\n";
echo "  {$outFile}\n";

function formatValue(PDO $pdo, mixed $value): string
{
    if ($value === null) {
        return 'NULL';
    }
    if (is_bool($value)) {
        return $value ? '1' : '0';
    }
    if (is_int($value) || is_float($value)) {
        if (is_nan($value) || is_infinite($value)) {
            return 'NULL';
        }
        return (string) $value;
    }
    return $pdo->quote((string) $value);
}

/**
 * Discover application schemas by scanning migration SQL files for
 * CREATE SCHEMA / CREATE DATABASE statements.
 */
function discoverAppSchemas(string $root): array
{
    $schemas = [];
    $migrationDir = $root . '/database/migrations';
    $files = glob($migrationDir . '/*.sql');
    if ($files === false) {
        return $schemas;
    }

    foreach ($files as $file) {
        $content = file_get_contents($file);
        if ($content === false) {
            continue;
        }

        if (preg_match_all(
            '/CREATE\s+(?:DATABASE|SCHEMA)\s+IF\s+NOT\s+EXISTS\s+`?([A-Za-z0-9_]+)`?/i',
            $content,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $match) {
                $name = $match[1];
                if (!in_array($name, $schemas, true)) {
                    $schemas[] = $name;
                }
            }
        }
    }

    return $schemas;
}
