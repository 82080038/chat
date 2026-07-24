<?php

declare(strict_types=1);

namespace Platform\Alert;

interface AlertServiceInterface
{
    public function createAlert(array $data): array;
    public function getAlert(string $id): ?array;
    public function listAlerts(array $filters, int $page, int $perPage): array;
    public function updateAlert(string $id, array $data): array;
    public function deleteAlert(string $id): array;
    public function triggerAlert(string $alertId, array $context): array;
    public function listNotifications(array $filters, int $page, int $perPage): array;
    public function acknowledgeNotification(string $notificationId): array;
    public function checkPriceAlert(string $instrumentId, float $currentPrice): array;
}
