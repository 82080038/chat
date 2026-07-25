<?php

declare(strict_types=1);

namespace Platform\Core\Middleware;

use Platform\Core\Application;
use Platform\Core\Http\Request;
use Platform\Core\Http\Response;
use Platform\Identity\IdentityServiceInterface;

final class AuthMiddleware
{
    public static function bearer(Request $request): ?Response
    {
        $authHeader = $request->getHeader('authorization');
        $token = null;

        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
        } elseif (isset($_COOKIE['access_token']) && $_COOKIE['access_token'] !== '') {
            $token = $_COOKIE['access_token'];
        }

        if ($token === null) {
            return Response::error(401, 'UNAUTHORIZED', 'Missing or invalid Authorization header');
        }

        $identity = Application::getInstance()->getService('identity');
        if (!$identity instanceof IdentityServiceInterface) {
            return Response::error(503, 'IDENTITY_UNAVAILABLE', 'Identity service is unavailable');
        }

        $claims = $identity->verifyAccessToken($token);
        $request->setOwnerId((string) $claims['owner_id']);
        $request->setAccessJti((string) $claims['jti']);
        return null;
    }

    public static function public(Request $request): ?Response
    {
        return null;
    }
}
