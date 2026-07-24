<?php

declare(strict_types=1);

namespace Platform\Trading;

use Platform\Core\BaseService;
use Platform\Core\Exceptions\ApiException;

final class TradingService extends BaseService implements TradingServiceInterface
{
    // ─── Brokers ─────────────────────────────────────────────────────────

    public function listBrokers(array $filters, int $page, int $perPage): array
    {
        [$page, $perPage, $offset] = $this->parsePagination(
            ['page' => $page, 'per_page' => $perPage]
        );
        $where = [];
        $params = [];
        if (isset($filters['status'])) {
            $where[] = 'status = :status';
            $params[':status'] = $filters['status'];
        }
        $clause = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
        $total = $this->countRows('trading.broker', $clause, $params);
        $stmt = $this->db->prepare(
            "SELECT * FROM trading.broker {$clause} "
            . "ORDER BY name ASC LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return $this->paginate($stmt->fetchAll(), $total, $page, $perPage);
    }

    public function createBroker(array $data): array
    {
        $this->validateRequired($data, ['name', 'country']);
        $id = $this->uuid();
        $now = $this->now();
        $stmt = $this->db->prepare(
            'INSERT INTO trading.broker
             (broker_id, name, legal_name, country, regulatory_id,
              api_type, api_endpoint, status, created_at)
             VALUES
             (:id, :name, :legal_name, :country, :regulatory_id,
              :api_type, :api_endpoint, :status, :now)'
        );
        $stmt->execute([
            ':id' => $id,
            ':name' => $data['name'],
            ':legal_name' => $data['legal_name'] ?? null,
            ':country' => strtoupper($data['country']),
            ':regulatory_id' => $data['regulatory_id'] ?? null,
            ':api_type' => $data['api_type'] ?? 'NONE',
            ':api_endpoint' => $data['api_endpoint'] ?? null,
            ':status' => $data['status'] ?? 'ACTIVE',
            ':now' => $now,
        ]);
        return $this->getBroker($id);
    }

    public function getBroker(string $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM trading.broker WHERE broker_id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function updateBroker(string $id, array $data): array
    {
        $existing = $this->getBroker($id);
        if ($existing === null) {
            throw new ApiException(404, 'BROKER_NOT_FOUND', 'Broker was not found');
        }
        $fields = [];
        $params = [':id' => $id];
        foreach (
            [
            'name', 'legal_name', 'country', 'regulatory_id',
            'api_type', 'api_endpoint', 'status',
            ] as $field
        ) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = :{$field}";
                $params[":{$field}"] = $data[$field];
            }
        }
        if ($fields === []) {
            return $existing;
        }
        $stmt = $this->db->prepare(
            'UPDATE trading.broker SET ' . implode(', ', $fields)
            . ' WHERE broker_id = :id'
        );
        $stmt->execute($params);
        return $this->getBroker($id);
    }

    // ─── Decisions ───────────────────────────────────────────────────────

