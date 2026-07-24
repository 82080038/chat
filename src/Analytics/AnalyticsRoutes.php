<?php

declare(strict_types=1);

namespace Platform\Analytics;

use Platform\Core\Application;
use Platform\Core\Exceptions\ApiException;
use Platform\Core\Http\Request;
use Platform\Core\Http\Response;
use Platform\Core\Http\Router;

final class AnalyticsRoutes
{
    public static function register(Router $router): void
    {
        // Features
        $router->get('/features', [self::class, 'listFeatures'], ['bearer']);
        $router->post('/features', [self::class, 'createFeature'], ['bearer']);
        $router->get('/features/{id}', [self::class, 'getFeature'], ['bearer']);
        $router->put('/features/{id}', [self::class, 'updateFeature'], ['bearer']);
        $router->get('/features/{id}/values', [self::class, 'featureValues'], ['bearer']);
        $router->post('/features/{id}/values', [self::class, 'ingestFeatureValues'], ['bearer']);

        // Signals
        $router->get('/signals', [self::class, 'listSignals'], ['bearer']);
        $router->post('/signals', [self::class, 'createSignal'], ['bearer']);
        $router->get('/signals/{id}', [self::class, 'getSignal'], ['bearer']);
        $router->post('/signals/{id}/invalidate', [self::class, 'invalidateSignal'], ['bearer']);
        $router->get('/instruments/{id}/signals', [self::class, 'instrumentSignals'], ['bearer']);

        // Forecasts
        $router->get('/forecasts', [self::class, 'listForecasts'], ['bearer']);
        $router->post('/forecasts', [self::class, 'createForecast'], ['bearer']);
        $router->get('/forecasts/{id}', [self::class, 'getForecast'], ['bearer']);
        $router->get('/instruments/{id}/forecasts', [self::class, 'instrumentForecasts'], ['bearer']);

        // Recommendations
        $router->get('/recommendations', [self::class, 'listRecommendations'], ['bearer']);
        $router->post('/recommendations', [self::class, 'createRecommendation'], ['bearer']);
        $router->get('/recommendations/{id}', [self::class, 'getRecommendation'], ['bearer']);
        $router->get(
            '/instruments/{id}/recommendations',
            [self::class, 'instrumentRecommendations'],
            ['bearer']
        );

        // Scores
        $router->get('/scores', [self::class, 'listScores'], ['bearer']);
        $router->post('/scores', [self::class, 'createScore'], ['bearer']);
        $router->get('/scores/{id}', [self::class, 'getScore'], ['bearer']);
        $router->get('/instruments/{id}/scores', [self::class, 'instrumentScores'], ['bearer']);

        // Model Registry
        $router->get('/models', [self::class, 'listModels'], ['bearer']);
        $router->post('/models', [self::class, 'createModel'], ['bearer']);
        $router->get('/models/{id}', [self::class, 'getModel'], ['bearer']);
        $router->put('/models/{id}', [self::class, 'updateModel'], ['bearer']);

        // Backtests
        $router->get('/backtests', [self::class, 'listBacktests'], ['bearer']);
        $router->post('/backtests', [self::class, 'createBacktest'], ['bearer']);
        $router->get('/backtests/{id}', [self::class, 'getBacktest'], ['bearer']);
        $router->get('/backtests/{id}/status', [self::class, 'backtestStatus'], ['bearer']);
    }

    // ─── Features ────────────────────────────────────────────────────────

    public static function listFeatures(Request $request): Response
    {
        [$page, $perPage] = self::pagination($request);
        $result = self::service()->listFeatures(
            ['status' => $request->getQuery('status')],
            $page,
            $perPage
        );
        return Response::ok($result['data'], $result['meta']);
    }

    public static function createFeature(Request $request): Response
    {
        return Response::created(self::service()->createFeature($request->getAllBody()));
    }

    public static function getFeature(Request $request): Response
    {
        $row = self::service()->getFeature((string) $request->getParam('id'));
        return Response::ok(self::required($row, 'FEATURE_NOT_FOUND', 'Feature was not found'));
    }

