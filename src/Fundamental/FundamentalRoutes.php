<?php

declare(strict_types=1);

namespace Platform\Fundamental;

use Platform\Core\Application;
use Platform\Core\Exceptions\ApiException;
use Platform\Core\Http\Request;
use Platform\Core\Http\Response;
use Platform\Core\Http\Router;

final class FundamentalRoutes
{
    public static function register(Router $router): void
    {
        // Financial Statements
        $router->get('/financial-statements', [self::class, 'listStatements'], ['bearer']);
        $router->post('/financial-statements', [self::class, 'createStatement'], ['bearer']);
        $router->get('/financial-statements/{id}', [self::class, 'getStatement'], ['bearer']);
        $router->get('/financial-statements/{id}/lines', [self::class, 'statementLines'], ['bearer']);
        $router->get(
            '/financial-statements/{id}/revisions',
            [self::class, 'statementRevisions'],
            ['bearer']
        );
        $router->post(
            '/financial-statements/{id}/revise',
            [self::class, 'reviseStatement'],
            ['bearer']
        );

        // Financial Metrics
        $router->get('/financial-metrics', [self::class, 'listMetrics'], ['bearer']);
        $router->post('/financial-metrics', [self::class, 'createMetric'], ['bearer']);
        $router->get('/financial-metrics/{id}', [self::class, 'getMetric'], ['bearer']);
        $router->get('/issuers/{id}/metrics', [self::class, 'issuerMetrics'], ['bearer']);

        // Economic Indicators
        $router->get('/economic-indicators', [self::class, 'listIndicators'], ['bearer']);
        $router->post('/economic-indicators', [self::class, 'createIndicator'], ['bearer']);
        $router->get('/economic-indicators/{id}', [self::class, 'getIndicator'], ['bearer']);

        // News
        $router->get('/news', [self::class, 'listNews'], ['bearer']);
        $router->post('/news', [self::class, 'createNews'], ['bearer']);
        $router->get('/news/{id}', [self::class, 'getNews'], ['bearer']);
        $router->get('/instruments/{id}/news', [self::class, 'instrumentNews'], ['bearer']);
    }

    // ─── Financial Statements ────────────────────────────────────────────

    public static function listStatements(Request $request): Response
    {
        [$page, $perPage] = self::pagination($request);
        $result = self::service()->listFinancialStatements(
            self::statementFilters($request),
            $page,
            $perPage
        );
        return Response::ok($result['data'], $result['meta']);
    }

    public static function createStatement(Request $request): Response
    {
        return Response::created(
            self::service()->createFinancialStatement($request->getAllBody())
        );
    }

    public static function getStatement(Request $request): Response
    {
        $row = self::service()->getFinancialStatement((string) $request->getParam('id'));
        return Response::ok(
            self::required($row, 'FINANCIAL_STATEMENT_NOT_FOUND', 'Financial statement was not found')
        );
    }

    public static function statementLines(Request $request): Response
    {
        return Response::ok(
            self::service()->getFinancialStatementLines((string) $request->getParam('id'))
        );
    }

    public static function statementRevisions(Request $request): Response
    {
        return Response::ok(
            self::service()->getFinancialStatementRevisions((string) $request->getParam('id'))
        );
    }

    public static function reviseStatement(Request $request): Response
    {
        return Response::created(
            self::service()->reviseFinancialStatement(
                (string) $request->getParam('id'),
                $request->getAllBody()
            )
        );
    }

    // ─── Financial Metrics ───────────────────────────────────────────────

    public static function listMetrics(Request $request): Response
    {
        [$page, $perPage] = self::pagination($request);
        $result = self::service()->listFinancialMetrics(
            self::metricFilters($request),
            $page,
            $perPage
        );
        return Response::ok($result['data'], $result['meta']);
    }

    public static function createMetric(Request $request): Response
    {
        return Response::created(
            self::service()->createFinancialMetric($request->getAllBody())
        );
    }

    public static function getMetric(Request $request): Response
    {
        $row = self::service()->getFinancialMetric((string) $request->getParam('id'));
        return Response::ok(
            self::required($row, 'METRIC_NOT_FOUND', 'Financial metric was not found')
        );
    }

    public static function issuerMetrics(Request $request): Response
    {
        $rows = self::service()->getIssuerMetrics(
            (string) $request->getParam('id'),
            $request->getQuery('metric_type')
        );
        return Response::ok($rows);
    }

    // ─── Economic Indicators ─────────────────────────────────────────────

    public static function listIndicators(Request $request): Response
    {
        [$page, $perPage] = self::pagination($request);
        $result = self::service()->listEconomicIndicators(
            self::indicatorFilters($request),
            $page,
            $perPage
        );
        return Response::ok($result['data'], $result['meta']);
    }

    public static function createIndicator(Request $request): Response
    {
        return Response::created(
            self::service()->createEconomicIndicator($request->getAllBody())
        );
    }

    public static function getIndicator(Request $request): Response
    {
        $row = self::service()->getEconomicIndicator((string) $request->getParam('id'));
        return Response::ok(
            self::required($row, 'INDICATOR_NOT_FOUND', 'Economic indicator was not found')
        );
    }

    // ─── News ────────────────────────────────────────────────────────────

    public static function listNews(Request $request): Response
    {
        [$page, $perPage] = self::pagination($request);
        $result = self::service()->listNews(
            [
                'instrument_id' => $request->getQuery('instrument_id'),
                'sentiment' => $request->getQuery('sentiment'),
                'search' => $request->getQuery('search'),
            ],
            $page,
            $perPage
        );
        return Response::ok($result['data'], $result['meta']);
    }

    public static function createNews(Request $request): Response
    {
        return Response::created(
            self::service()->createNewsItem($request->getAllBody())
        );
    }

    public static function getNews(Request $request): Response
    {
        $row = self::service()->getNewsItem((string) $request->getParam('id'));
        return Response::ok(
            self::required($row, 'NEWS_NOT_FOUND', 'News item was not found')
        );
    }

    public static function instrumentNews(Request $request): Response
    {
        $limit = (int) $request->getQuery('limit', 20);
        return Response::ok(
            self::service()->getNewsByInstrument((string) $request->getParam('id'), $limit)
        );
    }

    // ─── Private Helpers ─────────────────────────────────────────────────

    private static function service(): FundamentalServiceInterface
    {
        $service = Application::getInstance()->getService('fundamental');
        if (!$service instanceof FundamentalServiceInterface) {
            throw new ApiException(
                503,
                'FUNDAMENTAL_UNAVAILABLE',
                'Fundamental service is unavailable'
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

    private static function statementFilters(Request $request): array
    {
        return [
            'issuer_id' => $request->getQuery('filter[issuer_id]'),
            'statement_type' => $request->getQuery('filter[statement_type]'),
            'fiscal_year' => $request->getQuery('filter[fiscal_year]'),
            'status' => $request->getQuery('filter[status]'),
        ];
    }

    private static function metricFilters(Request $request): array
    {
        return [
            'issuer_id' => $request->getQuery('filter[issuer_id]'),
            'metric_type' => $request->getQuery('filter[metric_type]'),
            'fiscal_year' => $request->getQuery('filter[fiscal_year]'),
        ];
    }

    private static function indicatorFilters(Request $request): array
    {
        return [
            'country' => $request->getQuery('filter[country]'),
            'indicator_type' => $request->getQuery('filter[indicator_type]'),
            'period' => $request->getQuery('filter[period]'),
        ];
    }
}
