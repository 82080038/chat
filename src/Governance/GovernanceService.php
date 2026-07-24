<?php

declare(strict_types=1);

namespace Platform\Governance;

use Platform\Core\BaseService;
use Platform\Core\Exceptions\ApiException;

final class GovernanceService extends BaseService implements GovernanceServiceInterface
{
    public function auditLog(array $data): void
    {
        $sql = 'INSERT INTO governance.audit_log
            (audit_log_id, actor_type, action, entity_type, entity_id,
             old_values, new_values, ip_address, user_agent, correlation_id, created_at)
            VALUES
            (:id, :actor_type, :action, :entity_type, :entity_id,
             :old_values, :new_values, :ip_address, :user_agent, :correlation_id, :created_at)';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id' => $this->uuid(),
            ':actor_type' => $data['actor_type'],
            ':action' => $data['action'],
            ':entity_type' => $data['entity_type'] ?? null,
            ':entity_id' => $data['entity_id'] ?? null,
            ':old_values' => isset($data['old_values']) ? json_encode($data['old_values']) : null,
            ':new_values' => isset($data['new_values']) ? json_encode($data['new_values']) : null,
            ':ip_address' => $data['ip_address'] ?? null,
            ':user_agent' => $data['user_agent'] ?? null,
            ':correlation_id' => $data['correlation_id'] ?? null,
            ':created_at' => $this->now(),
        ]);
    }

    public function getAuditLog(string $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM governance.audit_log WHERE audit_log_id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function requestApproval(
        string $entityType,
        string $entityId,
        string $approvalType
    ): array {
        $id = $this->uuid();
        $sql = 'INSERT INTO governance.approval
            (approval_id, entity_type, entity_id, approval_type, requested_at, status, created_at)
            VALUES
            (:id, :entity_type, :entity_id, :approval_type, :requested_at, :status, :created_at)';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id' => $id,
            ':entity_type' => $entityType,
            ':entity_id' => $entityId,
            ':approval_type' => $approvalType,
            ':requested_at' => $this->now(),
            ':status' => 'PENDING',
            ':created_at' => $this->now(),
        ]);

        return $this->getApproval($id);
    }

    public function approve(string $approvalId): array
    {
        $sql = 'UPDATE governance.approval
            SET status = :status, approved_at = :approved_at
            WHERE approval_id = :id AND status = :pending_status';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':status' => 'APPROVED',
            ':approved_at' => $this->now(),
            ':id' => $approvalId,
            ':pending_status' => 'PENDING',
        ]);

        if ($stmt->rowCount() === 0) {
            return ['error' => 'Approval not found or not pending'];
        }

        return $this->getApproval($approvalId);
    }

    public function reject(string $approvalId, string $reason): array
    {
        $sql = 'UPDATE governance.approval
            SET status = :status, rejected_at = :rejected_at, rejection_reason = :reason
            WHERE approval_id = :id AND status = :pending_status';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':status' => 'REJECTED',
            ':rejected_at' => $this->now(),
            ':reason' => $reason,
            ':id' => $approvalId,
            ':pending_status' => 'PENDING',
        ]);

        if ($stmt->rowCount() === 0) {
            return ['error' => 'Approval not found or not pending'];
        }

        return $this->getApproval($approvalId);
    }

    public function getApproval(string $id): ?array
    {
        $sql = 'SELECT * FROM governance.approval WHERE approval_id = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function listApprovals(array $filters, int $page, int $perPage): array
    {
        [$page, $perPage, $offset] = $this->parsePagination(['page' => $page, 'per_page' => $perPage]);

        $where = [];
        $params = [];

        if (isset($filters['status'])) {
            $where[] = 'status = :status';
            $params[':status'] = $filters['status'];
        }
        if (isset($filters['approval_type'])) {
            $where[] = 'approval_type = :approval_type';
            $params[':approval_type'] = $filters['approval_type'];
        }
        if (isset($filters['entity_type'])) {
            $where[] = 'entity_type = :entity_type';
            $params[':entity_type'] = $filters['entity_type'];
        }

        $whereClause = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

        $countSql = "SELECT COUNT(*) FROM governance.approval {$whereClause}";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT * FROM governance.approval {$whereClause} "
            . "ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll();

        return $this->paginate($items, $total, $page, $perPage);
    }

    public function listAuditLogs(array $filters, int $page, int $perPage): array
    {
        [$page, $perPage, $offset] = $this->parsePagination(['page' => $page, 'per_page' => $perPage]);

        $where = [];
        $params = [];

        if (isset($filters['entity_type'])) {
            $where[] = 'entity_type = :entity_type';
            $params[':entity_type'] = $filters['entity_type'];
        }
        if (isset($filters['entity_id'])) {
            $where[] = 'entity_id = :entity_id';
            $params[':entity_id'] = $filters['entity_id'];
        }
        if (isset($filters['actor_type'])) {
            $where[] = 'actor_type = :actor_type';
            $params[':actor_type'] = $filters['actor_type'];
        }

        $whereClause = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

        $countSql = "SELECT COUNT(*) FROM governance.audit_log {$whereClause}";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT * FROM governance.audit_log {$whereClause} "
            . "ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll();

        return $this->paginate($items, $total, $page, $perPage);
    }

    public function createPolicy(array $data): array
    {
        $id = $this->uuid();
        $sql = 'INSERT INTO governance.policy
            (policy_id, policy_type, name, description, rules, priority,
             effective_from, status, version, created_at)
            VALUES
            (:id, :policy_type, :name, :description, :rules, :priority,
             :effective_from, :status, :version, :created_at)';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id' => $id,
            ':policy_type' => $data['policy_type'],
            ':name' => $data['name'],
            ':description' => $data['description'] ?? null,
            ':rules' => isset($data['rules']) ? json_encode($data['rules']) : null,
            ':priority' => $data['priority'] ?? 0,
            ':effective_from' => $this->now(),
            ':status' => 'DRAFT',
            ':version' => 1,
            ':created_at' => $this->now(),
        ]);

        return $this->getPolicy($id);
    }

    public function getPolicy(string $id): ?array
    {
        $sql = 'SELECT * FROM governance.policy WHERE policy_id = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();
        if ($result) {
            $result['rules'] = $result['rules'] ? json_decode($result['rules'], true) : null;
        }
        return $result ?: null;
    }

    public function listPolicies(array $filters, int $page, int $perPage): array
    {
        [$page, $perPage, $offset] = $this->parsePagination(['page' => $page, 'per_page' => $perPage]);

        $where = [];
        $params = [];

        if (isset($filters['policy_type'])) {
            $where[] = 'policy_type = :policy_type';
            $params[':policy_type'] = $filters['policy_type'];
        }
        if (isset($filters['status'])) {
            $where[] = 'status = :status';
            $params[':status'] = $filters['status'];
        }

        $whereClause = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

        $countSql = "SELECT COUNT(*) FROM governance.policy {$whereClause}";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT * FROM governance.policy {$whereClause} "
            . "ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll();

        return $this->paginate($items, $total, $page, $perPage);
    }

    public function evaluatePolicy(string $policyId, string $entityType, string $entityId): array
    {
        $policy = $this->getPolicy($policyId);
        if (!$policy) {
            return ['error' => 'Policy not found'];
        }

        $rules = $policy['rules'] ?? [];
        $result = 'PASS';
        $ruleResults = [];

        foreach ($rules as $ruleName => $ruleConfig) {
            $ruleResults[$ruleName] = 'PASS';
        }

        $id = $this->uuid();
        $sql = 'INSERT INTO governance.policy_evaluation
            (evaluation_id, policy_id, entity_type, entity_id, evaluation_result,
             rule_results, evaluated_at)
            VALUES
            (:id, :policy_id, :entity_type, :entity_id, :result, :rule_results, :evaluated_at)';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id' => $id,
            ':policy_id' => $policyId,
            ':entity_type' => $entityType,
            ':entity_id' => $entityId,
            ':result' => $result,
            ':rule_results' => json_encode($ruleResults),
            ':evaluated_at' => $this->now(),
        ]);

        return [
            'evaluation_id' => $id,
            'policy_id' => $policyId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'result' => $result,
            'rule_results' => $ruleResults,
        ];
    }

    public function startWorkflow(
        string $type,
        string $entityType,
        string $entityId
    ): array {
        $id = $this->uuid();
        $sql = 'INSERT INTO governance.workflow
            (workflow_id, workflow_type, entity_type, entity_id,
             current_step, total_steps, status, initiated_at)
            VALUES
            (:id, :type, :entity_type, :entity_id,
             :current_step, :total_steps, :status, :initiated_at)';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id' => $id,
            ':type' => $type,
            ':entity_type' => $entityType,
            ':entity_id' => $entityId,
            ':current_step' => 0,
            ':total_steps' => 1,
            ':status' => 'PENDING',
            ':initiated_at' => $this->now(),
        ]);

        return $this->getWorkflow($id);
    }

    public function getWorkflow(string $id): ?array
    {
        $sql = 'SELECT * FROM governance.workflow WHERE workflow_id = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function completeWorkflowStep(string $workflowId, string $stepId, array $result): array
    {
        $sql = 'UPDATE governance.workflow_step
            SET status = :status, completed_at = :completed_at, result = :result
            WHERE step_id = :step_id AND workflow_id = :workflow_id';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':status' => 'COMPLETED',
            ':completed_at' => $this->now(),
            ':result' => json_encode($result),
            ':step_id' => $stepId,
            ':workflow_id' => $workflowId,
        ]);

        $updateSql = 'UPDATE governance.workflow
            SET current_step = current_step + 1,
                status = CASE WHEN current_step + 1 >= total_steps THEN :completed ELSE :in_progress END,
                completed_at = CASE WHEN current_step + 1 >= total_steps THEN :now ELSE NULL END
            WHERE workflow_id = :workflow_id';

        $updateStmt = $this->db->prepare($updateSql);
        $updateStmt->execute([
            ':completed' => 'COMPLETED',
            ':in_progress' => 'IN_PROGRESS',
            ':now' => $this->now(),
            ':workflow_id' => $workflowId,
        ]);

        return $this->getWorkflow($workflowId);
    }

    public function updatePolicy(string $id, array $data): array
    {
        $existing = $this->getPolicy($id);
        if ($existing === null) {
            throw new ApiException(404, 'POLICY_NOT_FOUND', 'Policy was not found');
        }
        $newId = $this->uuid();
        $newVersion = (int) $existing['version'] + 1;
        $now = $this->now();
        $stmt = $this->db->prepare(
            'INSERT INTO governance.policy
             (policy_id, policy_type, name, description, rules, priority,
              effective_from, effective_until, status, version, created_at)
             VALUES
             (:id, :policy_type, :name, :description, :rules, :priority,
              :effective_from, :effective_until, :status, :version, :now)'
        );
        $stmt->execute([
            ':id' => $newId,
            ':policy_type' => $data['policy_type'] ?? $existing['policy_type'],
            ':name' => $data['name'] ?? $existing['name'],
            ':description' => $data['description'] ?? $existing['description'],
            ':rules' => isset($data['rules'])
                ? json_encode($data['rules'])
                : ($existing['rules'] ? json_encode($existing['rules']) : null),
            ':priority' => $data['priority'] ?? $existing['priority'],
            ':effective_from' => $now,
            ':effective_until' => $data['effective_until'] ?? null,
            ':status' => $data['status'] ?? 'ACTIVE',
            ':version' => $newVersion,
            ':now' => $now,
        ]);
        $supersede = $this->db->prepare(
            'UPDATE governance.policy SET status = :status, effective_until = :now
             WHERE policy_id = :id'
        );
        $supersede->execute([
            ':status' => 'SUPERSEDED',
            ':now' => $now,
            ':id' => $id,
        ]);
        return $this->getPolicy($newId);
    }

    public function listPolicyEvaluations(string $policyId, int $page, int $perPage): array
    {
        [$page, $perPage, $offset] = $this->parsePagination(
            ['page' => $page, 'per_page' => $perPage]
        );
        $clause = 'WHERE policy_id = :policy_id';
        $params = [':policy_id' => $policyId];
        $total = $this->countRows('governance.policy_evaluation', $clause, $params);
        $stmt = $this->db->prepare(
            "SELECT * FROM governance.policy_evaluation {$clause} "
            . "ORDER BY evaluated_at DESC LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return $this->paginate($stmt->fetchAll(), $total, $page, $perPage);
    }

    public function listWorkflows(array $filters, int $page, int $perPage): array
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
        if (isset($filters['workflow_type'])) {
            $where[] = 'workflow_type = :workflow_type';
            $params[':workflow_type'] = $filters['workflow_type'];
        }
        if (isset($filters['entity_type'])) {
            $where[] = 'entity_type = :entity_type';
            $params[':entity_type'] = $filters['entity_type'];
        }
        $clause = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
        $total = $this->countRows('governance.workflow', $clause, $params);
        $stmt = $this->db->prepare(
            "SELECT * FROM governance.workflow {$clause} "
            . "ORDER BY initiated_at DESC LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return $this->paginate($stmt->fetchAll(), $total, $page, $perPage);
    }

    public function cancelWorkflow(string $id, string $reason): array
    {
        $existing = $this->getWorkflow($id);
        if ($existing === null) {
            throw new ApiException(404, 'WORKFLOW_NOT_FOUND', 'Workflow was not found');
        }
        $now = $this->now();
        $stmt = $this->db->prepare(
            'UPDATE governance.workflow
             SET status = :status, completed_at = :now,
                 metadata = JSON_SET(
                     COALESCE(metadata, JSON_OBJECT()),
                     :reason_key, :reason
                 )
             WHERE workflow_id = :id'
        );
        $stmt->execute([
            ':status' => 'CANCELLED',
            ':now' => $now,
            ':reason_key' => '$.cancel_reason',
            ':reason' => $reason,
            ':id' => $id,
        ]);
        return $this->getWorkflow($id);
    }

    public function listWorkflowSteps(string $workflowId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM governance.workflow_step
             WHERE workflow_id = :id
             ORDER BY step_number ASC'
        );
        $stmt->execute([':id' => $workflowId]);
        return $stmt->fetchAll();
    }

    // ─── Private Helpers ─────────────────────────────────────────────────

    private function countRows(string $table, string $clause = '', array $params = []): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM {$table} {$clause}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    // ─── Compliance Checks ──────────────────────────────────────────────

    /**
     * Check for duplicate orders within a time window.
     * Detects if an identical order (same instrument, side, quantity, price) was placed recently.
     */
    public function checkDuplicateOrder(
        string $portfolioId,
        string $instrumentId,
        string $side,
        float $quantity,
        float $price
    ): array {
        $stmt = $this->db->prepare(
            'SELECT o.order_id, o.order_ref, o.created_at, o.quantity, o.limit_price, o.side
             FROM trading.`order` o
             JOIN trading.order_intent oi ON o.order_intent_id = oi.order_intent_id
             WHERE oi.portfolio_id = :pid
               AND o.instrument_id = :iid
               AND o.side = :side
               AND o.quantity = :qty
               AND (o.limit_price = :price OR (o.limit_price IS NULL AND :price2 = 0))
               AND o.created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
             ORDER BY o.created_at DESC
             LIMIT 5'
        );
        $stmt->execute([
            ':pid' => $portfolioId,
            ':iid' => $instrumentId,
            ':side' => $side,
            ':qty' => $quantity,
            ':price' => $price,
            ':price2' => $price,
        ]);
        $duplicates = $stmt->fetchAll();

        $isDuplicate = count($duplicates) > 0;

        return [
            'check' => 'DUPLICATE_ORDER',
            'passed' => !$isDuplicate,
            'is_duplicate' => $isDuplicate,
            'duplicate_count' => count($duplicates),
            'duplicates' => $duplicates,
            'message' => $isDuplicate
                ? 'Potential duplicate order detected: identical order placed within last 5 minutes'
                : 'No duplicate orders detected',
        ];
    }

    /**
     * Check for erroneous orders — detects fat-finger errors.
     * Flags orders where price deviates significantly from market or quantity is abnormally large.
     */
    public function checkErroneousOrder(
        string $portfolioId,
        string $instrumentId,
        string $side,
        float $quantity,
        float $price
    ): array {
        $warnings = [];

        // Get latest market price
        $priceStmt = $this->db->prepare(
            'SELECT close FROM data_ingestion.ohlcv_daily
             WHERE instrument_id = :iid ORDER BY trade_date DESC LIMIT 1'
        );
        $priceStmt->execute([':iid' => $instrumentId]);
        $marketPrice = (float) ($priceStmt->fetchColumn() ?: 0);

        // Price deviation check: flag if order price is >5% away from market
        if ($marketPrice > 0) {
            $deviationPct = abs(($price - $marketPrice) / $marketPrice) * 100;
            if ($deviationPct > 10) {
                $warnings[] = [
                    'type' => 'PRICE_DEVIATION',
                    'severity' => 'HIGH',
                    'message' => sprintf(
                        'Order price %.2f deviates %.2f%% from market price %.2f',
                        $price,
                        $deviationPct,
                        $marketPrice
                    ),
                    'market_price' => $marketPrice,
                    'order_price' => $price,
                    'deviation_pct' => round($deviationPct, 2),
                ];
            } elseif ($deviationPct > 5) {
                $warnings[] = [
                    'type' => 'PRICE_DEVIATION',
                    'severity' => 'MEDIUM',
                    'message' => sprintf(
                        'Order price %.2f deviates %.2f%% from market price %.2f',
                        $price,
                        $deviationPct,
                        $marketPrice
                    ),
                    'market_price' => $marketPrice,
                    'order_price' => $price,
                    'deviation_pct' => round($deviationPct, 2),
                ];
            }
        }

        // Quantity check: flag if order quantity is >10x average daily volume
        $volStmt = $this->db->prepare(
            'SELECT AVG(volume) AS avg_vol FROM data_ingestion.ohlcv_daily
             WHERE instrument_id = :iid AND trade_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)'
        );
        $volStmt->execute([':iid' => $instrumentId]);
        $avgVol = (float) ($volStmt->fetchColumn() ?: 0);

        if ($avgVol > 0 && $quantity > $avgVol * 10) {
            $warnings[] = [
                'type' => 'ABNORMAL_QUANTITY',
                'severity' => 'HIGH',
                'message' => sprintf(
                    'Order quantity %.0f exceeds 10x average daily volume %.0f',
                    $quantity,
                    $avgVol
                ),
                'order_quantity' => $quantity,
                'avg_daily_volume' => round($avgVol, 0),
                'ratio' => round($quantity / $avgVol, 1),
            ];
        }

        // Order value check: flag if order value exceeds 1 billion IDR
        $orderValue = $quantity * $price;
        if ($orderValue > 1000000000) {
            $warnings[] = [
                'type' => 'LARGE_ORDER_VALUE',
                'severity' => 'MEDIUM',
                'message' => sprintf(
                    'Order value Rp %.0f exceeds 1 billion IDR threshold',
                    $orderValue
                ),
                'order_value' => round($orderValue, 2),
            ];
        }

        $passed = count($warnings) === 0;

        return [
            'check' => 'ERRONEOUS_ORDER',
            'passed' => $passed,
            'warning_count' => count($warnings),
            'warnings' => $warnings,
            'market_price' => $marketPrice,
            'order_value' => round($orderValue, 2),
            'message' => $passed
                ? 'No erroneous order patterns detected'
                : sprintf('%d erroneous order warning(s) detected', count($warnings)),
        ];
    }

    /**
     * Check capital/credit threshold for a portfolio.
     * Ensures the portfolio has sufficient capital for the proposed order.
     */
    public function checkCapitalThreshold(string $portfolioId, float $orderValue): array
    {
        // Get portfolio cash balance
        $cashStmt = $this->db->prepare(
            'SELECT cb.available_balance FROM portfolio.cash_balance cb
             WHERE cb.portfolio_id = :pid ORDER BY as_of DESC LIMIT 1'
        );
        $cashStmt->execute([':pid' => $portfolioId]);
        $cashBalance = (float) ($cashStmt->fetchColumn() ?: 0);

        // Get total position value
        $posStmt = $this->db->prepare(
            'SELECT SUM(p.quantity * p.average_cost) AS total_value
             FROM portfolio.position p
             WHERE p.portfolio_id = :pid AND p.status = "OPEN"'
        );
        $posStmt->execute([':pid' => $portfolioId]);
        $positionValue = (float) ($posStmt->fetchColumn() ?: 0);

        $totalCapital = $cashBalance + $positionValue;

        // Get risk limits for capital thresholds
        $limitStmt = $this->db->prepare(
            'SELECT limit_type, limit_value, limit_unit FROM risk.risk_limit
             WHERE portfolio_id = :pid AND status = "ACTIVE"
               AND limit_type IN ("MAX_CAPITAL_DEPLOYMENT", "MAX_ORDER_VALUE", "MIN_CASH_RESERVE")'
        );
        $limitStmt->execute([':pid' => $portfolioId]);
        $limits = $limitStmt->fetchAll();

        $violations = [];
        $cashAfterOrder = $cashBalance - $orderValue;

        // Check cash sufficiency
        if ($cashAfterOrder < 0) {
            $violations[] = [
                'type' => 'INSUFFICIENT_CASH',
                'severity' => 'CRITICAL',
                'message' => sprintf(
                    'Insufficient cash: Rp %.0f available, Rp %.0f required',
                    $cashBalance,
                    $orderValue
                ),
                'cash_balance' => $cashBalance,
                'order_value' => $orderValue,
                'shortfall' => round(abs($cashAfterOrder), 2),
            ];
        }

        // Check against limits
        foreach ($limits as $limit) {
            $limitValue = (float) $limit['limit_value'];
            $limitType = $limit['limit_type'];

            if ($limitType === 'MAX_ORDER_VALUE' && $orderValue > $limitValue) {
                $violations[] = [
                    'type' => 'MAX_ORDER_VALUE_EXCEEDED',
                    'severity' => 'HIGH',
                    'message' => sprintf(
                        'Order value Rp %.0f exceeds max order limit Rp %.0f',
                        $orderValue,
                        $limitValue
                    ),
                    'limit_value' => $limitValue,
                    'order_value' => $orderValue,
                ];
            }

            if ($limitType === 'MIN_CASH_RESERVE' && $cashAfterOrder < $limitValue) {
                $violations[] = [
                    'type' => 'MIN_CASH_RESERVE_BREACH',
                    'severity' => 'HIGH',
                    'message' => sprintf(
                        'Cash after order Rp %.0f would breach minimum reserve Rp %.0f',
                        $cashAfterOrder,
                        $limitValue
                    ),
                    'limit_value' => $limitValue,
                    'cash_after_order' => round($cashAfterOrder, 2),
                ];
            }

            if ($limitType === 'MAX_CAPITAL_DEPLOYMENT') {
                $deploymentPct = $totalCapital > 0 ? (($positionValue + $orderValue) / $totalCapital) * 100 : 0;
                if ($deploymentPct > $limitValue) {
                    $violations[] = [
                        'type' => 'MAX_CAPITAL_DEPLOYMENT_EXCEEDED',
                        'severity' => 'HIGH',
                        'message' => sprintf(
                            'Capital deployment %.1f%% exceeds max %.1f%%',
                            $deploymentPct,
                            $limitValue
                        ),
                        'limit_value' => $limitValue,
                        'current_deployment_pct' => round($deploymentPct, 1),
                    ];
                }
            }
        }

        $passed = count($violations) === 0;

        return [
            'check' => 'CAPITAL_THRESHOLD',
            'passed' => $passed,
            'violation_count' => count($violations),
            'violations' => $violations,
            'cash_balance' => round($cashBalance, 2),
            'position_value' => round($positionValue, 2),
            'total_capital' => round($totalCapital, 2),
            'order_value' => round($orderValue, 2),
            'cash_after_order' => round($cashAfterOrder, 2),
            'message' => $passed
                ? 'Capital threshold check passed'
                : sprintf('%d capital threshold violation(s) detected', count($violations)),
        ];
    }

    /**
     * Calculate minimum capital required for a transaction.
     *
     * Includes order value, broker commission, exchange fees, clearing fees,
     * and VAT on commission. Validates lot size (1 lot = 100 shares for BEI),
     * checks against cash balance and risk limits.
     *
     * @param string $portfolioId
     * @param string $instrumentId
     * @param float $quantity  Number of shares (must be multiple of 100 for BEI)
     * @param float $price     Order price per share
     * @param string $side     BUY or SELL
     * @return array Detailed breakdown of minimum capital requirement
     */
    public function calculateMinimumCapital(
        string $portfolioId,
        string $instrumentId,
        float $quantity,
        float $price,
        string $side = 'BUY'
    ): array {
        // ─── Validate inputs ───────────────────────────────────────────
        if ($quantity <= 0) {
            throw new ApiException(422, 'VALIDATION_ERROR', 'Quantity must be greater than 0');
        }
        if ($price <= 0) {
            throw new ApiException(422, 'VALIDATION_ERROR', 'Price must be greater than 0');
        }
        if (!in_array($side, ['BUY', 'SELL'], true)) {
            throw new ApiException(422, 'VALIDATION_ERROR', 'Side must be BUY or SELL');
        }

        // ─── Constants: BEI / KPEI fee structure ───────────────────────
        $LOT_SIZE = 100;
        $COMMISSION_RATE_BUY = 0.0015;   // 0.15% broker commission for BUY
        $COMMISSION_RATE_SELL = 0.0025;  // 0.25% broker commission for SELL (incl. sales tax)
        $BEI_FEE_RATE = 0.00004;         // 0.004% Bursa Efek Indonesia fee
        $KPEI_FEE_RATE = 0.00003;        // 0.003% KPEI clearing fee
        $VAT_RATE = 0.11;                // 11% PPN on commission
        $MIN_COMMISSION = 10000;         // Minimum broker commission Rp 10,000

        // ─── Validate lot size ─────────────────────────────────────────
        $lots = (int) ($quantity / $LOT_SIZE);
        $remainder = $quantity % $LOT_SIZE;
        $lotWarning = null;
        if ($remainder !== 0) {
            $lotWarning = sprintf(
                'Quantity %s is not a multiple of %d'
                . ' (1 lot = 100 shares on BEI).'
                . ' Nearest valid quantity: %d shares (%d lots).',
                $this->fmtNumber($quantity),
                $LOT_SIZE,
                $lots * $LOT_SIZE,
                $lots
            );
            // Round down to nearest lot
            $effectiveQuantity = (float) ($lots * $LOT_SIZE);
            if ($effectiveQuantity <= 0) {
                $effectiveQuantity = $LOT_SIZE;
                $lots = 1;
            }
        } else {
            $effectiveQuantity = $quantity;
        }

        // ─── Calculate order value ─────────────────────────────────────
        $orderValue = $effectiveQuantity * $price;

        // ─── Calculate fees ────────────────────────────────────────────
        $commissionRate = $side === 'BUY' ? $COMMISSION_RATE_BUY : $COMMISSION_RATE_SELL;
        $commission = max($orderValue * $commissionRate, $MIN_COMMISSION);
        $vatOnCommission = $commission * $VAT_RATE;
        $beiFee = $orderValue * $BEI_FEE_RATE;
        $kpeiFee = $orderValue * $KPEI_FEE_RATE;

        // For SELL: include sales tax (0.1% of transaction value)
        $salesTax = $side === 'SELL' ? $orderValue * 0.001 : 0.0;

        $totalFees = $commission + $vatOnCommission + $beiFee + $kpeiFee + $salesTax;

        // ─── Minimum capital required ──────────────────────────────────
        $minCapital = $side === 'BUY'
            ? $orderValue + $totalFees
            : max($totalFees, $MIN_COMMISSION); // For SELL, only need fees (shares already held)

        // ─── Get portfolio cash balance ────────────────────────────────
        $cashStmt = $this->db->prepare(
            'SELECT cb.available_balance FROM portfolio.cash_balance cb
             WHERE cb.portfolio_id = :pid ORDER BY as_of DESC LIMIT 1'
        );
        $cashStmt->execute([':pid' => $portfolioId]);
        $cashBalance = (float) ($cashStmt->fetchColumn() ?: 0);

        // ─── Get instrument ticker for display ─────────────────────────
        $tickerStmt = $this->db->prepare(
            'SELECT l.ticker FROM market_master.listing l
             WHERE l.instrument_id = :iid AND l.status = "ACTIVE" LIMIT 1'
        );
        $tickerStmt->execute([':iid' => $instrumentId]);
        $ticker = $tickerStmt->fetchColumn() ?: 'UNKNOWN';

        // ─── Get risk limits relevant to minimum capital ───────────────
        $limitStmt = $this->db->prepare(
            'SELECT limit_type, limit_value, limit_unit FROM risk.risk_limit
             WHERE portfolio_id = :pid AND status = "ACTIVE"
               AND limit_type IN ("MAX_ORDER_VALUE", "MAX_POSITION_VALUE", "MIN_CASH_RESERVE")'
        );
        $limitStmt->execute([':pid' => $portfolioId]);
        $limits = $limitStmt->fetchAll();

        $limitChecks = [];
        $limitViolations = [];

        foreach ($limits as $limit) {
            $limitValue = (float) $limit['limit_value'];
            $limitType = $limit['limit_type'];

            if ($limitType === 'MAX_ORDER_VALUE' && $orderValue > $limitValue) {
                $limitViolations[] = [
                    'type' => 'MAX_ORDER_VALUE_EXCEEDED',
                    'severity' => 'HIGH',
                    'message' => sprintf(
                        'Order value Rp %s exceeds max order limit Rp %s',
                        $this->fmtNumber($orderValue),
                        $this->fmtNumber($limitValue)
                    ),
                    'limit_value' => $limitValue,
                    'order_value' => $orderValue,
                ];
            }

            if ($limitType === 'MIN_CASH_RESERVE') {
                $cashAfterOrder = $cashBalance - $minCapital;
                if ($cashAfterOrder < $limitValue) {
                    $limitViolations[] = [
                        'type' => 'MIN_CASH_RESERVE_BREACH',
                        'severity' => 'HIGH',
                        'message' => sprintf(
                            'Cash after order Rp %s would breach minimum reserve Rp %s',
                            $this->fmtNumber($cashAfterOrder),
                            $this->fmtNumber($limitValue)
                        ),
                        'limit_value' => $limitValue,
                        'cash_after_order' => round($cashAfterOrder, 2),
                    ];
                }
                $limitChecks[] = [
                    'limit_type' => 'MIN_CASH_RESERVE',
                    'limit_value' => $limitValue,
                    'cash_after_order' => round($cashBalance - $minCapital, 2),
                    'passed' => ($cashBalance - $minCapital) >= $limitValue,
                ];
            }

            if ($limitType === 'MAX_POSITION_VALUE' && $side === 'BUY' && $orderValue > $limitValue) {
                $limitViolations[] = [
                    'type' => 'MAX_POSITION_VALUE_EXCEEDED',
                    'severity' => 'HIGH',
                    'message' => sprintf(
                        'Position value Rp %s exceeds max position limit Rp %s',
                        $this->fmtNumber($orderValue),
                        $this->fmtNumber($limitValue)
                    ),
                    'limit_value' => $limitValue,
                    'order_value' => $orderValue,
                ];
            }
        }

        // ─── Capital sufficiency ───────────────────────────────────────
        $shortfall = max(0, $minCapital - $cashBalance);
        $sufficient = $cashBalance >= $minCapital;

        // ─── Per-unit cost breakdown ───────────────────────────────────
        $costPerShare = $effectiveQuantity > 0 ? $minCapital / $effectiveQuantity : 0;
        $effectivePrice = $side === 'BUY'
            ? $price + ($totalFees / $effectiveQuantity)
            : $price - ($totalFees / $effectiveQuantity);

        return [
            'check' => 'MINIMUM_CAPITAL',
            'portfolio_id' => $portfolioId,
            'instrument_id' => $instrumentId,
            'ticker' => $ticker,
            'side' => $side,
            'quantity_requested' => $quantity,
            'quantity_effective' => $effectiveQuantity,
            'lots' => $lots,
            'lot_size' => $LOT_SIZE,
            'lot_warning' => $lotWarning,
            'price' => round($price, 4),
            'effective_price' => round($effectivePrice, 4),
            'cost_per_share' => round($costPerShare, 4),
            'order_value' => round($orderValue, 2),
            'fee_breakdown' => [
                'broker_commission' => round($commission, 2),
                'commission_rate' => $commissionRate,
                'vat_on_commission' => round($vatOnCommission, 2),
                'vat_rate' => $VAT_RATE,
                'bei_fee' => round($beiFee, 2),
                'bei_fee_rate' => $BEI_FEE_RATE,
                'kpei_fee' => round($kpeiFee, 2),
                'kpei_fee_rate' => $KPEI_FEE_RATE,
                'sales_tax' => round($salesTax, 2),
                'total_fees' => round($totalFees, 2),
            ],
            'minimum_capital' => round($minCapital, 2),
            'cash_balance' => round($cashBalance, 2),
            'shortfall' => round($shortfall, 2),
            'sufficient' => $sufficient,
            'limit_checks' => $limitChecks,
            'limit_violations' => $limitViolations,
            'violation_count' => count($limitViolations),
            'passed' => $sufficient && count($limitViolations) === 0,
            'message' => $sufficient
                ? (count($limitViolations) === 0
                    ? 'Minimum capital is sufficient for this transaction'
                    : sprintf('Capital is sufficient but %d limit violation(s) detected', count($limitViolations)))
                : sprintf(
                    'Insufficient capital: Rp %s required, Rp %s available, shortfall Rp %s',
                    $this->fmtNumber($minCapital),
                    $this->fmtNumber($cashBalance),
                    $this->fmtNumber($shortfall)
                ),
        ];
    }

    /**
     * Format a number with thousand separators (Indonesian style).
     */
    private function fmtNumber(float $value): string
    {
        return number_format($value, 0, ',', '.');
    }
}
