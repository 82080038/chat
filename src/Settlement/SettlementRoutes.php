<?php

declare(strict_types=1);

namespace Platform\Settlement;

use Platform\Core\Application;
use Platform\Core\Exceptions\ApiException;
use Platform\Core\Http\Request;
use Platform\Core\Http\Response;
use Platform\Core\Http\Router;

final class SettlementRoutes
{
    public static function register(Router $router): void
    {
        // Settlements
        $router->get('/settlements', [self::class, 'listSettlements'], ['bearer']);
        $router->get('/settlements/{id}', [self::class, 'getSettlement'], ['bearer']);
        $router->get(
            '/portfolios/{id}/settlements',
            [self::class, 'portfolioSettlements'],
            ['bearer']
        );

        // Reconciliations
        $router->get('/reconciliations', [self::class, 'listReconciliations'], ['bearer']);
        $router->get('/reconciliations/{id}', [self::class, 'getReconciliation'], ['bearer']);
        $router->post(
            '/reconciliations/{id}/resolve',
            [self::class, 'resolveReconciliation'],
            ['bearer']
        );
        $router->get(
            '/portfolios/{id}/reconciliations',
            [self::class, 'portfolioReconciliations'],
            ['bearer']
        );
    }

    // ─── Settlements ─────────────────────────────────────────────────────

    public static function listSettlements(Request $request): Response
    {
        [$page, $perPage] = self::pagination($request);
        $result = self::service()->listSettlements(
            [
                'portfolio_id' => $request->getQuery('filter[portfolio_id]'),
                'status' => $request->getQuery('filter[status]'),
                'settlement_date_from' => $request->getQuery('filter[settlement_date_from]'),
                'settlement_date_to' => $request->getQuery('filter[settlement_date_to]'),
            ],
            $page,
            $perPage
        );
        return Response::ok($result['data'], $result['meta']);
    }

    public static function getSettlement(Request $request): Response
    {
        $row = self::service()->getSettlement((string) $request->getParam('id'));
        return Response::ok(
            self::required($row, 'SETTLEMENT_NOT_FOUND', 'Settlement was not found')
        );
    }

    public static function portfolioSettlements(Request $request): Response
    {
        return Response::ok(
            self::service()->getPendingSettlements((string) $request->getParam('id'))
        );
    }

    // ─── Reconciliations ─────────────────────────────────────────────────

    public static function listReconciliations(Request $request): Response
    {
        [$page, $perPage] = self::pagination($request);
        $result = self::service()->listReconciliations(
            [
                'portfolio_id' => $request->getQuery('filter[portfolio_id]'),
                'status' => $request->getQuery('filter[status]'),
                'reconciliation_type' => $request->getQuery('filter[reconciliation_type]'),
            ],
            $page,
            $perPage
        );
        return Response::ok($result['data'], $result['meta']);
    }

    public static function getReconciliation(Request $request): Response
    {
        $row = self::service()->getReconciliation((string) $request->getParam('id'));
        return Response::ok(
            self::required($row, 'RECONCILIATION_NOT_FOUND', 'Reconciliation was not found')
        );
    }

    public static function resolveReconciliation(Request $request): Response
    {
        $resolution = (string) $request->getBody('resolution', 'Resolved by owner');
        return Response::ok(
            self::service()->resolveReconciliation(
                (string) $request->getParam('id'),
                $resolution
            )
        );
    }

    public static function portfolioReconciliations(Request $request): Response
    {
        return Response::ok(
            self::service()->listPortfolioReconciliations((string) $request->getParam('id'))
        );
    }

    // ─── Private Helpers ─────────────────────────────────────────────────

    private static function service(): SettlementServiceInterface
    {
        $service = Application::getInstance()->getService('settlement');
        if (!$service instanceof SettlementServiceInterface) {
            throw new ApiException(
                503,
                'SETTLEMENT_UNAVAILABLE',
                'Settlement service is unavailable'
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
