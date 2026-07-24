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
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return Response::error(401, 'UNAUTHORIZED', 'Missing or invalid Authorization header');
        }

        $identity = Application::getInstance()->getService('identity');
        if (!$identity instanceof IdentityServiceInterface) {
            return Response::error(503, 'IDENTITY_UNAVAILABLE', 'Identity service is unavailable');
        }

        $claims = $identity->verifyAccessToken(substr($authHeader, 7));
        $request->setOwnerId((string) $claims['owner_id']);
        $request->setAccessJti((string) $claims['jti']);
        return null;
    }

    public static function public(Request $request): ?Response
    {
        return null;
    }
}
