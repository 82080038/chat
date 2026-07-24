<?php

declare(strict_types=1);

namespace Platform\Trading;

use Platform\Core\Application;
use Platform\Core\Exceptions\ApiException;
use Platform\Core\Http\Request;
use Platform\Core\Http\Response;
use Platform\Core\Http\Router;

final class BrokerAdapterRoutes
{
    public static function register(Router $router): void
    {
        $router->post(
            '/brokers/{brokerId}/auth',
            [self::class, 'authenticateBroker'],
            ['bearer']
        );
        $router->get(
            '/brokers/{brokerId}/balance',
            [self::class, 'getBalance'],
            ['bearer']
        );
        $router->get(
            '/brokers/{brokerId}/holdings',
            [self::class, 'getHoldings'],
            ['bearer']
        );
        $router->get(
            '/brokers/{brokerId}/price/{symbol}',
            [self::class, 'getPrice'],
            ['bearer']
        );
        $router->post(
            '/brokers/{brokerId}/orders',
            [self::class, 'placeOrder'],
            ['bearer']
        );
        $router->delete(
            '/brokers/{brokerId}/orders/{orderId}',
            [self::class, 'cancelOrder'],
            ['bearer']
        );
        $router->get(
            '/brokers/{brokerId}/orders/{orderId}',
            [self::class, 'getOrderStatus'],
            ['bearer']
        );
        $router->get(
            '/brokers/api-logs',
            [self::class, 'listApiLogs'],
            ['bearer']
        );
    }

    private static function service(): BrokerAdapterService
    {
        $app = Application::getInstance();
        $service = $app->getService('broker_adapter');
        if (!$service instanceof BrokerAdapterService) {
            throw new ApiException(
                503,
                'BROKER_ADAPTER_UNAVAILABLE',
                'Broker adapter service is unavailable'
            );
        }
        return $service;
    }

    public static function authenticateBroker(Request $request): Response
    {
        $brokerId = (string) $request->getParam('brokerId');
        $credentials = $request->getAllBody();
        $result = self::service()->authenticateBroker($brokerId, $credentials);
        return Response::ok($result);
    }

    public static function getBalance(Request $request): Response
    {
        $brokerId = (string) $request->getParam('brokerId');
        return Response::ok(self::service()->getBalance($brokerId));
    }

    public static function getHoldings(Request $request): Response
    {
        $brokerId = (string) $request->getParam('brokerId');
        return Response::ok(self::service()->getHoldings($brokerId));
    }

    public static function getPrice(Request $request): Response
    {
        $brokerId = (string) $request->getParam('brokerId');
        $symbol = (string) $request->getParam('symbol');
        return Response::ok(self::service()->getPrice($brokerId, $symbol));
    }

    public static function placeOrder(Request $request): Response
    {
        $brokerId = (string) $request->getParam('brokerId');
        $order = $request->getAllBody();
        $result = self::service()->placeOrder($brokerId, $order);
        return Response::created($result);
    }

    public static function cancelOrder(Request $request): Response
    {
        $brokerId = (string) $request->getParam('brokerId');
        $orderId = (string) $request->getParam('orderId');
        return Response::ok(self::service()->cancelOrder($brokerId, $orderId));
    }

    public static function getOrderStatus(Request $request): Response
    {
        $brokerId = (string) $request->getParam('brokerId');
        $orderId = (string) $request->getParam('orderId');
        return Response::ok(self::service()->getOrderStatus($brokerId, $orderId));
    }

    public static function listApiLogs(Request $request): Response
    {
        $query = $request->getAllQuery();
        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = min(200, max(1, (int) ($query['per_page'] ?? 50)));
        $filters = [];
        if (isset($query['broker_id'])) {
            $filters['broker_id'] = $query['broker_id'];
        }
        $result = self::service()->listApiLogs($filters, $page, $perPage);
        return Response::ok($result['data'], $result['meta']);
    }
}
