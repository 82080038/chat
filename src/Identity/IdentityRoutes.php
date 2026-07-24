<?php

declare(strict_types=1);

namespace Platform\Identity;

use Platform\Core\Application;
use Platform\Core\Exceptions\ApiException;
use Platform\Core\Http\Request;
use Platform\Core\Http\Response;
use Platform\Core\Http\Router;

final class IdentityRoutes
{
    public static function register(Router $router): void
    {
        $router->post('/auth/setup', [self::class, 'setup']);
        $router->post('/auth/login', [self::class, 'login']);
        $router->post('/auth/refresh', [self::class, 'refresh']);
        $router->post('/auth/logout', [self::class, 'logout'], ['bearer']);
        $router->get('/auth/me', [self::class, 'me'], ['bearer']);
        $router->post('/auth/change-password', [self::class, 'changePassword'], ['bearer']);
        $router->get('/auth/preferences', [self::class, 'preferences'], ['bearer']);
        $router->put('/auth/preferences', [self::class, 'updatePreferences'], ['bearer']);
        $router->post('/auth/kill-switch', [self::class, 'activateKillSwitch'], ['bearer']);
        $router->delete('/auth/kill-switch', [self::class, 'deactivateKillSwitch'], ['bearer']);
        $router->get('/auth/kill-switch', [self::class, 'killSwitchStatus'], ['bearer']);
    }

    public static function setup(Request $request): Response
    {
        $owner = self::service()->setupOwner($request->getAllBody(), self::context($request));
        return Response::created($owner);
    }

    public static function login(Request $request): Response
    {
        $tokens = self::service()->authenticate(
            (string) $request->getBody('email', ''),
            (string) $request->getBody('password', ''),
            self::context($request)
        );
        return Response::ok($tokens);
    }

    public static function refresh(Request $request): Response
    {
        $tokens = self::service()->refresh(
            (string) $request->getBody('refresh_token', ''),
            self::context($request)
        );
        return Response::ok($tokens);
    }

    public static function logout(Request $request): Response
    {
        $jti = $request->getAccessJti();
        if ($jti === null) {
            throw new ApiException(401, 'INVALID_TOKEN', 'Owner session is missing');
        }
        self::service()->logout($jti, self::context($request));
        return Response::noContent();
    }

    public static function me(Request $request): Response
    {
        $owner = self::service()->getOwner();
        if ($owner === null || $owner['owner_id'] !== $request->getOwnerId()) {
            throw new ApiException(404, 'OWNER_NOT_FOUND', 'Owner account was not found');
        }
        return Response::ok($owner);
    }

    public static function changePassword(Request $request): Response
    {
        $ownerId = self::requiredOwnerId($request);
        self::service()->changePassword(
            $ownerId,
            (string) $request->getBody('current_password', ''),
            (string) $request->getBody('new_password', '')
        );
        return Response::noContent();
    }

    public static function preferences(Request $request): Response
    {
        return Response::ok(self::service()->getPreferences(self::requiredOwnerId($request)));
    }

    public static function updatePreferences(Request $request): Response
    {
        return Response::ok(self::service()->updatePreferences(
            self::requiredOwnerId($request),
            $request->getAllBody()
        ));
    }

    private static function service(): IdentityServiceInterface
    {
        $service = Application::getInstance()->getService('identity');
        if (!$service instanceof IdentityServiceInterface) {
            throw new ApiException(503, 'IDENTITY_UNAVAILABLE', 'Identity service is unavailable');
        }
        return $service;
    }

    private static function requiredOwnerId(Request $request): string
    {
        $ownerId = $request->getOwnerId();
        if ($ownerId === null) {
            throw new ApiException(401, 'UNAUTHORIZED', 'Owner authentication is required');
        }
        return $ownerId;
    }

    private static function context(Request $request): array
    {
        return [
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $request->getHeader('user-agent'),
            'correlation_id' => $request->getCorrelationId(),
        ];
    }

    public static function activateKillSwitch(Request $request): Response
    {
        $reason = (string) $request->getBody('reason', 'Emergency shutdown');
        $result = self::service()->activateKillSwitch($reason);
        return Response::ok($result);
    }

    public static function deactivateKillSwitch(Request $request): Response
    {
        $result = self::service()->deactivateKillSwitch();
        return Response::ok($result);
    }

    public static function killSwitchStatus(Request $request): Response
    {
        return Response::ok([
            'active' => self::service()->isKillSwitchActive(),
        ]);
    }
}
