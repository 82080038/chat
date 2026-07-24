<?php

declare(strict_types=1);

namespace Platform\DataIngestion;

use Platform\Core\Application;
use Platform\Core\Exceptions\ApiException;
use Platform\Core\Http\Request;
use Platform\Core\Http\Response;
use Platform\Core\Http\Router;

final class DataIngestionRoutes
{
    public static function register(Router $router): void
    {
        $router->post('/ingestion/ohlcv', [self::class, 'ingestOhlcv'], ['bearer']);
        $router->get('/ingestion/ohlcv', [self::class, 'listOhlcv'], ['bearer']);
        $router->get('/ingestion/ohlcv/{id}', [self::class, 'getOhlcv'], ['bearer']);
        $router->get(
            '/ingestion/ohlcv/instrument/{instrumentId}',
            [self::class, 'getOhlcvHistory'],
            ['bearer']
        );
        $router->get('/ingestion/status', [self::class, 'getIngestionStatus'], ['bearer']);
    }

    private static function service(): DataIngestionService
    {
        $app = Application::getInstance();
        $service = $app->getService('data_ingestion');
        if (!$service instanceof DataIngestionService) {
            throw new ApiException(
                503,
                'DATA_INGESTION_UNAVAILABLE',
                'Data Ingestion service is unavailable'
            );
        }
        return $service;
    }

    public static function ingestOhlcv(Request $request): Response
    {
        $data = $request->getAllBody();
        $result = self::service()->ingestOhlcv($data);
        return Response::created($result);
    }

    public static function getOhlcv(Request $request): Response
    {
        $id = (string) $request->getParam('id');
        $row = self::service()->getOhlcv($id);
        if (!$row) {
            return Response::error(404, 'NOT_FOUND', "OHLCV record {$id} not found");
        }
        return Response::ok($row);
    }

    public static function listOhlcv(Request $request): Response
    {
        $query = $request->getAllQuery();
        [$page, $perPage] = self::parsePage($query);
        $filters = self::extractFilters(
            $query,
            ['instrument_id', 'source', 'from_date', 'to_date']
        );
        $result = self::service()->listOhlcv($filters, $page, $perPage);
        return Response::ok($result['data'], $result['meta']);
    }

    public static function getOhlcvHistory(Request $request): Response
    {
        $instrumentId = (string) $request->getParam('instrumentId');
        $query = $request->getAllQuery();
        $fromDate = isset($query['from_date']) ? (string) $query['from_date'] : null;
        $toDate = isset($query['to_date']) ? (string) $query['to_date'] : null;
        $rows = self::service()->getOhlcvHistory($instrumentId, $fromDate, $toDate);
        return Response::ok($rows);
    }

    public static function getIngestionStatus(Request $request): Response
    {
        return Response::ok(self::service()->getIngestionStatus());
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
