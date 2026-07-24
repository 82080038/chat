<?php

declare(strict_types=1);

namespace Platform\Config;

interface ConfigServiceInterface
{
    public function listConfigurations(array $filters, int $page, int $perPage): array;

    public function createConfiguration(array $data): array;

    public function getConfiguration(string $id): ?array;

    public function getConfig(string $key): ?array;

    public function updateConfiguration(string $id, array $data): array;

    public function listFeatureFlags(int $page, int $perPage): array;

    public function createFeatureFlag(array $data): array;

    public function getFeatureFlag(string $id): ?array;

    public function getFeatureFlagByKey(string $key): ?array;

    public function updateFeatureFlag(string $id, array $data): array;

    public function isFeatureEnabled(string $key): bool;

    public function listSystemParameters(int $page, int $perPage): array;

    public function getSystemParameter(string $key): ?array;

    public function updateSystemParameter(string $key, mixed $value): array;

    public function listStorageObjects(int $page, int $perPage): array;

    public function registerStorageObject(array $data): array;

    public function getStorageObject(string $id): ?array;

    public function softDeleteStorageObject(string $id): void;

    public function listApiAccessLogs(int $page, int $perPage): array;

    public function logApiAccess(array $data): void;

    public function listOwnerActivityLogs(int $page, int $perPage): array;

    public function logOwnerActivity(array $data): void;
}
