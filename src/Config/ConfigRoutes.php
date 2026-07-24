<?php

declare(strict_types=1);

namespace Platform\Config;

use Platform\Core\Application;
use Platform\Core\Exceptions\ApiException;
use Platform\Core\Http\Request;
use Platform\Core\Http\Response;
use Platform\Core\Http\Router;

final class ConfigRoutes
{
    public static function register(Router $router): void
    {
        $router->get('/configurations', [self::class, 'listConfigurations'], ['bearer']);
        $router->post('/configurations', [self::class, 'createConfiguration'], ['bearer']);
        $router->get('/configurations/key/{key}', [self::class, 'getConfigurationByKey'], ['bearer']);
        $router->get('/configurations/{id}', [self::class, 'getConfiguration'], ['bearer']);
        $router->put('/configurations/{id}', [self::class, 'updateConfiguration'], ['bearer']);

        $router->get('/feature-flags', [self::class, 'listFeatureFlags'], ['bearer']);
        $router->post('/feature-flags', [self::class, 'createFeatureFlag'], ['bearer']);
        $router->get('/feature-flags/key/{key}', [self::class, 'getFeatureFlagByKey'], ['bearer']);
        $router->get('/feature-flags/{id}', [self::class, 'getFeatureFlag'], ['bearer']);
        $router->put('/feature-flags/{id}', [self::class, 'updateFeatureFlag'], ['bearer']);

        $router->get('/system-parameters', [self::class, 'listSystemParameters'], ['bearer']);
        $router->get('/system-parameters/{key}', [self::class, 'getSystemParameter'], ['bearer']);
        $router->put('/system-parameters/{key}', [self::class, 'updateSystemParameter'], ['bearer']);

        $router->get('/storage-objects', [self::class, 'listStorageObjects'], ['bearer']);
        $router->post('/storage-objects', [self::class, 'registerStorageObject'], ['bearer']);
        $router->get('/storage-objects/{id}', [self::class, 'getStorageObject'], ['bearer']);
        $router->delete('/storage-objects/{id}', [self::class, 'deleteStorageObject'], ['bearer']);

        $router->get('/api-access-logs', [self::class, 'listApiAccessLogs'], ['bearer']);
        $router->get('/owner-activity-logs', [self::class, 'listOwnerActivityLogs'], ['bearer']);
    }

    public static function listConfigurations(Request $request): Response
    {
        [$page, $perPage] = self::pagination($request);
        $result = self::service()->listConfigurations([
            'category' => $request->getQuery('category'),
            'status' => $request->getQuery('status'),
        ], $page, $perPage);
        return Response::ok($result['data'], $result['meta']);
    }

    public static function createConfiguration(Request $request): Response
    {
        return Response::created(self::service()->createConfiguration($request->getAllBody()));
    }

    public static function getConfigurationByKey(Request $request): Response
    {
        $row = self::service()->getConfig((string) $request->getParam('key'));
        return Response::ok(self::required($row, 'CONFIG_NOT_FOUND', 'Configuration was not found'));
    }

    public static function getConfiguration(Request $request): Response
    {
        $row = self::service()->getConfiguration((string) $request->getParam('id'));
        return Response::ok(self::required($row, 'CONFIG_NOT_FOUND', 'Configuration was not found'));
    }

    public static function updateConfiguration(Request $request): Response
    {
        return Response::ok(self::service()->updateConfiguration(
            (string) $request->getParam('id'),
            $request->getAllBody()
        ));
    }

    public static function listFeatureFlags(Request $request): Response
    {
        [$page, $perPage] = self::pagination($request);
        $result = self::service()->listFeatureFlags($page, $perPage);
        return Response::ok($result['data'], $result['meta']);
    }

    public static function createFeatureFlag(Request $request): Response
    {
        return Response::created(self::service()->createFeatureFlag($request->getAllBody()));
    }

    public static function getFeatureFlagByKey(Request $request): Response
    {
        $row = self::service()->getFeatureFlagByKey((string) $request->getParam('key'));
        return Response::ok(self::required($row, 'FEATURE_FLAG_NOT_FOUND', 'Feature flag was not found'));
    }

    public static function getFeatureFlag(Request $request): Response
    {
        $row = self::service()->getFeatureFlag((string) $request->getParam('id'));
        return Response::ok(self::required($row, 'FEATURE_FLAG_NOT_FOUND', 'Feature flag was not found'));
    }

    public static function updateFeatureFlag(Request $request): Response
    {
        return Response::ok(self::service()->updateFeatureFlag(
            (string) $request->getParam('id'),
            $request->getAllBody()
        ));
    }

    public static function listSystemParameters(Request $request): Response
    {
        [$page, $perPage] = self::pagination($request);
        $result = self::service()->listSystemParameters($page, $perPage);
        return Response::ok($result['data'], $result['meta']);
    }

    public static function getSystemParameter(Request $request): Response
    {
        $row = self::service()->getSystemParameter((string) $request->getParam('key'));
        return Response::ok(self::required($row, 'PARAMETER_NOT_FOUND', 'System parameter was not found'));
    }

    public static function updateSystemParameter(Request $request): Response
    {
        return Response::ok(self::service()->updateSystemParameter(
            (string) $request->getParam('key'),
            $request->getBody('value')
        ));
    }

    public static function listStorageObjects(Request $request): Response
    {
        [$page, $perPage] = self::pagination($request);
        $result = self::service()->listStorageObjects($page, $perPage);
        return Response::ok($result['data'], $result['meta']);
    }

    public static function registerStorageObject(Request $request): Response
    {
        return Response::created(self::service()->registerStorageObject($request->getAllBody()));
    }

    public static function getStorageObject(Request $request): Response
    {
        $row = self::service()->getStorageObject((string) $request->getParam('id'));
        return Response::ok(self::required($row, 'STORAGE_OBJECT_NOT_FOUND', 'Storage object was not found'));
    }

    public static function deleteStorageObject(Request $request): Response
    {
        self::service()->softDeleteStorageObject((string) $request->getParam('id'));
        return Response::noContent();
    }

    public static function listApiAccessLogs(Request $request): Response
    {
        [$page, $perPage] = self::pagination($request);
        $result = self::service()->listApiAccessLogs($page, $perPage);
        return Response::ok($result['data'], $result['meta']);
    }

    public static function listOwnerActivityLogs(Request $request): Response
    {
        [$page, $perPage] = self::pagination($request);
        $result = self::service()->listOwnerActivityLogs($page, $perPage);
        return Response::ok($result['data'], $result['meta']);
    }

    private static function service(): ConfigServiceInterface
    {
        $service = Application::getInstance()->getService('config');
        if (!$service instanceof ConfigServiceInterface) {
            throw new ApiException(503, 'CONFIG_UNAVAILABLE', 'Configuration service is unavailable');
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
