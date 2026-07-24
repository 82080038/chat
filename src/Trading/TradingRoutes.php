<?php

declare(strict_types=1);

namespace Platform\Trading;

use Platform\Core\Application;
use Platform\Core\Exceptions\ApiException;
use Platform\Core\Http\Request;
use Platform\Core\Http\Response;
use Platform\Core\Http\Router;

final class TradingRoutes
{
    public static function register(Router $router): void
    {
        // Brokers
        $router->get('/brokers', [self::class, 'listBrokers'], ['bearer']);
        $router->post('/brokers', [self::class, 'createBroker'], ['bearer']);
        $router->get('/brokers/{id}', [self::class, 'getBroker'], ['bearer']);
        $router->put('/brokers/{id}', [self::class, 'updateBroker'], ['bearer']);

        // Decisions
        $router->get('/decisions', [self::class, 'listDecisions'], ['bearer']);
        $router->post('/decisions', [self::class, 'createDecision'], ['bearer']);
        $router->get('/decisions/{id}', [self::class, 'getDecision'], ['bearer']);
        $router->post('/decisions/{id}/approve', [self::class, 'approveDecision'], ['bearer']);
        $router->post('/decisions/{id}/reject', [self::class, 'rejectDecision'], ['bearer']);
        $router->post('/decisions/{id}/override', [self::class, 'overrideDecision'], ['bearer']);

        // Order Intents
        $router->get('/order-intents', [self::class, 'listOrderIntents'], ['bearer']);
        $router->post('/order-intents', [self::class, 'createOrderIntent'], ['bearer']);
        $router->get('/order-intents/{id}', [self::class, 'getOrderIntent'], ['bearer']);
        $router->post('/order-intents/{id}/approve', [self::class, 'approveOrderIntent'], ['bearer']);
        $router->post('/order-intents/{id}/reject', [self::class, 'rejectOrderIntent'], ['bearer']);

        // Orders
        $router->get('/orders', [self::class, 'listOrders'], ['bearer']);
        $router->post('/orders', [self::class, 'submitOrder'], ['bearer']);
        $router->get('/orders/{id}', [self::class, 'getOrder'], ['bearer']);
        $router->post('/orders/{id}/cancel', [self::class, 'cancelOrder'], ['bearer']);
        $router->patch('/orders/{id}', [self::class, 'modifyOrder'], ['bearer']);
        $router->get('/orders/{id}/executions', [self::class, 'orderExecutions'], ['bearer']);
        $router->post('/orders/duplicate-check', [self::class, 'checkDuplicateOrder'], ['bearer']);

        // Executions
        $router->get('/executions', [self::class, 'listExecutions'], ['bearer']);
        $router->get('/executions/{id}', [self::class, 'getExecution'], ['bearer']);
    }

    // ─── Brokers ─────────────────────────────────────────────────────────

    public static function listBrokers(Request $request): Response
    {
        [$page, $perPage] = self::pagination($request);
        $result = self::service()->listBrokers(
            ['status' => $request->getQuery('status')],
            $page,
            $perPage
        );
        return Response::ok($result['data'], $result['meta']);
    }

    public static function createBroker(Request $request): Response
    {
        return Response::created(self::service()->createBroker($request->getAllBody()));
    }

    public static function getBroker(Request $request): Response
    {
        $row = self::service()->getBroker((string) $request->getParam('id'));
        return Response::ok(self::required($row, 'BROKER_NOT_FOUND', 'Broker was not found'));
    }

    public static function updateBroker(Request $request): Response
    {
        return Response::ok(
            self::service()->updateBroker((string) $request->getParam('id'), $request->getAllBody())
        );
    }

    // ─── Decisions ───────────────────────────────────────────────────────

    public static function listDecisions(Request $request): Response
    {
        [$page, $perPage] = self::pagination($request);
        $result = self::service()->listDecisions(
            [
                'portfolio_id' => $request->getQuery('filter[portfolio_id]'),
                'status' => $request->getQuery('filter[status]'),
                'instrument_id' => $request->getQuery('filter[instrument_id]'),
            ],
            $page,
            $perPage
        );
        return Response::ok($result['data'], $result['meta']);
    }

    public static function createDecision(Request $request): Response
    {
        return Response::created(self::service()->createDecision($request->getAllBody()));
    }

    public static function getDecision(Request $request): Response
    {
        $row = self::service()->getDecision((string) $request->getParam('id'));
        return Response::ok(self::required($row, 'DECISION_NOT_FOUND', 'Decision was not found'));
    }

    public static function approveDecision(Request $request): Response
    {
        return Response::ok(self::service()->approveDecision((string) $request->getParam('id')));
    }

