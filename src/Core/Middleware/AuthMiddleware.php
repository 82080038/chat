<?php

declare(strict_types=1);

namespace Platform\Core\Middleware;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Platform\Core\Application;
use Platform\Core\Http\Request;
use Platform\Core\Http\Response;

final class AuthMiddleware
{
    public static function bearer(Request $request): ?Response
    {
        $authHeader = $request->getHeader('authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return Response::error(401, 'UNAUTHORIZED', 'Missing or invalid Authorization header');
        }

        $token = substr($authHeader, 7);
        try {
            $secret = Application::getInstance()->getConfig('JWT_SECRET', 'change-me');
            $decoded = JWT::decode($token, new Key($secret, 'HS256'));
            $request->setUserId($decoded->user_id ?? null);
            $request->setTenantId($decoded->tenant_id ?? null);
            $request->setUserPermissions($decoded->permissions ?? []);
        } catch (\Exception $e) {
            return Response::error(401, 'INVALID_TOKEN', 'Invalid or expired token');
        }
        return null;
    }

    public static function admin(Request $request): ?Response
    {
        $result = self::bearer($request);
        if ($result !== null) {
            return $result;
        }
        if (!$request->hasPermission('admin.full')) {
            return Response::error(403, 'FORBIDDEN', 'Admin access required');
        }
        return null;
    }

    public static function public(Request $request): ?Response
    {
        return null;
    }
}
