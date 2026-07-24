<?php

declare(strict_types=1);

namespace Platform\Alert;

use Platform\Core\Application;
use Platform\Core\Exceptions\ApiException;
use Platform\Core\Http\Request;
use Platform\Core\Http\Response;
use Platform\Core\Http\Router;

final class AlertRoutes
{
    public static function register(Router $router): void
    {
        $router->post('/alerts', [self::class, 'createAlert'], ['bearer']);
        $router->get('/alerts', [self::class, 'listAlerts'], ['bearer']);
        $router->get('/alerts/{id}', [self::class, 'getAlert'], ['bearer']);
        $router->put('/alerts/{id}', [self::class, 'updateAlert'], ['bearer']);
        $router->delete('/alerts/{id}', [self::class, 'deleteAlert'], ['bearer']);
        $router->post('/alerts/{id}/trigger', [self::class, 'triggerAlert'], ['bearer']);
        $router->post(
            '/alerts/check-price/{instrumentId}',
            [self::class, 'checkPriceAlert'],
            ['bearer']
        );
        $router->get('/alerts/notifications', [self::class, 'listNotifications'], ['bearer']);
        $router->post(
            '/alerts/notifications/{notificationId}/acknowledge',
            [self::class, 'acknowledgeNotification'],
            ['bearer']
        );
    }

    private static function service(): AlertService
    {
        $app = Application::getInstance();
        $service = $app->getService('alert');
        if (!$service instanceof AlertService) {
            throw new ApiException(
                503,
                'ALERT_UNAVAILABLE',
                'Alert service is unavailable'
            );
        }
        return $service;
    }

    public static function createAlert(Request $request): Response
    {
        $data = $request->getAllBody();
        $result = self::service()->createAlert($data);
        return Response::created($result);
    }

    public static function getAlert(Request $request): Response
    {
        $id = (string) $request->getParam('id');
        $row = self::service()->getAlert($id);
        if (!$row) {
            return Response::error(404, 'NOT_FOUND', "Alert {$id} not found");
        }
        return Response::ok($row);
    }

    public static function listAlerts(Request $request): Response
    {
        $query = $request->getAllQuery();
        [$page, $perPage] = self::parsePage($query);
        $filters = self::extractFilters(
            $query,
            ['alert_type', 'is_active', 'instrument_id', 'portfolio_id']
        );
        $result = self::service()->listAlerts($filters, $page, $perPage);
        return Response::ok($result['data'], $result['meta']);
    }

    public static function updateAlert(Request $request): Response
    {
        $id = (string) $request->getParam('id');
        $data = $request->getAllBody();
        $result = self::service()->updateAlert($id, $data);
        return Response::ok($result);
    }

    public static function deleteAlert(Request $request): Response
    {
        $id = (string) $request->getParam('id');
        $result = self::service()->deleteAlert($id);
        return Response::ok($result);
    }

    public static function triggerAlert(Request $request): Response
    {
        $id = (string) $request->getParam('id');
        $context = $request->getAllBody();
        $result = self::service()->triggerAlert($id, $context);
        return Response::ok($result);
    }

    public static function checkPriceAlert(Request $request): Response
    {
        $instrumentId = (string) $request->getParam('instrumentId');
        $price = (float) $request->getBody('current_price', 0);
        $result = self::service()->checkPriceAlert($instrumentId, $price);
        return Response::ok($result);
    }

    public static function listNotifications(Request $request): Response
    {
        $query = $request->getAllQuery();
        [$page, $perPage] = self::parsePage($query);
        $filters = self::extractFilters($query, ['alert_id', 'status']);
        $result = self::service()->listNotifications($filters, $page, $perPage);
        return Response::ok($result['data'], $result['meta']);
    }

    public static function acknowledgeNotification(Request $request): Response
    {
        $notificationId = (string) $request->getParam('notificationId');
        $result = self::service()->acknowledgeNotification($notificationId);
        return Response::ok($result);
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
