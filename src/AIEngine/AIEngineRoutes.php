<?php

declare(strict_types=1);

namespace Platform\AIEngine;

use Platform\Core\Application;
use Platform\Core\Exceptions\ApiException;
use Platform\Core\Http\Request;
use Platform\Core\Http\Response;
use Platform\Core\Http\Router;

final class AIEngineRoutes
{
    public static function register(Router $router): void
    {
        $router->post('/ai/sentiment', [self::class, 'analyzeSentiment'], ['bearer']);
        $router->post('/ai/pattern', [self::class, 'recognizePattern'], ['bearer']);
        $router->post('/ai/anomaly', [self::class, 'detectAnomaly'], ['bearer']);
        $router->get('/ai/analyses', [self::class, 'listAnalyses'], ['bearer']);
        $router->get('/ai/analyses/{id}', [self::class, 'getAnalysis'], ['bearer']);
        $router->post('/ai/model-runs', [self::class, 'createModelRun'], ['bearer']);
        $router->patch('/ai/model-runs/{id}', [self::class, 'updateModelRun'], ['bearer']);
    }

    private static function service(): AIEngineService
    {
        $app = Application::getInstance();
        $service = $app->getService('ai_engine');
        if (!$service instanceof AIEngineService) {
            throw new ApiException(
                503,
                'AI_ENGINE_UNAVAILABLE',
                'AI engine service is unavailable'
            );
        }
        return $service;
    }

    public static function analyzeSentiment(Request $request): Response
    {
        $data = $request->getAllBody();
        $result = self::service()->analyzeSentiment($data);
        return Response::created($result);
    }

    public static function recognizePattern(Request $request): Response
    {
        $data = $request->getAllBody();
        $result = self::service()->recognizePattern($data);
        return Response::created($result);
    }

    public static function detectAnomaly(Request $request): Response
    {
        $data = $request->getAllBody();
        $result = self::service()->detectAnomaly($data);
        return Response::created($result);
    }

    public static function getAnalysis(Request $request): Response
    {
        $id = (string) $request->getParam('id');
        $row = self::service()->getAnalysis($id);
        if (!$row) {
            return Response::error(404, 'NOT_FOUND', "Analysis {$id} not found");
        }
        return Response::ok($row);
    }

    public static function listAnalyses(Request $request): Response
    {
        $query = $request->getAllQuery();
        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = min(200, max(1, (int) ($query['per_page'] ?? 50)));
        $filters = [];
        if (isset($query['analysis_type'])) {
            $filters['analysis_type'] = $query['analysis_type'];
        }
        if (isset($query['instrument_id'])) {
            $filters['instrument_id'] = $query['instrument_id'];
        }
        $result = self::service()->listAnalyses($filters, $page, $perPage);
        return Response::ok($result['data'], $result['meta']);
    }

    public static function createModelRun(Request $request): Response
    {
        $data = $request->getAllBody();
        $result = self::service()->createModelRun($data);
        return Response::created($result);
    }

    public static function updateModelRun(Request $request): Response
    {
        $id = (string) $request->getParam('id');
        $data = $request->getAllBody();
        $result = self::service()->updateModelRun($id, $data);
        return Response::ok($result);
    }
}
