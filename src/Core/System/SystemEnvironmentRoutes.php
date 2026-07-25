<?php

declare(strict_types=1);

namespace Platform\Core\System;

use Platform\Core\Http\Request;
use Platform\Core\Http\Response;
use Platform\Core\Http\Router;

final class SystemEnvironmentRoutes
{
    public static function register(Router $router): void
    {
        $router->get('/system/environment', [self::class, 'environment'], ['bearer']);
        $router->get('/system/capabilities', [self::class, 'capabilities'], ['bearer']);
    }

    public static function environment(Request $request): Response
    {
        $env = SystemEnvironment::getInstance();
        return Response::ok($env->detect());
    }

    public static function capabilities(Request $request): Response
    {
        $env = SystemEnvironment::getInstance();
        return Response::ok($env->getCapabilities());
    }
}
