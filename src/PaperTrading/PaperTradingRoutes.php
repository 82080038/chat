<?php

declare(strict_types=1);

namespace Platform\PaperTrading;

use Platform\Core\Application;
use Platform\Core\Exceptions\ApiException;
use Platform\Core\Http\Request;
use Platform\Core\Http\Response;
use Platform\Core\Http\Router;

final class PaperTradingRoutes
{
    public static function register(Router $router): void
    {
        $router->post('/paper/accounts', [self::class, 'createAccount'], ['bearer']);
        $router->get('/paper/accounts/{accountId}', [self::class, 'getAccount'], ['bearer']);
        $router->post('/paper/accounts/{accountId}/orders', [self::class, 'placeOrder'], ['bearer']);
        $router->delete('/paper/accounts/{accountId}/orders/{orderId}', [self::class, 'cancelOrder'], ['bearer']);
        $router->get('/paper/accounts/{accountId}/orders', [self::class, 'listOrders'], ['bearer']);
        $router->get('/paper/accounts/{accountId}/positions', [self::class, 'getPositions'], ['bearer']);
        $router->get('/paper/accounts/{accountId}/summary', [self::class, 'getSummary'], ['bearer']);
        $router->post('/paper/validate-signal/{signalId}/{accountId}', [self::class, 'validateSignal'], ['bearer']);
    }

    private static function service(): PaperTradingService
    {
        $app = Application::getInstance();
        $service = $app->getService('paper_trading');
        if (!$service instanceof PaperTradingService) {
            throw new ApiException(
                503,
                'PAPER_TRADING_UNAVAILABLE',
                'Paper trading service is unavailable'
            );
        }
        return $service;
    }

    public static function createAccount(Request $request): Response
    {
        $data = $request->getAllBody();
        $result = self::service()->createAccount($data);
        return Response::created($result);
    }

    public static function getAccount(Request $request): Response
    {
        $accountId = (string) $request->getParam('accountId');
        $row = self::service()->getAccount($accountId);
        if (!$row) {
            return Response::error(404, 'NOT_FOUND', "Account {$accountId} not found");
        }
        return Response::ok($row);
    }

    public static function placeOrder(Request $request): Response
    {
        $accountId = (string) $request->getParam('accountId');
        $data = $request->getAllBody();
        $result = self::service()->placeOrder($accountId, $data);
        return Response::created($result);
    }

    public static function cancelOrder(Request $request): Response
    {
        $accountId = (string) $request->getParam('accountId');
        $orderId = (string) $request->getParam('orderId');
        $result = self::service()->cancelOrder($accountId, $orderId);
        return Response::ok($result);
    }

    public static function listOrders(Request $request): Response
    {
        $accountId = (string) $request->getParam('accountId');
        $query = $request->getAllQuery();
        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = min(200, max(1, (int) ($query['per_page'] ?? 50)));
        $result = self::service()->listOrders($accountId, $page, $perPage);
        return Response::ok($result['data'], $result['meta']);
    }

    public static function getPositions(Request $request): Response
    {
        $accountId = (string) $request->getParam('accountId');
        return Response::ok(self::service()->getPositions($accountId));
    }

    public static function getSummary(Request $request): Response
    {
        $accountId = (string) $request->getParam('accountId');
        return Response::ok(self::service()->getSummary($accountId));
    }

    public static function validateSignal(Request $request): Response
    {
        $signalId = (string) $request->getParam('signalId');
        $accountId = (string) $request->getParam('accountId');
        return Response::ok(self::service()->validateSignal($signalId, $accountId));
    }
}
