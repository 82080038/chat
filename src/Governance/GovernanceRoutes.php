<?php

declare(strict_types=1);

namespace Platform\Governance;

use Platform\Core\Http\Request;
use Platform\Core\Http\Response;
use Platform\Core\Http\Router;

final class GovernanceRoutes
{
    public static function register(Router $router): void
    {
        // Audit Logs
        $router->get('/audit-logs', [self::class, 'listAuditLogs'], ['bearer']);
        $router->get('/audit-logs/{id}', [self::class, 'getAuditLog'], ['bearer']);
        $router->get('/audit-logs/entity/{entityType}/{entityId}', [self::class, 'getEntityAuditTrail'], ['bearer']);

        // Approvals
        $router->get('/approvals', [self::class, 'listApprovals'], ['bearer']);
        $router->get('/approvals/{id}', [self::class, 'getApproval'], ['bearer']);
        $router->post('/approvals/{id}/approve', [self::class, 'approveApproval'], ['bearer']);
        $router->post('/approvals/{id}/reject', [self::class, 'rejectApproval'], ['bearer']);

        // Policies
        $router->get('/policies', [self::class, 'listPolicies'], ['bearer']);
        $router->post('/policies', [self::class, 'createPolicy'], ['bearer']);
        $router->get('/policies/{id}', [self::class, 'getPolicy'], ['bearer']);
        $router->post('/policies/{id}/evaluate', [self::class, 'evaluatePolicy'], ['bearer']);

        // Workflows
        $router->get('/workflows', [self::class, 'listWorkflows'], ['bearer']);
        $router->get('/workflows/{id}', [self::class, 'getWorkflow'], ['bearer']);
        $router->post('/workflows/{id}/steps/{stepId}/complete', [self::class, 'completeStep'], ['bearer']);
        $router->post('/workflows/{id}/cancel', [self::class, 'cancelWorkflow'], ['bearer']);
    }

    private static function service(): GovernanceService
    {
        $app = \Platform\Core\Application::getInstance();
        $service = $app->getService('governance');
        return $service instanceof GovernanceService ? $service : new GovernanceService();
    }

    public static function listAuditLogs(Request $request): Response
    {
        $query = $request->getAllQuery();
        [$page, $perPage] = self::parsePage($query);
        $filters = self::extractFilters($query, ['entity_type', 'entity_id', 'actor_type']);
        $result = self::service()->listAuditLogs($filters, $page, $perPage);
        return Response::ok($result['data'], $result['meta']);
    }

    public static function getAuditLog(Request $request): Response
    {
        $id = $request->getParam('id');
        $result = self::service()->listAuditLogs(['entity_id' => $id], 1, 1);
        if (empty($result['data'])) {
            return Response::error(404, 'NOT_FOUND', "Audit log {$id} not found");
        }
        return Response::ok($result['data'][0]);
    }

    public static function getEntityAuditTrail(Request $request): Response
    {
        $entityType = $request->getParam('entityType');
        $entityId = $request->getParam('entityId');
        $query = $request->getAllQuery();
        [$page, $perPage] = self::parsePage($query);
        $result = self::service()->listAuditLogs([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
        ], $page, $perPage);
        return Response::ok($result['data'], $result['meta']);
    }

    public static function listApprovals(Request $request): Response
    {
        $query = $request->getAllQuery();
        [$page, $perPage] = self::parsePage($query);
        $filters = self::extractFilters($query, ['status', 'approval_type', 'entity_type']);
        $result = self::service()->listApprovals($filters, $page, $perPage);
        return Response::ok($result['data'], $result['meta']);
    }

    public static function getApproval(Request $request): Response
    {
        $id = $request->getParam('id');
        $approval = self::service()->getApproval($id);
        if (!$approval) {
            return Response::error(404, 'NOT_FOUND', "Approval {$id} not found");
        }
        return Response::ok($approval);
    }

    public static function approveApproval(Request $request): Response
    {
        $id = $request->getParam('id');
        $result = self::service()->approve($id);
        if (isset($result['error'])) {
            return Response::error(422, 'APPROVAL_ERROR', $result['error']);
        }
        return Response::ok($result);
    }

    public static function rejectApproval(Request $request): Response
    {
        $id = $request->getParam('id');
        $reason = $request->getBody('rejection_reason', '');
        $result = self::service()->reject($id, $reason);
        if (isset($result['error'])) {
            return Response::error(422, 'APPROVAL_ERROR', $result['error']);
        }
        return Response::ok($result);
    }

    public static function listPolicies(Request $request): Response
    {
        $query = $request->getAllQuery();
        [$page, $perPage] = self::parsePage($query);
        $filters = self::extractFilters($query, ['policy_type', 'status']);
        $result = self::service()->listPolicies($filters, $page, $perPage);
        return Response::ok($result['data'], $result['meta']);
    }

    public static function createPolicy(Request $request): Response
    {
        $data = $request->getAllBody();
        $result = self::service()->createPolicy($data);
        return Response::created($result);
    }

    public static function getPolicy(Request $request): Response
    {
        $id = $request->getParam('id');
        $policy = self::service()->getPolicy($id);
        if (!$policy) {
            return Response::error(404, 'NOT_FOUND', "Policy {$id} not found");
        }
        return Response::ok($policy);
    }

    public static function evaluatePolicy(Request $request): Response
    {
        $policyId = $request->getParam('id');
        $entityType = $request->getBody('entity_type', '');
        $entityId = $request->getBody('entity_id', '');
        $result = self::service()->evaluatePolicy($policyId, $entityType, $entityId);
        if (isset($result['error'])) {
            return Response::error(404, 'NOT_FOUND', $result['error']);
        }
        return Response::ok($result);
    }

    public static function listWorkflows(Request $request): Response
    {
        return Response::ok([], ['page' => 1, 'per_page' => 50, 'total' => 0, 'total_pages' => 0]);
    }

    public static function getWorkflow(Request $request): Response
    {
        $id = $request->getParam('id');
        $workflow = self::service()->getWorkflow($id);
        if (!$workflow) {
            return Response::error(404, 'NOT_FOUND', "Workflow {$id} not found");
        }
        return Response::ok($workflow);
    }

    public static function completeStep(Request $request): Response
    {
        $workflowId = $request->getParam('id');
        $stepId = $request->getParam('stepId');
        $result = self::service()->completeWorkflowStep($workflowId, $stepId, $request->getAllBody());
        return Response::ok($result);
    }

    public static function cancelWorkflow(Request $request): Response
    {
        return Response::ok(['status' => 'CANCELLED']);
    }

    private static function parsePage(array $query): array
    {
        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = min(200, max(1, (int) ($query['per_page'] ?? 50)));
        return [$page, $perPage];
    }

    private static function extractFilters(array $query, array $allowedKeys): array
    {
        $filters = [];
        foreach ($allowedKeys as $key) {
            if (isset($query[$key])) {
                $filters[$key] = $query[$key];
            }
        }
        return $filters;
    }
}
