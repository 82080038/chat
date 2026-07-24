<?php

declare(strict_types=1);

namespace Platform\Valuation;

use Platform\Core\Application;
use Platform\Core\Exceptions\ApiException;
use Platform\Core\Http\Request;
use Platform\Core\Http\Response;
use Platform\Core\Http\Router;

final class ValuationRoutes
{
    public static function register(Router $router): void
    {
        $router->post('/valuations', [self::class, 'createValuation'], ['bearer']);
        $router->get('/valuations', [self::class, 'listValuations'], ['bearer']);
        $router->get('/valuations/{id}', [self::class, 'getValuation'], ['bearer']);
        $router->get(
            '/valuations/instrument/{instrumentId}',
            [self::class, 'getInstrumentValuations'],
            ['bearer']
        );
        $router->post('/valuations/dcf', [self::class, 'calculateDcf'], ['bearer']);
        $router->post('/valuations/relative', [self::class, 'calculateRelative'], ['bearer']);
        $router->post('/valuations/fair-value', [self::class, 'calculateFairValue'], ['bearer']);
    }

    private static function service(): ValuationService
    {
        $app = Application::getInstance();
        $service = $app->getService('valuation');
        if (!$service instanceof ValuationService) {
            throw new ApiException(
                503,
                'VALUATION_UNAVAILABLE',
                'Valuation service is unavailable'
            );
        }
        return $service;
    }

    public static function createValuation(Request $request): Response
    {
        $data = $request->getAllBody();
        $result = self::service()->createValuation($data);
        return Response::created($result);
    }

    public static function getValuation(Request $request): Response
    {
        $id = (string) $request->getParam('id');
        $row = self::service()->getValuation($id);
        if (!$row) {
            return Response::error(404, 'NOT_FOUND', "Valuation {$id} not found");
        }
        return Response::ok($row);
    }

    public static function listValuations(Request $request): Response
    {
        $query = $request->getAllQuery();
        [$page, $perPage] = self::parsePage($query);
        $filters = self::extractFilters(
            $query,
            ['instrument_id', 'valuation_type']
        );
        $result = self::service()->listValuations($filters, $page, $perPage);
        return Response::ok($result['data'], $result['meta']);
    }

    public static function getInstrumentValuations(Request $request): Response
    {
        $instrumentId = (string) $request->getParam('instrumentId');
        $rows = self::service()->getInstrumentValuations($instrumentId);
        return Response::ok($rows);
    }

    public static function calculateDcf(Request $request): Response
    {
        $data = $request->getAllBody();
        $result = self::service()->calculateDcf($data);
        return Response::ok($result);
    }

    public static function calculateRelative(Request $request): Response
    {
        $data = $request->getAllBody();
        $result = self::service()->calculateRelative($data);
        return Response::ok($result);
    }

    public static function calculateFairValue(Request $request): Response
    {
        $data = $request->getAllBody();
        $result = self::service()->calculateFairValue($data);
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
