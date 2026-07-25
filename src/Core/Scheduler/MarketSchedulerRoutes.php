<?php

declare(strict_types=1);

namespace Platform\Core\Scheduler;

use Platform\Core\Http\Request;
use Platform\Core\Http\Response;
use Platform\Core\Http\Router;

final class MarketSchedulerRoutes
{
    public static function register(Router $router): void
    {
        $router->get('/market-scheduler/status', [self::class, 'status'], ['bearer']);
        $router->get('/market-scheduler/schedule', [self::class, 'schedule'], ['bearer']);
        $router->post('/market-scheduler/run', [self::class, 'runDueTasks'], ['bearer']);
        $router->post('/market-scheduler/run/{taskId}', [self::class, 'runTask'], ['bearer']);
    }

    public static function status(Request $request): Response
    {
        $scheduler = new MarketScheduler();
        return Response::ok($scheduler->getStatus());
    }

    public static function schedule(Request $request): Response
    {
        $scheduler = new MarketScheduler();
        return Response::ok($scheduler->getSchedule());
    }

    public static function runDueTasks(Request $request): Response
    {
        $scheduler = new MarketScheduler();
        return Response::ok($scheduler->runDueTasks());
    }

    public static function runTask(Request $request): Response
    {
        $taskId = (string) $request->getParam('taskId');
        $scheduler = new MarketScheduler();
        return Response::ok($scheduler->runTask($taskId));
    }
}
