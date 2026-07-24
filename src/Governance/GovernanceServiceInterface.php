<?php

declare(strict_types=1);

namespace Platform\Governance;

interface GovernanceServiceInterface
{
    public function auditLog(array $data): void;
    public function getAuditLog(string $id): ?array;
    public function listAuditLogs(array $filters, int $page, int $perPage): array;
    public function requestApproval(string $entityType, string $entityId, string $approvalType): array;
    public function approve(string $approvalId): array;
    public function reject(string $approvalId, string $reason): array;
    public function getApproval(string $id): ?array;
    public function listApprovals(array $filters, int $page, int $perPage): array;
    public function createPolicy(array $data): array;
    public function getPolicy(string $id): ?array;
    public function updatePolicy(string $id, array $data): array;
    public function listPolicies(array $filters, int $page, int $perPage): array;
    public function evaluatePolicy(string $policyId, string $entityType, string $entityId): array;
    public function listPolicyEvaluations(string $policyId, int $page, int $perPage): array;
    public function startWorkflow(string $type, string $entityType, string $entityId): array;
    public function getWorkflow(string $id): ?array;
    public function listWorkflows(array $filters, int $page, int $perPage): array;
    public function cancelWorkflow(string $id, string $reason): array;
    public function listWorkflowSteps(string $workflowId): array;
    public function completeWorkflowStep(string $workflowId, string $stepId, array $result): array;
}
