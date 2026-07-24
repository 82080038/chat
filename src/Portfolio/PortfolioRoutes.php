<?php

declare(strict_types=1);

namespace Platform\Portfolio;

use Platform\Core\Application;
use Platform\Core\Exceptions\ApiException;
use Platform\Core\Http\Request;
use Platform\Core\Http\Response;
use Platform\Core\Http\Router;

final class PortfolioRoutes
{
    public static function register(Router $router): void
    {
        // Portfolios
        $router->get('/portfolios', [self::class, 'listPortfolios'], ['bearer']);
        $router->post('/portfolios', [self::class, 'createPortfolio'], ['bearer']);
        $router->get('/portfolios/{id}', [self::class, 'getPortfolio'], ['bearer']);
        $router->put('/portfolios/{id}', [self::class, 'updatePortfolio'], ['bearer']);
        $router->delete('/portfolios/{id}', [self::class, 'archivePortfolio'], ['bearer']);
        $router->get('/portfolios/{id}/summary', [self::class, 'portfolioSummary'], ['bearer']);
        $router->get('/portfolios/{id}/positions', [self::class, 'listPositions'], ['bearer']);
        $router->get(
            '/portfolios/{id}/positions/history',
            [self::class, 'positionHistory'],
            ['bearer']
        );
        $router->get('/portfolios/{id}/cash-balances', [self::class, 'cashBalances'], ['bearer']);
        $router->get(
            '/portfolios/{id}/cash-transactions',
            [self::class, 'cashTransactions'],
            ['bearer']
        );
        $router->post(
            '/portfolios/{id}/cash-transactions',
            [self::class, 'recordCashTxn'],
            ['bearer']
        );
        $router->get('/portfolios/{id}/targets', [self::class, 'listTargets'], ['bearer']);
        $router->post('/portfolios/{id}/targets', [self::class, 'setTarget'], ['bearer']);
        $router->put(
            '/portfolios/{id}/targets/{targetId}',
            [self::class, 'updateTarget'],
            ['bearer']
        );
        $router->delete(
            '/portfolios/{id}/targets/{targetId}',
            [self::class, 'removeTarget'],
            ['bearer']
        );
        $router->get('/portfolios/{id}/accounts', [self::class, 'listAccounts'], ['bearer']);
        $router->post('/portfolios/{id}/accounts', [self::class, 'linkAccount'], ['bearer']);
    }

    // ─── Portfolios ──────────────────────────────────────────────────────

    public static function listPortfolios(Request $request): Response
    {
        [$page, $perPage] = self::pagination($request);
        $result = self::service()->listPortfolios(
            [
                'portfolio_type' => $request->getQuery('filter[portfolio_type]'),
                'status' => $request->getQuery('filter[status]'),
            ],
            $page,
            $perPage
        );
        return Response::ok($result['data'], $result['meta']);
    }

    public static function createPortfolio(Request $request): Response
    {
        return Response::created(self::service()->createPortfolio($request->getAllBody()));
    }

    public static function getPortfolio(Request $request): Response
    {
        $row = self::service()->getPortfolio((string) $request->getParam('id'));
        return Response::ok(self::required($row, 'PORTFOLIO_NOT_FOUND', 'Portfolio was not found'));
    }

    public static function updatePortfolio(Request $request): Response
    {
        return Response::ok(
            self::service()->updatePortfolio((string) $request->getParam('id'), $request->getAllBody())
        );
    }

    public static function archivePortfolio(Request $request): Response
    {
        return Response::ok(self::service()->archivePortfolio((string) $request->getParam('id')));
    }

    public static function portfolioSummary(Request $request): Response
    {
        return Response::ok(self::service()->getPortfolioSummary((string) $request->getParam('id')));
    }

    // ─── Positions ───────────────────────────────────────────────────────

    public static function listPositions(Request $request): Response
    {
        [$page, $perPage] = self::pagination($request);
        $result = self::service()->getPositions(
            (string) $request->getParam('id'),
            $page,
            $perPage
        );
        return Response::ok($result['data'], $result['meta']);
    }

    public static function positionHistory(Request $request): Response
    {
        $rows = self::service()->getPositionHistory(
            (string) $request->getParam('id'),
            (string) $request->getQuery('instrument_id', ''),
            (string) $request->getQuery('from', date('Y-01-01')),
            (string) $request->getQuery('to', date('Y-m-d'))
        );
        return Response::ok($rows);
    }

    // ─── Cash ────────────────────────────────────────────────────────────

    public static function cashBalances(Request $request): Response
    {
        return Response::ok(self::service()->getCashBalances((string) $request->getParam('id')));
    }

    public static function cashTransactions(Request $request): Response
    {
        [$page, $perPage] = self::pagination($request);
        $result = self::service()->getCashTransactions(
            (string) $request->getParam('id'),
            $page,
            $perPage
        );
        return Response::ok($result['data'], $result['meta']);
    }

    public static function recordCashTxn(Request $request): Response
    {
        return Response::created(
            self::service()->recordCashTransaction(
                (string) $request->getParam('id'),
                $request->getAllBody()
            )
        );
    }

    // ─── Targets ─────────────────────────────────────────────────────────

    public static function listTargets(Request $request): Response
    {
        return Response::ok(self::service()->getPortfolioTargets((string) $request->getParam('id')));
    }

    public static function setTarget(Request $request): Response
    {
        return Response::created(
            self::service()->setPortfolioTarget(
                (string) $request->getParam('id'),
                $request->getAllBody()
            )
        );
    }

    public static function updateTarget(Request $request): Response
    {
        return Response::ok(
            self::service()->updatePortfolioTarget(
                (string) $request->getParam('targetId'),
                $request->getAllBody()
            )
        );
    }

    public static function removeTarget(Request $request): Response
    {
        return Response::ok(
            self::service()->removePortfolioTarget((string) $request->getParam('targetId'))
        );
    }

    // ─── Accounts ────────────────────────────────────────────────────────

    public static function listAccounts(Request $request): Response
    {
        return Response::ok(self::service()->getPortfolioAccounts((string) $request->getParam('id')));
    }

    public static function linkAccount(Request $request): Response
    {
        return Response::created(
            self::service()->linkPortfolioAccount(
                (string) $request->getParam('id'),
                $request->getAllBody()
            )
        );
    }

    // ─── Private Helpers ─────────────────────────────────────────────────

    private static function service(): PortfolioServiceInterface
    {
        $service = Application::getInstance()->getService('portfolio');
        if (!$service instanceof PortfolioServiceInterface) {
            throw new ApiException(
                503,
                'PORTFOLIO_UNAVAILABLE',
                'Portfolio service is unavailable'
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