    public static function updateFeature(Request $request): Response
    {
        return Response::ok(
            self::service()->updateFeature((string) $request->getParam('id'), $request->getAllBody())
        );
    }

    public static function featureValues(Request $request): Response
    {
        [$page, $perPage] = self::pagination($request);
        $result = self::service()->getFeatureValues(
            (string) $request->getParam('id'),
            [
                'instrument_id' => $request->getQuery('filter[instrument_id]'),
                'from' => $request->getQuery('filter[from]'),
                'to' => $request->getQuery('filter[to]'),
            ],
            $page,
            $perPage
        );
        return Response::ok($result['data'], $result['meta']);
    }

    public static function ingestFeatureValues(Request $request): Response
    {
        return Response::ok(
            self::service()->ingestFeatureValues(
                (string) $request->getParam('id'),
                $request->getAllBody()
            )
        );
    }

    // ─── Signals ─────────────────────────────────────────────────────────

    public static function listSignals(Request $request): Response
    {
        [$page, $perPage] = self::pagination($request);
        $result = self::service()->listSignals(
            [
                'instrument_id' => $request->getQuery('filter[instrument_id]'),
                'signal_type' => $request->getQuery('filter[signal_type]'),
                'direction' => $request->getQuery('filter[direction]'),
                'status' => $request->getQuery('filter[status]'),
            ],
            $page,
            $perPage
        );
        return Response::ok($result['data'], $result['meta']);
    }

    public static function createSignal(Request $request): Response
    {
        return Response::created(self::service()->createSignal($request->getAllBody()));
    }

    public static function getSignal(Request $request): Response
    {
        $row = self::service()->getSignal((string) $request->getParam('id'));
        return Response::ok(self::required($row, 'SIGNAL_NOT_FOUND', 'Signal was not found'));
    }

    public static function invalidateSignal(Request $request): Response
    {
        $reason = (string) $request->getBody('reason', 'Manual invalidation');
        return Response::ok(
            self::service()->invalidateSignal((string) $request->getParam('id'), $reason)
        );
    }

    public static function instrumentSignals(Request $request): Response
    {
        return Response::ok(
            self::service()->getActiveSignals((string) $request->getParam('id'))
        );
    }

    // ─── Forecasts ───────────────────────────────────────────────────────

    public static function listForecasts(Request $request): Response
    {
        [$page, $perPage] = self::pagination($request);
        $result = self::service()->listForecasts(
            [
                'instrument_id' => $request->getQuery('filter[instrument_id]'),
                'target_variable' => $request->getQuery('filter[target_variable]'),
                'model_version' => $request->getQuery('filter[model_version]'),
            ],
            $page,
            $perPage
        );
        return Response::ok($result['data'], $result['meta']);
    }

    public static function createForecast(Request $request): Response
    {
        return Response::created(self::service()->createForecast($request->getAllBody()));
    }

    public static function getForecast(Request $request): Response
    {
        $row = self::service()->getForecast((string) $request->getParam('id'));
        return Response::ok(self::required($row, 'FORECAST_NOT_FOUND', 'Forecast was not found'));
    }

    public static function instrumentForecasts(Request $request): Response
    {
        $targetVar = $request->getQuery('target_variable');
        if ($targetVar !== null) {
            $row = self::service()->getLatestForecast(
                (string) $request->getParam('id'),
                $targetVar
            );
            return Response::ok(self::required($row, 'FORECAST_NOT_FOUND', 'Forecast was not found'));
        }
        [$page, $perPage] = self::pagination($request);
        $result = self::service()->listForecasts(
            ['instrument_id' => (string) $request->getParam('id')],
            $page,
            $perPage
        );
        return Response::ok($result['data'], $result['meta']);
    }

    // ─── Recommendations ─────────────────────────────────────────────────

    public static function listRecommendations(Request $request): Response
    {
        [$page, $perPage] = self::pagination($request);
        $result = self::service()->listRecommendations(
            [
                'instrument_id' => $request->getQuery('filter[instrument_id]'),
                'action' => $request->getQuery('filter[action]'),
                'status' => $request->getQuery('filter[status]'),
                'min_confidence' => $request->getQuery('filter[min_confidence]'),
            ],
            $page,
            $perPage
        );
        return Response::ok($result['data'], $result['meta']);
    }

