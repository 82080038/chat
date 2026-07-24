<?php

declare(strict_types=1);

namespace Platform\Core\Database;

use PDO;
use PDOException;
use Platform\Core\Application;

/**
 * PostgreSQL connection singleton for TimescaleDB time-series data.
 * Fail-safe: returns null if PostgreSQL is not configured or unavailable.
 */
final class PgSqlConnection
{
    private static ?PDO $instance = null;
    private static bool $attempted = false;

    public static function getInstance(): ?PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        if (self::$attempted) {
            return null;
        }

        self::$attempted = true;

        try {
            $app = Application::getInstance();
            $host = $app->getConfig('PG_HOST', '127.0.0.1');
            $port = $app->getConfig('PG_PORT', '5432');
            $dbName = $app->getConfig('DB_NAME_TS', 'market_tsdb');
            $user = $app->getConfig('PG_USER', 'postgres');
            $pass = $app->getConfig('PG_PASS', '');

            $dsn = "pgsql:host={$host};port={$port};dbname={$dbName}";

            self::$instance = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            self::$instance = null;
        }

        return self::$instance;
    }

    public static function isAvailable(): bool
    {
        return self::getInstance() !== null;
    }

    /**
     * Reset connection state (for testing).
     */
    public static function reset(): void
    {
        self::$instance = null;
        self::$attempted = false;
    }
}
