<?php

declare(strict_types=1);

namespace Platform\Core\Http;

final class Request
{
    use RequestParamsTrait;

    private string $method;
    private string $path;
    private array $headers = [];
    private array $query = [];
    private array $body = [];
    private ?string $tenantId = null;
    private ?string $userId = null;
    private ?array $userPermissions = null;

    public function __construct()
    {
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $this->path = $uri ?? '/';

        $this->query = $_GET;
        $this->headers = $this->parseHeaders();

        $rawBody = file_get_contents('php://input');
        if ($rawBody) {
            $decoded = json_decode($rawBody, true);
            $this->body = is_array($decoded) ? $decoded : [];
        }
    }

    private function parseHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', strtolower(substr($key, 5)));
                $headers[$name] = $value;
            }
        }
        return $headers;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getHeader(string $name): ?string
    {
        $name = strtolower($name);
        return $this->headers[$name] ?? null;
    }

    public function getQuery(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function getAllQuery(): array
    {
        return $this->query;
    }

    public function getBody(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    public function getAllBody(): array
    {
        return $this->body;
    }

    public function getTenantId(): ?string
    {
        return $this->tenantId ?? $this->getHeader('x-tenant-id');
    }

    public function setTenantId(?string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function setUserId(?string $userId): void
    {
        $this->userId = $userId;
    }

    public function getUserPermissions(): ?array
    {
        return $this->userPermissions;
    }

    public function setUserPermissions(?array $permissions): void
    {
        $this->userPermissions = $permissions;
    }

    public function hasPermission(string $permission): bool
    {
        return $this->userPermissions !== null && in_array($permission, $this->userPermissions, true);
    }
}
