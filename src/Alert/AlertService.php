<?php

declare(strict_types=1);

namespace Platform\Alert;

use Platform\Core\BaseService;
use Platform\Core\Exceptions\ApiException;

final class AlertService extends BaseService implements AlertServiceInterface
{
    public function createAlert(array $data): array
    {
        $required = ['alert_type', 'condition_op', 'threshold'];
        foreach ($required as $field) {
            if (!array_key_exists($field, $data)) {
                throw new ApiException(
                    422,
                    'VALIDATION_ERROR',
                    "Field {$field} is required",
                    [$field => 'Required']
                );
            }
        }

        $validTypes = ['PRICE', 'SIGNAL', 'RISK'];
        if (!in_array($data['alert_type'], $validTypes, true)) {
            throw new ApiException(
                422,
                'INVALID_ALERT_TYPE',
                'alert_type must be one of: PRICE, SIGNAL, RISK'
            );
        }

        $validOps = ['GT', 'LT', 'GTE', 'LTE', 'EQ'];
        if (!in_array($data['condition_op'], $validOps, true)) {
            throw new ApiException(
                422,
                'INVALID_CONDITION_OP',
                'condition_op must be one of: GT, LT, GTE, LTE, EQ'
            );
        }

        $id = $this->uuid();
        $now = $this->now();

        $sql = 'INSERT INTO alert.alert
            (alert_id, alert_type, instrument_id, portfolio_id,
             condition_op, threshold, description, is_active,
             triggered_count, created_at)
            VALUES
            (:id, :alert_type, :instrument_id, :portfolio_id,
             :condition_op, :threshold, :description, :is_active,
             :triggered_count, :created_at)';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id' => $id,
            ':alert_type' => $data['alert_type'],
            ':instrument_id' => $data['instrument_id'] ?? null,
            ':portfolio_id' => $data['portfolio_id'] ?? null,
            ':condition_op' => $data['condition_op'],
            ':threshold' => $data['threshold'],
            ':description' => $data['description'] ?? null,
            ':is_active' => $data['is_active'] ?? 1,
            ':triggered_count' => 0,
            ':created_at' => $now,
        ]);

        return $this->getAlert($id);
    }

    public function getAlert(string $id): ?array
    {
        $sql = 'SELECT * FROM alert.alert WHERE alert_id = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function listAlerts(array $filters, int $page, int $perPage): array
    {
        [$page, $perPage, $offset] = $this->parsePagination(
            ['page' => $page, 'per_page' => $perPage]
        );

        $where = [];
        $params = [];

        if (isset($filters['alert_type'])) {
            $where[] = 'alert_type = :alert_type';
            $params[':alert_type'] = $filters['alert_type'];
        }
        if (isset($filters['is_active'])) {
            $where[] = 'is_active = :is_active';
            $params[':is_active'] = $filters['is_active'];
        }
        if (isset($filters['instrument_id'])) {
            $where[] = 'instrument_id = :instrument_id';
            $params[':instrument_id'] = $filters['instrument_id'];
        }
        if (isset($filters['portfolio_id'])) {
            $where[] = 'portfolio_id = :portfolio_id';
            $params[':portfolio_id'] = $filters['portfolio_id'];
        }

        $whereClause = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

        $countSql = "SELECT COUNT(*) FROM alert.alert {$whereClause}";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT * FROM alert.alert {$whereClause} "
            . "ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll();

        return $this->paginate($items, $total, $page, $perPage);
    }

    public function updateAlert(string $id, array $data): array
    {
        $existing = $this->getAlert($id);
        if ($existing === null) {
            throw new ApiException(404, 'ALERT_NOT_FOUND', 'Alert was not found');
        }

        $setParts = [];
        $params = [':id' => $id];

        $updatable = ['condition_op', 'threshold', 'description', 'is_active'];
        foreach ($updatable as $field) {
            if (array_key_exists($field, $data)) {
                $setParts[] = "{$field} = :{$field}";
                $params[":{$field}"] = $data[$field];
            }
        }

        if ($setParts === []) {
            return $existing;
        }

        $setParts[] = 'updated_at = :updated_at';
        $params[':updated_at'] = $this->now();

        $setClause = implode(', ', $setParts);
        $sql = "UPDATE alert.alert SET {$setClause} WHERE alert_id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $this->getAlert($id);
    }

    public function deleteAlert(string $id): array
    {
        $existing = $this->getAlert($id);
        if ($existing === null) {
            throw new ApiException(404, 'ALERT_NOT_FOUND', 'Alert was not found');
        }

        $sql = 'UPDATE alert.alert SET is_active = 0, updated_at = :now '
            . 'WHERE alert_id = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':now' => $this->now(),
            ':id' => $id,
        ]);

        return ['deleted' => true, 'alert_id' => $id];
    }

    public function triggerAlert(string $alertId, array $context): array
    {
        $alert = $this->getAlert($alertId);
        if ($alert === null) {
            throw new ApiException(404, 'ALERT_NOT_FOUND', 'Alert was not found');
        }

        if (!$alert['is_active']) {
            return ['triggered' => false, 'reason' => 'Alert is inactive'];
        }

        $triggerValue = isset($context['value']) ? (float) $context['value'] : null;
        $message = $context['message'] ?? $this->buildMessage($alert, $triggerValue);

        $notifId = $this->uuid();
        $now = $this->now();

        $sql = 'INSERT INTO alert.alert_notification
            (notification_id, alert_id, trigger_value, message, status, created_at)
            VALUES
            (:id, :alert_id, :trigger_value, :message, :status, :created_at)';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id' => $notifId,
            ':alert_id' => $alertId,
            ':trigger_value' => $triggerValue,
            ':message' => $message,
            ':status' => 'PENDING',
            ':created_at' => $now,
        ]);

        $updateSql = 'UPDATE alert.alert
            SET triggered_count = triggered_count + 1, last_triggered = :now
            WHERE alert_id = :id';
        $updateStmt = $this->db->prepare($updateSql);
        $updateStmt->execute([':now' => $now, ':id' => $alertId]);

        return [
            'notification_id' => $notifId,
            'alert_id' => $alertId,
            'trigger_value' => $triggerValue,
            'message' => $message,
            'status' => 'PENDING',
        ];
    }

    public function listNotifications(array $filters, int $page, int $perPage): array
    {
        [$page, $perPage, $offset] = $this->parsePagination(
            ['page' => $page, 'per_page' => $perPage]
        );

        $where = [];
        $params = [];

        if (isset($filters['alert_id'])) {
            $where[] = 'alert_id = :alert_id';
            $params[':alert_id'] = $filters['alert_id'];
        }
        if (isset($filters['status'])) {
            $where[] = 'status = :status';
            $params[':status'] = $filters['status'];
        }

        $whereClause = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

        $countSql = "SELECT COUNT(*) FROM alert.alert_notification {$whereClause}";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT * FROM alert.alert_notification {$whereClause} "
            . "ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll();

        return $this->paginate($items, $total, $page, $perPage);
    }

    public function acknowledgeNotification(string $notificationId): array
    {
        $sql = 'UPDATE alert.alert_notification
            SET status = :status, acknowledged_at = :ack_at
            WHERE notification_id = :id AND status = :pending';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':status' => 'ACKNOWLEDGED',
            ':ack_at' => $this->now(),
            ':id' => $notificationId,
            ':pending' => 'PENDING',
        ]);

        if ($stmt->rowCount() === 0) {
            throw new ApiException(
                404,
                'NOTIFICATION_NOT_FOUND',
                'Notification not found or already acknowledged'
            );
        }

        $selectSql = 'SELECT * FROM alert.alert_notification '
            . 'WHERE notification_id = :id';
        $selectStmt = $this->db->prepare($selectSql);
        $selectStmt->execute([':id' => $notificationId]);
        $row = $selectStmt->fetch();
        return $row === false ? null : $row;
    }

    public function checkPriceAlert(string $instrumentId, float $currentPrice): array
    {
        $sql = 'SELECT * FROM alert.alert '
            . 'WHERE alert_type = :type AND instrument_id = :inst '
            . 'AND is_active = 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':type' => 'PRICE', ':inst' => $instrumentId]);
        $alerts = $stmt->fetchAll();

        $triggered = [];
        foreach ($alerts as $alert) {
            $condition = $this->evaluateCondition(
                $currentPrice,
                $alert['condition_op'],
                (float) $alert['threshold']
            );
            if ($condition) {
                $result = $this->triggerAlert($alert['alert_id'], [
                    'value' => $currentPrice,
                ]);
                $triggered[] = $result;
            }
        }

        return [
            'instrument_id' => $instrumentId,
            'current_price' => $currentPrice,
            'alerts_checked' => count($alerts),
            'alerts_triggered' => count($triggered),
            'notifications' => $triggered,
        ];
    }

    private function evaluateCondition(float $value, string $op, float $threshold): bool
    {
        return match ($op) {
            'GT' => $value > $threshold,
            'LT' => $value < $threshold,
            'GTE' => $value >= $threshold,
            'LTE' => $value <= $threshold,
            'EQ' => abs($value - $threshold) < 0.0001,
            default => false,
        };
    }

    private function buildMessage(array $alert, ?float $triggerValue): string
    {
        $type = $alert['alert_type'];
        $op = $alert['condition_op'];
        $threshold = $alert['threshold'];
        $desc = $alert['description'] ?? '';

        $msg = "{$type} alert: condition {$op} {$threshold}";
        if ($triggerValue !== null) {
            $msg .= " (current: {$triggerValue})";
        }
        if ($desc) {
            $msg .= " — {$desc}";
        }
        return $msg;
    }
}
