<?php

declare(strict_types=1);

namespace Platform\Microstructure;

use Platform\Core\Application;
use Platform\Core\Exceptions\ApiException;
use Platform\Core\Http\Request;
use Platform\Core\Http\Response;
use Platform\Core\Http\Router;

final class MicrostructureRoutes
{
    public static function register(Router $router): void
    {
        $router->post(
            '/microstructure/order-books',
            [self::class, 'captureOrderBook'],
            ['bearer']
        );
        $router->get(
            '/microstructure/order-books',
            [self::class, 'listOrderBooks'],
            ['bearer']
        );
        $router->get(
            '/microstructure/order-books/{id}',
            [self::class, 'getOrderBook'],
            ['bearer']
        );
        $router->get(
            '/microstructure/order-books/instrument/{instrumentId}/latest',
            [self::class, 'getLatestOrderBook'],
            ['bearer']
        );
        $router->get(
            '/microstructure/spread-analysis/{instrumentId}',
            [self::class, 'spreadAnalysis'],
            ['bearer']
        );
        $router->post(
            '/microstructure/market-impact',
            [self::class, 'marketImpact'],
            ['bearer']
        );
        $router->get(
            '/microstructure/liquidity-profile/{instrumentId}',
            [self::class, 'liquidityProfile'],
            ['bearer']
        );
        $router->get(
            '/microstructure/metrics',
            [self::class, 'listMetrics'],
            ['bearer']
        );
        $router->get(
            '/microstructure/metrics/{instrumentId}/{date}',
            [self::class, 'getMetrics'],
            ['bearer']
        );
    }

    private static function service(): MicrostructureService
    {
        $app = Application::getInstance();
        $service = $app->getService('microstructure');
        if (!$service instanceof MicrostructureService) {
            throw new ApiException(
                500,
                'SERVICE_NOT_FOUND',
                'MicrostructureService not registered'
            );
        }
        return $service;
    }

    public static function captureOrderBook(Request $request): Response
    {
        $data = $request->json();
        $result = self::service()->captureOrderBook($data);
        return Response::created($result);
    }

    public static function getOrderBook(Request $request, array $params): Response
    {
        $result = self::service()->getOrderBook($params['id']);
        if ($result === null) {
            throw new ApiException(
                404,
                'NOT_FOUND',
                'Order book snapshot not found'
            );
        }
        return Response::ok($result);
    }

    public static function getLatestOrderBook(
        Request $request,
        array $params
    ): Response {
        $result = self::service()->getLatestOrderBook(
            $params['instrumentId']
        );
        if ($result === null) {
            throw new ApiException(
                404,
                'NOT_FOUND',
                'No order book snapshot found for instrument'
            );
        }
        return Response::ok($result);
    }

    public static function listOrderBooks(Request $request): Response
    {
        $filters = $request->query();
        $page = (int) ($filters['page'] ?? 1);
        $perPage = (int) ($filters['per_page'] ?? 20);
        unset($filters['page'], $filters['per_page']);
        $result = self::service()->listOrderBooks($filters, $page, $perPage);
        return Response::ok($result);
    }

    public static function spreadAnalysis(
        Request $request,
        array $params
    ): Response {
        $days = (int) ($request->query()['days'] ?? 30);
        $result = self::service()->calculateSpreadAnalysis(
            $params['instrumentId'],
            $days
        );
        return Response::ok($result);
    }

    public static function marketImpact(Request $request): Response
    {
        $data = $request->json();
        if (!isset($data['instrument_id'], $data['order_quantity'], $data['side'])) {
            throw new ApiException(
                400,
                'VALIDATION_ERROR',
                'instrument_id, order_quantity, and side are required'
            );
        }
        $result = self::service()->calculateMarketImpact(
            $data['instrument_id'],
            (float) $data['order_quantity'],
            $data['side']
        );
        return Response::ok($result);
    }

    public static function liquidityProfile(
        Request $request,
        array $params
    ): Response {
        $result = self::service()->calculateLiquidityProfile(
            $params['instrumentId']
        );
        return Response::ok($result);
    }

    public static function listMetrics(Request $request): Response
    {
        $filters = $request->query();
        $page = (int) ($filters['page'] ?? 1);
        $perPage = (int) ($filters['per_page'] ?? 20);
        unset($filters['page'], $filters['per_page']);
        $result = self::service()->listMetrics($filters, $page, $perPage);
        return Response::ok($result);
    }

    public static function getMetrics(
        Request $request,
        array $params
    ): Response {
        $result = self::service()->getMetrics(
            $params['instrumentId'],
            $params['date']
        );
        if ($result === null) {
            throw new ApiException(
                404,
                'NOT_FOUND',
                'Metrics not found for instrument on given date'
            );
        }
        return Response::ok($result);
    }
}
