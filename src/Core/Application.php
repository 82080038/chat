<?php

declare(strict_types=1);

namespace Platform\Core;

use Dotenv\Dotenv;

final class Application
{
    private static ?Application $instance = null;

    private array $config = [];
    private array $services = [];

    private function __construct()
    {
        $rootPath = dirname(__DIR__, 2);
        if (file_exists($rootPath . '/.env')) {
            $dotenv = Dotenv::createImmutable($rootPath);
            $dotenv->load();
        }
        $this->config = $_ENV;
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConfig(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    public function registerService(string $name, object $service): void
    {
        $this->services[$name] = $service;
    }

    public function getService(string $name): ?object
    {
        return $this->services[$name] ?? null;
    }

    public function getEnvironment(): string
    {
        return $this->getConfig('APP_ENV', 'development');
    }

    public function isDebug(): bool
    {
        return (bool) $this->getConfig('APP_DEBUG', false);
    }
}