    public static function rejectDecision(Request $request): Response
    {
        $reason = (string) $request->getBody('reason', 'Rejected by owner');
        return Response::ok(
            self::service()->rejectDecision((string) $request->getParam('id'), $reason)
        );
    }

    public static function overrideDecision(Request $request): Response
    {
        $reason = (string) $request->getBody('override_reason', 'Manual override');
        return Response::ok(
            self::service()->overrideDecision((string) $request->getParam('id'), $reason)
        );
    }

    // ─── Order Intents ───────────────────────────────────────────────────

    public static function listOrderIntents(Request $request): Response
    {
        [$page, $perPage] = self::pagination($request);
        $result = self::service()->listOrderIntents(
            [
                'portfolio_id' => $request->getQuery('filter[portfolio_id]'),
                'status' => $request->getQuery('filter[status]'),
                'decision_id' => $request->getQuery('filter[decision_id]'),
            ],
            $page,
            $perPage
        );
        return Response::ok($result['data'], $result['meta']);
    }

    public static function createOrderIntent(Request $request): Response
    {
        return Response::created(self::service()->createOrderIntent($request->getAllBody()));
    }

    public static function getOrderIntent(Request $request): Response
    {
        $row = self::service()->getOrderIntent((string) $request->getParam('id'));
        return Response::ok(
            self::required($row, 'ORDER_INTENT_NOT_FOUND', 'Order intent was not found')
        );
    }

    public static function approveOrderIntent(Request $request): Response
    {
        return Response::ok(self::service()->approveOrderIntent((string) $request->getParam('id')));
    }

    public static function rejectOrderIntent(Request $request): Response
    {
        $reason = (string) $request->getBody('reason', 'Rejected by owner');
        return Response::ok(
            self::service()->rejectOrderIntent((string) $request->getParam('id'), $reason)
        );
    }

    // ─── Orders ──────────────────────────────────────────────────────────

    public static function listOrders(Request $request): Response
    {
        [$page, $perPage] = self::pagination($request);
        $result = self::service()->listOrders(
            [
                'portfolio_id' => $request->getQuery('filter[portfolio_id]'),
                'status' => $request->getQuery('filter[status]'),
                'instrument_id' => $request->getQuery('filter[instrument_id]'),
            ],
            $page,
            $perPage
        );
        return Response::ok($result['data'], $result['meta']);
    }

    public static function submitOrder(Request $request): Response
    {
        return Response::created(self::service()->submitOrder($request->getAllBody()));
    }

    public static function getOrder(Request $request): Response
    {
        $row = self::service()->getOrder((string) $request->getParam('id'));
        return Response::ok(self::required($row, 'ORDER_NOT_FOUND', 'Order was not found'));
    }

    public static function cancelOrder(Request $request): Response
    {
        $reason = (string) $request->getBody('reason', 'Cancelled by owner');
        return Response::ok(
            self::service()->cancelOrder((string) $request->getParam('id'), $reason)
        );
    }

    public static function modifyOrder(Request $request): Response
    {
        return Response::ok(
            self::service()->modifyOrder((string) $request->getParam('id'), $request->getAllBody())
        );
    }

    public static function checkDuplicateOrder(Request $request): Response
    {
        return Response::ok(
            self::service()->checkDuplicateOrder($request->getAllBody())
        );
    }

    public static function orderExecutions(Request $request): Response
    {
        return Response::ok(
            self::service()->getOrderExecutions((string) $request->getParam('id'))
        );
    }

    // ─── Executions ──────────────────────────────────────────────────────

    public static function listExecutions(Request $request): Response
    {
        [$page, $perPage] = self::pagination($request);
        $result = self::service()->listExecutions(
            [
                'order_id' => $request->getQuery('filter[order_id]'),
                'status' => $request->getQuery('filter[status]'),
                'instrument_id' => $request->getQuery('filter[instrument_id]'),
            ],
            $page,
            $perPage
        );
        return Response::ok($result['data'], $result['meta']);
    }

    public static function getExecution(Request $request): Response
    {
        $row = self::service()->getExecution((string) $request->getParam('id'));
        return Response::ok(
            self::required($row, 'EXECUTION_NOT_FOUND', 'Execution was not found')
        );
    }

    // ─── Private Helpers ─────────────────────────────────────────────────

    private static function service(): TradingServiceInterface
    {
        $service = Application::getInstance()->getService('trading');
        if (!$service instanceof TradingServiceInterface) {
            throw new ApiException(
                503,
                'TRADING_UNAVAILABLE',
                'Trading service is unavailable'
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
