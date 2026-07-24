<?php

declare(strict_types=1);

namespace Platform\Core\Database;

use PDO;
use PDOException;
use Platform\Core\Application;

final class MySqlConnection
{
    private static ?PDO $instance = null;

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $app = Application::getInstance();
            $host = $app->getConfig('DB_HOST', '127.0.0.1');
            $port = $app->getConfig('DB_PORT', '3306');
            $dbName = $app->getConfig('DB_NAME', 'platform');
            $user = $app->getConfig('DB_USER', 'root');
            $pass = $app->getConfig('DB_PASS', '');
            $charset = $app->getConfig('DB_CHARSET', 'utf8mb4');

            $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset={$charset}";

            self::$instance = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        }
        return self::$instance;
    }
}
