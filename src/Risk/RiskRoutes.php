<?php

declare(strict_types=1);

namespace Platform\Risk;

use Platform\Core\Application;
use Platform\Core\Exceptions\ApiException;
use Platform\Core\Http\Request;
use Platform\Core\Http\Response;
use Platform\Core\Http\Router;

final class RiskRoutes
{
    public static function register(Router $router): void
    {
        // Risk Profiles
        $router->get('/risk-profiles', [self::class, 'listRiskProfiles'], ['bearer']);
        $router->post('/risk-profiles', [self::class, 'createRiskProfile'], ['bearer']);
        $router->get('/risk-profiles/{id}', [self::class, 'getRiskProfile'], ['bearer']);
        $router->put('/risk-profiles/{id}', [self::class, 'updateRiskProfile'], ['bearer']);

        // Risk Limits (nested under portfolio)
        $router->get('/portfolios/{id}/risk-limits', [self::class, 'listRiskLimits'], ['bearer']);
        $router->post('/portfolios/{id}/risk-limits', [self::class, 'setRiskLimit'], ['bearer']);
        $router->put('/risk-limits/{limitId}', [self::class, 'updateRiskLimit'], ['bearer']);
        $router->delete('/risk-limits/{limitId}', [self::class, 'removeRiskLimit'], ['bearer']);

        // Risk Assessments
        $router->get(
            '/portfolios/{id}/risk-assessments',
            [self::class, 'listRiskAssessments'],
            ['bearer']
        );
        $router->post(
            '/portfolios/{id}/risk-assessments',
            [self::class, 'triggerAssessment'],
            ['bearer']
        );
        $router->get('/risk-assessments/{id}', [self::class, 'getRiskAssessment'], ['bearer']);

        // Risk Events
        $router->get('/risk-events', [self::class, 'listRiskEvents'], ['bearer']);
        $router->get(
            '/portfolios/{id}/risk-events',
            [self::class, 'portfolioRiskEvents'],
            ['bearer']
        );
        $router->get('/risk-events/{id}', [self::class, 'getRiskEvent'], ['bearer']);
        $router->post(
            '/risk-events/{id}/acknowledge',
            [self::class, 'acknowledgeRiskEvent'],
            ['bearer']
        );
        $router->post('/risk-events/{id}/resolve', [self::class, 'resolveRiskEvent'], ['bearer']);
    }

    // ─── Risk Profiles ───────────────────────────────────────────────────

    public static function listRiskProfiles(Request $request): Response
    {
        [$page, $perPage] = self::pagination($request);
        $result = self::service()->listRiskProfiles(
            [
                'status' => $request->getQuery('status'),
                'risk_tolerance' => $request->getQuery('risk_tolerance'),
            ],
            $page,
            $perPage
        );
        return Response::ok($result['data'], $result['meta']);
    }

    public static function createRiskProfile(Request $request): Response
    {
        return Response::created(self::service()->createRiskProfile($request->getAllBody()));
    }

    public static function getRiskProfile(Request $request): Response
    {
        $row = self::service()->getRiskProfile((string) $request->getParam('id'));
        return Response::ok(
            self::required($row, 'RISK_PROFILE_NOT_FOUND', 'Risk profile was not found')
        );
    }

    public static function updateRiskProfile(Request $request): Response
    {
        return Response::ok(
            self::service()->updateRiskProfile(
                (string) $request->getParam('id'),
                $request->getAllBody()
            )
        );
    }

    // ─── Risk Limits ─────────────────────────────────────────────────────

    public static function listRiskLimits(Request $request): Response
    {
        return Response::ok(
            self::service()->listRiskLimits((string) $request->getParam('id'))
        );
    }

    public static function setRiskLimit(Request $request): Response
    {
        return Response::created(
            self::service()->setRiskLimit(
                (string) $request->getParam('id'),
                $request->getAllBody()
            )
        );
    }

    public static function updateRiskLimit(Request $request): Response
    {
        return Response::ok(
            self::service()->updateRiskLimit(
                (string) $request->getParam('limitId'),
                $request->getAllBody()
            )
        );
    }

    public static function removeRiskLimit(Request $request): Response
    {
        return Response::ok(
            self::service()->removeRiskLimit((string) $request->getParam('limitId'))
        );
    }

    // ─── Risk Assessments ────────────────────────────────────────────────

    public static function listRiskAssessments(Request $request): Response
    {
        [$page, $perPage] = self::pagination($request);
        $result = self::service()->listRiskAssessments(
            (string) $request->getParam('id'),
            $page,
            $perPage
        );
        return Response::ok($result['data'], $result['meta']);
    }

    public static function triggerAssessment(Request $request): Response
    {
        return Response::created(
            self::service()->triggerAssessment(
                (string) $request->getParam('id'),
                $request->getAllBody()
            )
        );
    }

    public static function getRiskAssessment(Request $request): Response
    {
        $row = self::service()->getRiskAssessment((string) $request->getParam('id'));
        return Response::ok(
            self::required($row, 'RISK_ASSESSMENT_NOT_FOUND', 'Risk assessment was not found')
        );
    }

    // ─── Risk Events ─────────────────────────────────────────────────────

    public static function listRiskEvents(Request $request): Response
    {
        [$page, $perPage] = self::pagination($request);
        $result = self::service()->listRiskEvents(
            [
                'portfolio_id' => $request->getQuery('filter[portfolio_id]'),
                'status' => $request->getQuery('filter[status]'),
                'severity' => $request->getQuery('filter[severity]'),
            ],
            $page,
            $perPage
        );
        return Response::ok($result['data'], $result['meta']);
    }

    public static function portfolioRiskEvents(Request $request): Response
    {
        return Response::ok(
            self::service()->listPortfolioRiskEvents((string) $request->getParam('id'))
        );
    }

    public static function getRiskEvent(Request $request): Response
    {
        $row = self::service()->getRiskEvent((string) $request->getParam('id'));
        return Response::ok(
            self::required($row, 'RISK_EVENT_NOT_FOUND', 'Risk event was not found')
        );
    }

    public static function acknowledgeRiskEvent(Request $request): Response
    {
        return Response::ok(
            self::service()->acknowledgeRiskEvent((string) $request->getParam('id'))
        );
    }

    public static function resolveRiskEvent(Request $request): Response
    {
        $resolution = (string) $request->getBody('resolution', 'Resolved');
        return Response::ok(
            self::service()->resolveRiskEvent((string) $request->getParam('id'), $resolution)
        );
    }

    // ─── Private Helpers ─────────────────────────────────────────────────

    private static function service(): RiskServiceInterface
    {
        $service = Application::getInstance()->getService('risk');
        if (!$service instanceof RiskServiceInterface) {
            throw new ApiException(
                503,
                'RISK_UNAVAILABLE',
                'Risk service is unavailable'
            );
        }
        return $service;
    }

    private static function pagination(Request $request): array
    {
        return [
            max(1, (int) $request->getQuery('page', 1)),
            min(200, max(1, (int) $request->getQuery('per_page', 50))),
        ];
    }

    private static function required(?array $row, string $code, string $message): array
    {
        if ($row === null) {
            throw new ApiException(404, $code, $message);
        }
        return $row;
    }
}
