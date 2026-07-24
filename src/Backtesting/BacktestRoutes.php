<?php

declare(strict_types=1);

namespace Platform\Backtesting;

use Platform\Core\Application;
use Platform\Core\Exceptions\ApiException;
use Platform\Core\Http\Request;
use Platform\Core\Http\Response;
use Platform\Core\Http\Router;

final class BacktestRoutes
{
    public static function register(Router $router): void
    {
        $router->post('/backtests', [self::class, 'createRun'], ['bearer']);
        $router->get('/backtests', [self::class, 'listRuns'], ['bearer']);
        $router->get('/backtests/{id}', [self::class, 'getRun'], ['bearer']);
        $router->post('/backtests/{id}/execute', [self::class, 'executeRun'], ['bearer']);
        $router->get('/backtests/{id}/trades', [self::class, 'getTrades'], ['bearer']);
        $router->get('/backtests/{id}/metrics', [self::class, 'getMetrics'], ['bearer']);
    }

    private static function service(): BacktestService
    {
        $app = Application::getInstance();
        $service = $app->getService('backtest');
        if (!$service instanceof BacktestService) {
            throw new ApiException(
                503,
                'BACKTEST_UNAVAILABLE',
                'Backtest service is unavailable'
            );
        }
        return $service;
    }

    public static function createRun(Request $request): Response
    {
        $data = $request->getAllBody();
        $result = self::service()->createRun($data);
        return Response::created($result);
    }

    public static function getRun(Request $request): Response
    {
        $id = (string) $request->getParam('id');
        $row = self::service()->getRun($id);
        if (!$row) {
            return Response::error(404, 'NOT_FOUND', "Backtest run {$id} not found");
        }
        return Response::ok($row);
    }

    public static function listRuns(Request $request): Response
    {
        $query = $request->getAllQuery();
        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = min(200, max(1, (int) ($query['per_page'] ?? 50)));
        $filters = [];
        if (isset($query['status'])) {
            $filters['status'] = $query['status'];
        }
        if (isset($query['strategy_name'])) {
            $filters['strategy_name'] = $query['strategy_name'];
        }
        $result = self::service()->listRuns($filters, $page, $perPage);
        return Response::ok($result['data'], $result['meta']);
    }

    public static function executeRun(Request $request): Response
    {
        $id = (string) $request->getParam('id');
        $body = $request->getAllBody();
        $priceData = $body['price_data'] ?? [];
        $result = self::service()->executeRun($id, $priceData);
        return Response::ok($result);
    }

    public static function getTrades(Request $request): Response
    {
        $id = (string) $request->getParam('id');
        $trades = self::service()->getRunTrades($id);
        return Response::ok($trades);
    }

    public static function getMetrics(Request $request): Response
    {
        $id = (string) $request->getParam('id');
        $metrics = self::service()->getRunMetrics($id);
        if (!$metrics) {
            return Response::error(
                404,
                'NOT_FOUND',
                "Metrics for run {$id} not found"
            );
        }
        return Response::ok($metrics);
    }
}
