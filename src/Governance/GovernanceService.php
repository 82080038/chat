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
}