    public function listDecisions(array $filters, int $page, int $perPage): array
    {
        [$page, $perPage, $offset] = $this->parsePagination(
            ['page' => $page, 'per_page' => $perPage]
        );
        $where = [];
        $params = [];
        if (isset($filters['portfolio_id'])) {
            $where[] = 'portfolio_id = :portfolio_id';
            $params[':portfolio_id'] = $filters['portfolio_id'];
        }
        if (isset($filters['status'])) {
            $where[] = 'status = :status';
            $params[':status'] = $filters['status'];
        }
        if (isset($filters['instrument_id'])) {
            $where[] = 'instrument_id = :instrument_id';
            $params[':instrument_id'] = $filters['instrument_id'];
        }
        $clause = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
        $total = $this->countRows('trading.decision', $clause, $params);
        $stmt = $this->db->prepare(
            "SELECT * FROM trading.decision {$clause} "
            . "ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return $this->paginate($stmt->fetchAll(), $total, $page, $perPage);
    }

    public function createDecision(array $data): array
    {
        $this->validateRequired($data, ['portfolio_id', 'instrument_id', 'action']);
        $this->assertDecisionAction((string) $data['action']);
        $id = $this->uuid();
        $now = $this->now();
        $stmt = $this->db->prepare(
            'INSERT INTO trading.decision
             (decision_id, portfolio_id, instrument_id, recommendation_id,
              risk_assessment_id, action, intended_quantity, intended_price,
              reason, confidence, policy_result, policy_checks, human_override,
              override_reason, created_at, status)
             VALUES
             (:id, :portfolio_id, :instrument_id, :recommendation_id,
              :risk_assessment_id, :action, :intended_quantity, :intended_price,
              :reason, :confidence, :policy_result, :policy_checks, 0,
              NULL, :now, :status)'
        );
        $stmt->execute([
            ':id' => $id,
            ':portfolio_id' => $data['portfolio_id'],
            ':instrument_id' => $data['instrument_id'],
            ':recommendation_id' => $data['recommendation_id'] ?? null,
            ':risk_assessment_id' => $data['risk_assessment_id'] ?? null,
            ':action' => $data['action'],
            ':intended_quantity' => $data['intended_quantity'] ?? null,
            ':intended_price' => $data['intended_price'] ?? null,
            ':reason' => $data['reason'] ?? null,
            ':confidence' => $data['confidence'] ?? null,
            ':policy_result' => $data['policy_result'] ?? 'APPROVED',
            ':policy_checks' => isset($data['policy_checks'])
                ? json_encode($data['policy_checks'])
                : null,
            ':now' => $now,
            ':status' => $data['status'] ?? 'PENDING',
        ]);
        return $this->getDecision($id);
    }

    public function getDecision(string $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM trading.decision WHERE decision_id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function approveDecision(string $id): array
    {
        $existing = $this->getDecision($id);
        if ($existing === null) {
            throw new ApiException(404, 'DECISION_NOT_FOUND', 'Decision was not found');
        }
        $stmt = $this->db->prepare(
            'UPDATE trading.decision SET status = :status WHERE decision_id = :id'
        );
        $stmt->execute([':status' => 'APPROVED', ':id' => $id]);
        return $this->getDecision($id);
    }

    public function rejectDecision(string $id, string $reason): array
    {
        $existing = $this->getDecision($id);
        if ($existing === null) {
            throw new ApiException(404, 'DECISION_NOT_FOUND', 'Decision was not found');
        }
        $stmt = $this->db->prepare(
            'UPDATE trading.decision
             SET status = :status, policy_result = :policy_result,
                 override_reason = :reason
             WHERE decision_id = :id'
        );
        $stmt->execute([
            ':status' => 'REJECTED',
            ':policy_result' => 'REJECTED',
            ':reason' => $reason,
            ':id' => $id,
        ]);
        return $this->getDecision($id);
    }

    public function overrideDecision(string $id, string $reason): array
    {
        $existing = $this->getDecision($id);
        if ($existing === null) {
            throw new ApiException(404, 'DECISION_NOT_FOUND', 'Decision was not found');
        }
        $stmt = $this->db->prepare(
            'UPDATE trading.decision
             SET status = :status, policy_result = :policy_result,
                 human_override = 1, override_reason = :reason
             WHERE decision_id = :id'
        );
        $stmt->execute([
            ':status' => 'APPROVED',
            ':policy_result' => 'MANUAL_OVERRIDE',
            ':reason' => $reason,
            ':id' => $id,
        ]);
        return $this->getDecision($id);
    }

    // ─── Order Intents ───────────────────────────────────────────────────

    public function listOrderIntents(array $filters, int $page, int $perPage): array
    {
        [$page, $perPage, $offset] = $this->parsePagination(
            ['page' => $page, 'per_page' => $perPage]
        );
        $where = [];
        $params = [];
        if (isset($filters['portfolio_id'])) {
            $where[] = 'portfolio_id = :portfolio_id';
            $params[':portfolio_id'] = $filters['portfolio_id'];
        }
        if (isset($filters['status'])) {
            $where[] = 'status = :status';
            $params[':status'] = $filters['status'];
        }
        if (isset($filters['decision_id'])) {
            $where[] = 'decision_id = :decision_id';
            $params[':decision_id'] = $filters['decision_id'];
        }
        $clause = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
        $total = $this->countRows('trading.order_intent', $clause, $params);
        $stmt = $this->db->prepare(
            "SELECT * FROM trading.order_intent {$clause} "
            . "ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return $this->paginate($stmt->fetchAll(), $total, $page, $perPage);
    }

    public function createOrderIntent(array $data): array
    {
        $this->validateRequired($data, ['decision_id', 'portfolio_id', 'instrument_id', 'side', 'target_quantity']);
        $this->assertSide((string) $data['side']);
        $id = $this->uuid();
        $now = $this->now();
        $stmt = $this->db->prepare(
            'INSERT INTO trading.order_intent
             (order_intent_id, decision_id, portfolio_id, instrument_id,
              side, target_quantity, target_price, strategy, reason,
              status, approved_at, created_at, expires_at)
             VALUES
             (:id, :decision_id, :portfolio_id, :instrument_id,
              :side, :target_quantity, :target_price, :strategy, :reason,
              :status, NULL, :now, :expires_at)'
        );
        $stmt->execute([
            ':id' => $id,
            ':decision_id' => $data['decision_id'],
            ':portfolio_id' => $data['portfolio_id'],
            ':instrument_id' => $data['instrument_id'],
            ':side' => $data['side'],
            ':target_quantity' => $data['target_quantity'],
            ':target_price' => $data['target_price'] ?? null,
            ':strategy' => $data['strategy'] ?? null,
            ':reason' => $data['reason'] ?? null,
            ':status' => $data['status'] ?? 'DRAFT',
            ':now' => $now,
            ':expires_at' => $data['expires_at'] ?? null,
        ]);
        return $this->getOrderIntent($id);
    }

    public function getOrderIntent(string $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM trading.order_intent WHERE order_intent_id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function approveOrderIntent(string $id): array
    {
        $existing = $this->getOrderIntent($id);
        if ($existing === null) {
            throw new ApiException(404, 'ORDER_INTENT_NOT_FOUND', 'Order intent was not found');
        }
        $now = $this->now();
        $stmt = $this->db->prepare(
            'UPDATE trading.order_intent
             SET status = :status, approved_at = :now
             WHERE order_intent_id = :id'
        );
        $stmt->execute([':status' => 'APPROVED', ':now' => $now, ':id' => $id]);
        return $this->getOrderIntent($id);
    }

    public function rejectOrderIntent(string $id, string $reason): array
    {
        $existing = $this->getOrderIntent($id);
        if ($existing === null) {
            throw new ApiException(404, 'ORDER_INTENT_NOT_FOUND', 'Order intent was not found');
        }
        $stmt = $this->db->prepare(
            'UPDATE trading.order_intent
             SET status = :status, reason = :reason
             WHERE order_intent_id = :id'
        );
        $stmt->execute([':status' => 'REJECTED', ':reason' => $reason, ':id' => $id]);
        return $this->getOrderIntent($id);
    }

    // ─── Orders ──────────────────────────────────────────────────────────

    public function listOrders(array $filters, int $page, int $perPage): array
    {
        [$page, $perPage, $offset] = $this->parsePagination(
            ['page' => $page, 'per_page' => $perPage]
        );
        $where = [];
        $params = [];
        if (isset($filters['portfolio_id'])) {
            $where[] = 'portfolio_id = :portfolio_id';
            $params[':portfolio_id'] = $filters['portfolio_id'];
        }
        if (isset($filters['status'])) {
            $where[] = 'status = :status';
            $params[':status'] = $filters['status'];
        }
        if (isset($filters['instrument_id'])) {
            $where[] = 'instrument_id = :instrument_id';
            $params[':instrument_id'] = $filters['instrument_id'];
        }
        $clause = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
        $total = $this->countRows('trading.order', $clause, $params);
        $stmt = $this->db->prepare(
            "SELECT * FROM trading.order {$clause} "
            . "ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return $this->paginate($stmt->fetchAll(), $total, $page, $perPage);
    }

    public function submitOrder(array $data): array
    {
        $this->validateRequired($data, ['order_intent_id', 'account_id', 'quantity']);
        $intent = $this->getOrderIntent($data['order_intent_id']);
        if ($intent === null) {
            throw new ApiException(404, 'ORDER_INTENT_NOT_FOUND', 'Order intent was not found');
        }
        $id = $this->uuid();
        $now = $this->now();
        $orderRef = 'ORD-' . date('Ymd') . '-' . str_pad(
            (string) $this->countRows('trading.order', '', []),
            5,
            '0',
            STR_PAD_LEFT
        ) . '1';
        $stmt = $this->db->prepare(
            'INSERT INTO trading.order
             (order_id, order_ref, order_intent_id, portfolio_id, account_id,
              instrument_id, side, order_type, quantity, filled_quantity,
              remaining_quantity, limit_price, stop_price, time_in_force,
              expire_at, broker_order_id, status, rejection_reason,
              submitted_at, filled_at, created_at, updated_at)
             VALUES
             (:id, :order_ref, :order_intent_id, :portfolio_id, :account_id,
              :instrument_id, :side, :order_type, :quantity, 0,
              :remaining, :limit_price, :stop_price, :time_in_force,
              :expire_at, NULL, :status, NULL,
              :now, NULL, :now, :now)'
        );
        $stmt->execute([
            ':id' => $id,
            ':order_ref' => $orderRef,
            ':order_intent_id' => $data['order_intent_id'],
            ':portfolio_id' => $intent['portfolio_id'],
            ':account_id' => $data['account_id'],
            ':instrument_id' => $intent['instrument_id'],
            ':side' => $intent['side'],
            ':order_type' => $data['order_type'] ?? 'MARKET',
            ':quantity' => $data['quantity'],
            ':remaining' => $data['quantity'],
            ':limit_price' => $data['limit_price'] ?? null,
            ':stop_price' => $data['stop_price'] ?? null,
            ':time_in_force' => $data['time_in_force'] ?? 'DAY',
            ':expire_at' => $data['expire_at'] ?? null,
            ':status' => 'SUBMITTED',
            ':now' => $now,
        ]);
        $stmt2 = $this->db->prepare(
            'UPDATE trading.order_intent SET status = :status WHERE order_intent_id = :id'
        );
        $stmt2->execute([':status' => 'CONVERTED', ':id' => $data['order_intent_id']]);
        return $this->getOrder($id);
    }

    public function getOrder(string $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM trading.order WHERE order_id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        $row['executions'] = $this->getOrderExecutions($id);
        return $row;
    }

    public function cancelOrder(string $id, string $reason): array
    {
        $existing = $this->getOrder($id);
        if ($existing === null) {
            throw new ApiException(404, 'ORDER_NOT_FOUND', 'Order was not found');
        }
        $stmt = $this->db->prepare(
            'UPDATE trading.order
             SET status = :status, rejection_reason = :reason
             WHERE order_id = :id'
        );
        $stmt->execute([':status' => 'CANCELLED', ':reason' => $reason, ':id' => $id]);
        return $this->getOrder($id);
    }

    public function getOrderExecutions(string $orderId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM trading.execution WHERE order_id = :id ORDER BY executed_at ASC'
        );
        $stmt->execute([':id' => $orderId]);
        return $stmt->fetchAll();
    }

    // ─── Executions ──────────────────────────────────────────────────────

    public function listExecutions(array $filters, int $page, int $perPage): array
    {
        [$page, $perPage, $offset] = $this->parsePagination(
            ['page' => $page, 'per_page' => $perPage]
        );
        $where = [];
        $params = [];
        if (isset($filters['order_id'])) {
            $where[] = 'order_id = :order_id';
            $params[':order_id'] = $filters['order_id'];
        }
        if (isset($filters['status'])) {
            $where[] = 'status = :status';
            $params[':status'] = $filters['status'];
        }
        if (isset($filters['instrument_id'])) {
            $where[] = 'instrument_id = :instrument_id';
            $params[':instrument_id'] = $filters['instrument_id'];
        }
        $clause = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
        $total = $this->countRows('trading.execution', $clause, $params);
        $stmt = $this->db->prepare(
            "SELECT * FROM trading.execution {$clause} "
            . "ORDER BY executed_at DESC LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return $this->paginate($stmt->fetchAll(), $total, $page, $perPage);
    }

    public function getExecution(string $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM trading.execution WHERE execution_id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function recordExecution(array $data): array
    {
        $this->validateRequired($data, ['order_id', 'instrument_id', 'fill_quantity', 'fill_price', 'currency']);
        $id = $this->uuid();
        $now = $this->now();
        $fillValue = (float) $data['fill_quantity'] * (float) $data['fill_price'];
        $commission = (float) ($data['commission'] ?? 0);
        $fees = (float) ($data['fees'] ?? 0);
        $taxes = (float) ($data['taxes'] ?? 0);
        $netValue = $fillValue + $commission + $fees + $taxes;
        $execRef = 'EXE-' . date('Ymd') . '-' . str_pad(
            (string) $this->countRows('trading.execution', '', []),
            5,
            '0',
            STR_PAD_LEFT
        ) . '1';
        $stmt = $this->db->prepare(
            'INSERT INTO trading.execution
             (execution_id, execution_ref, order_id, instrument_id,
              fill_quantity, fill_price, fill_value, commission, fees, taxes,
              net_value, currency, broker_execution_id, executed_at,
              created_at, status)
             VALUES
             (:id, :exec_ref, :order_id, :instrument_id,
              :fill_qty, :fill_price, :fill_value, :commission, :fees, :taxes,
              :net_value, :currency, :broker_exec_id, :executed_at,
              :now, :status)'
        );
        $stmt->execute([
            ':id' => $id,
            ':exec_ref' => $execRef,
            ':order_id' => $data['order_id'],
            ':instrument_id' => $data['instrument_id'],
            ':fill_qty' => $data['fill_quantity'],
            ':fill_price' => $data['fill_price'],
            ':fill_value' => $fillValue,
            ':commission' => $commission,
            ':fees' => $fees,
            ':taxes' => $taxes,
            ':net_value' => $netValue,
            ':currency' => strtoupper($data['currency']),
            ':broker_exec_id' => $data['broker_execution_id'] ?? null,
            ':executed_at' => $data['executed_at'] ?? $now,
            ':now' => $now,
            ':status' => $data['status'] ?? 'PENDING_SETTLEMENT',
        ]);
        $this->updateOrderFill($data['order_id'], (float) $data['fill_quantity']);
        return $this->getExecution($id);
    }

    // ─── Private Helpers ─────────────────────────────────────────────────

    private function updateOrderFill(string $orderId, float $fillQty): void
    {
        $stmt = $this->db->prepare(
            'SELECT COALESCE(SUM(fill_quantity), 0) as total_filled
             FROM trading.execution WHERE order_id = :id'
        );
        $stmt->execute([':id' => $orderId]);
        $totalFilled = (float) $stmt->fetchColumn();
        $order = $this->getOrder($orderId);
        if ($order === null) {
            return;
        }
        $remaining = (float) $order['quantity'] - $totalFilled;
        $status = $remaining <= 0 ? 'FILLED' : 'PARTIALLY_FILLED';
        $now = $this->now();
        $upd = $this->db->prepare(
            'UPDATE trading.order
             SET filled_quantity = :filled, remaining_quantity = :remaining,
                 status = :status, filled_at = :filled_at, updated_at = :now
             WHERE order_id = :id'
        );
        $upd->execute([
            ':filled' => $totalFilled,
            ':remaining' => max(0, $remaining),
            ':status' => $status,
            ':filled_at' => $status === 'FILLED' ? $now : null,
            ':now' => $now,
            ':id' => $orderId,
        ]);
    }

    private function assertDecisionAction(string $action): void
    {
        $valid = ['BUY', 'SELL', 'HOLD', 'ABSTAIN', 'REBALANCE'];
        if (!in_array($action, $valid, true)) {
            throw new ApiException(
                422,
                'VALIDATION_ERROR',
                'Invalid action. Must be one of: ' . implode(', ', $valid)
            );
        }
    }

    private function assertSide(string $side): void
    {
        $valid = ['BUY', 'SELL'];
        if (!in_array($side, $valid, true)) {
            throw new ApiException(
                422,
                'VALIDATION_ERROR',
                'Invalid side. Must be one of: ' . implode(', ', $valid)
            );
        }
    }

    private function validateRequired(array $data, array $fields): void
    {
        $errors = [];
        foreach ($fields as $field) {
            if (!array_key_exists($field, $data) || $data[$field] === '') {
                $errors[$field][] = 'This field is required';
            }
        }
        if ($errors !== []) {
            throw new ApiException(422, 'VALIDATION_ERROR', 'Required fields are missing', $errors);
        }
    }

    private function countRows(string $table, string $clause = '', array $params = []): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM {$table} {$clause}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }
}
