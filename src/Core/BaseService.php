<?php

declare(strict_types=1);

namespace Platform\Core;

abstract class BaseService
{
    protected \PDO $db;

    public function __construct(?\PDO $db = null)
    {
        $this->db = $db ?? \Platform\Core\Database\MySqlConnection::getInstance();
    }

    protected function uuid(): string
    {
        return \Ramsey\Uuid\Uuid::uuid7()->toString();
    }

    protected function now(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
    }

    protected function paginate(array $items, int $total, int $page, int $perPage): array
    {
        return [
            'data' => $items,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => (int) ceil($total / max(1, $perPage)),
            ],
        ];
    }

    protected function parsePagination(array $query): array
    {
        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = min(200, max(1, (int) ($query['per_page'] ?? 50)));
        $offset = ($page - 1) * $perPage;
        return [$page, $perPage, $offset];
    }
}
