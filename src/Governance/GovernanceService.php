<?php

declare(strict_types=1);

namespace Platform\Governance;

use Platform\Core\BaseService;

final class GovernanceService extends BaseService implements GovernanceServiceInterface
{
    public function auditLog(array $data): void
    {
        $sql = 'INSERT INTO governance.audit_log
            (audit_log_id, tenant_id, actor_type, actor_id, action, entity_type, entity_id,
             old_values, new_values, ip_address, user_agent, correlation_id, created_at)
            VALUES
            (:id, :tenant_id, :actor_type, :actor_id, :action, :entity_type, :entity_id,
             :old_values, :new_values, :ip_address, :user_agent, :correlation_id, :created_at)';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id' => $this->uuid(),
            ':tenant_id' => $data['tenant_id'],
            ':actor_type' => $data['actor_type'],
            ':actor_id' => $data['actor_id'] ?? null,
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

    public function requestApproval(
        string $entityType,
        string $entityId,
        string $approvalType,
        string $requestedBy,
        ?string $tenantId = null
    ): array {
        $id = $this->uuid();
        $sql = 'INSERT INTO governance.approval
            (approval_id, tenant_id, entity_type, entity_id, approval_type,
             requested_by, requested_at, status, created_at)
            VALUES
            (:id, :tenant_id, :entity_type, :entity_id, :approval_type,
             :requested_by, :requested_at, :status, :created_at)';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id' => $id,
            ':tenant_id' => $tenantId,
            ':entity_type' => $entityType,
            ':entity_id' => $entityId,
            ':approval_type' => $approvalType,
            ':requested_by' => $requestedBy,
            ':requested_at' => $this->now(),
            ':status' => 'PENDING',
            ':created_at' => $this->now(),
        ]);

        return $this->getApproval($id);
    }

    public function approve(string $approvalId, string $approvedBy): array
    {
        $sql = 'UPDATE governance.approval
            SET status = :status, approved_by = :approved_by, approved_at = :approved_at
            WHERE approval_id = :id AND status = :pending_status';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':status' => 'APPROVED',
            ':approved_by' => $approvedBy,
            ':approved_at' => $this->now(),
            ':id' => $approvalId,
            ':pending_status' => 'PENDING',
        ]);

        if ($stmt->rowCount() === 0) {
            return ['error' => 'Approval not found or not pending'];
        }

        return $this->getApproval($approvalId);
    }

    public function reject(string $approvalId, string $rejectedBy, string $reason): array
    {
        $sql = 'UPDATE governance.approval
            SET status = :status, rejected_by = :rejected_by, rejected_at = :rejected_at,
                rejection_reason = :reason
            WHERE approval_id = :id AND status = :pending_status';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':status' => 'REJECTED',
            ':rejected_by' => $rejectedBy,
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

        if (isset($filters['tenant_id'])) {
            $where[] = 'tenant_id = :tenant_id';
            $params[':tenant_id'] = $filters['tenant_id'];
        }
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

        $sql = "SELECT * FROM governance.approval {$whereClause} ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}";
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

        if (isset($filters['tenant_id'])) {
            $where[] = 'tenant_id = :tenant_id';
            $params[':tenant_id'] = $filters['tenant_id'];
        }
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

        $sql = "SELECT * FROM governance.audit_log {$whereClause} ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll();

        return $this->paginate($items, $total, $page, $perPage);
    }

    public function createPolicy(array $data): array
    {
        $id = $this->uuid();
        $sql = 'INSERT INTO governance.policy
            (policy_id, tenant_id, policy_type, name, description, rules, priority,
             effective_from, status, version, created_by, created_at)
            VALUES
            (:id, :tenant_id, :policy_type, :name, :description, :rules, :priority,
             :effective_from, :status, :version, :created_by, :created_at)';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id' => $id,
            ':tenant_id' => $data['tenant_id'],
            ':policy_type' => $data['policy_type'],
            ':name' => $data['name'],
            ':description' => $data['description'] ?? null,
            ':rules' => isset($data['rules']) ? json_encode($data['rules']) : null,
            ':priority' => $data['priority'] ?? 0,
            ':effective_from' => $this->now(),
            ':status' => 'DRAFT',
            ':version' => 1,
            ':created_by' => $data['created_by'],
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

        if (isset($filters['tenant_id'])) {
            $where[] = 'tenant_id = :tenant_id';
            $params[':tenant_id'] = $filters['tenant_id'];
        }
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

        $sql = "SELECT * FROM governance.policy {$whereClause} ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}";
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
        string $entityId,
        string $initiatedBy,
        ?string $tenantId = null
    ): array {
        $id = $this->uuid();
        $sql = 'INSERT INTO governance.workflow
            (workflow_id, tenant_id, workflow_type, entity_type, entity_id,
             current_step, total_steps, status, initiated_by, initiated_at)
            VALUES
            (:id, :tenant_id, :type, :entity_type, :entity_id,
             :current_step, :total_steps, :status, :initiated_by, :initiated_at)';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id' => $id,
            ':tenant_id' => $tenantId,
            ':type' => $type,
            ':entity_type' => $entityType,
            ':entity_id' => $entityId,
            ':current_step' => 0,
            ':total_steps' => 1,
            ':status' => 'PENDING',
            ':initiated_by' => $initiatedBy,
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
}