    public static function createRecommendation(Request $request): Response
    {
        return Response::created(self::service()->createRecommendation($request->getAllBody()));
    }

    public static function getRecommendation(Request $request): Response
    {
        $row = self::service()->getRecommendation((string) $request->getParam('id'));
        return Response::ok(
            self::required($row, 'RECOMMENDATION_NOT_FOUND', 'Recommendation was not found')
        );
    }

    public static function instrumentRecommendations(Request $request): Response
    {
        $row = self::service()->getLatestRecommendation((string) $request->getParam('id'));
        if ($row === null) {
            return Response::ok([]);
        }
        return Response::ok([$row]);
    }

    // ─── Scores ──────────────────────────────────────────────────────────

    public static function listScores(Request $request): Response
    {
        [$page, $perPage] = self::pagination($request);
        $result = self::service()->listScores(
            [
                'instrument_id' => $request->getQuery('filter[instrument_id]'),
                'score_type' => $request->getQuery('filter[score_type]'),
            ],
            $page,
            $perPage
        );
        return Response::ok($result['data'], $result['meta']);
    }

    public static function createScore(Request $request): Response
    {
        return Response::created(self::service()->createScore($request->getAllBody()));
    }

    public static function getScore(Request $request): Response
    {
        $row = self::service()->getScore((string) $request->getParam('id'));
        return Response::ok(self::required($row, 'SCORE_NOT_FOUND', 'Score was not found'));
    }

    public static function instrumentScores(Request $request): Response
    {
        return Response::ok(
            self::service()->getInstrumentScores(
                (string) $request->getParam('id'),
                $request->getQuery('score_type')
            )
        );
    }

    // ─── Model Registry ──────────────────────────────────────────────────

    public static function listModels(Request $request): Response
    {
        [$page, $perPage] = self::pagination($request);
        $result = self::service()->listModels(
            [
                'status' => $request->getQuery('status'),
                'model_type' => $request->getQuery('model_type'),
            ],
            $page,
            $perPage
        );
        return Response::ok($result['data'], $result['meta']);
    }

    public static function createModel(Request $request): Response
    {
        return Response::created(self::service()->createModel($request->getAllBody()));
    }

    public static function getModel(Request $request): Response
    {
        $row = self::service()->getModel((string) $request->getParam('id'));
        return Response::ok(self::required($row, 'MODEL_NOT_FOUND', 'Model was not found'));
    }

    public static function updateModel(Request $request): Response
    {
        return Response::ok(
            self::service()->updateModel((string) $request->getParam('id'), $request->getAllBody())
        );
    }

    // ─── Backtests ───────────────────────────────────────────────────────

    public static function listBacktests(Request $request): Response
    {
        [$page, $perPage] = self::pagination($request);
        $result = self::service()->listBacktests(
            [
                'strategy_name' => $request->getQuery('strategy_name'),
                'status' => $request->getQuery('status'),
            ],
            $page,
            $perPage
        );
        return Response::ok($result['data'], $result['meta']);
    }

    public static function createBacktest(Request $request): Response
    {
        return Response::created(self::service()->createBacktest($request->getAllBody()));
    }

    public static function getBacktest(Request $request): Response
    {
        $row = self::service()->getBacktest((string) $request->getParam('id'));
        return Response::ok(self::required($row, 'BACKTEST_NOT_FOUND', 'Backtest was not found'));
    }

    public static function backtestStatus(Request $request): Response
    {
        $row = self::service()->getBacktestStatus((string) $request->getParam('id'));
        return Response::ok(self::required($row, 'BACKTEST_NOT_FOUND', 'Backtest was not found'));
    }

    // ─── Private Helpers ─────────────────────────────────────────────────

    private static function service(): AnalyticsServiceInterface
    {
        $service = Application::getInstance()->getService('analytics');
        if (!$service instanceof AnalyticsServiceInterface) {
            throw new ApiException(
                503,
                'ANALYTICS_UNAVAILABLE',
                'Analytics service is unavailable'
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
