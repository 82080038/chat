<?php

declare(strict_types=1);

namespace Platform\MarketData;

use Platform\Core\Application;
use Platform\Core\Exceptions\ApiException;
use Platform\Core\Http\Request;
use Platform\Core\Http\Response;
use Platform\Core\Http\Router;
use Platform\Core\Http\StreamResponse;

final class RealTimeDataRoutes
{
    public static function register(Router $router): void
    {
        $router->get('/market-data/quotes/{symbol}', [self::class, 'quote'], ['bearer']);
        $router->get('/api/market-data/stream', [self::class, 'stream'], ['bearer']);
    }

    public static function quote(Request $request): Response
    {
        $symbol = $request->getParam('symbol');
        if ($symbol === null || trim($symbol) === '') {
            throw new ApiException(422, 'VALIDATION_ERROR', 'Symbol is required');
        }

        $maxAge = (int) $request->getQuery('max_age', 60);
        if ($maxAge < 1) {
            $maxAge = 60;
        }

        $service = self::service();
        $quote = $service->getQuote($symbol, $maxAge);

        return Response::ok($quote);
    }

    public static function stream(Request $request): Response
    {
        $symbol = $request->getQuery('symbol');
        if ($symbol === null || trim($symbol) === '') {
            throw new ApiException(422, 'VALIDATION_ERROR', 'Symbol is required');
        }

        $service = self::service();
        $symbol = strtoupper(trim($symbol));

        return new StreamResponse(function (StreamResponse $response) use ($service, $symbol): void {
            $lastPrice = null;
            $lastEventAt = 0;
            $heartbeatEvery = $response->getHeartbeatSeconds();

            $response->event('connected', ['symbol' => $symbol, 'ts' => date('c')]);

            while ($response->isClientConnected()) {
                $quote = $service->getCachedQuote($symbol);

                if ($quote !== null && $quote['price'] !== $lastPrice) {
                    $lastPrice = $quote['price'];
                    $lastEventAt = time();
                    $response->event('quote', $quote);
                }

                if ((time() - $lastEventAt) >= $heartbeatEvery) {
                    $lastEventAt = time();
                    $response->comment('heartbeat ' . date('c'));
                }

                sleep(1);
            }
        });
    }

    private static function service(): RealTimeDataService
    {
        $service = Application::getInstance()->getService('realtime_data');
        if (!$service instanceof RealTimeDataService) {
            throw new ApiException(503, 'SERVICE_UNAVAILABLE', 'Real-time data service is unavailable');
        }
        return $service;
    }
}
