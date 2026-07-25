<?php

declare(strict_types=1);

namespace Platform\Core\Http;

use Platform\Config\ConfigServiceInterface;
use Platform\Core\Application;
use Platform\Core\Exceptions\ApiException;
use Throwable;

final class Router
{
    private array $routes = [];
    private array $middleware = [];

    public function addRoute(string $method, string $pattern, callable $handler, array $middleware = []): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $this->compilePattern($pattern),
            'rawPattern' => $pattern,
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    public function get(string $pattern, callable $handler, array $middleware = []): void
    {
        $this->addRoute('GET', $pattern, $handler, $middleware);
    }

    public function post(string $pattern, callable $handler, array $middleware = []): void
    {
        $this->addRoute('POST', $pattern, $handler, $middleware);
    }

    public function put(string $pattern, callable $handler, array $middleware = []): void
    {
        $this->addRoute('PUT', $pattern, $handler, $middleware);
    }

    public function delete(string $pattern, callable $handler, array $middleware = []): void
    {
        $this->addRoute('DELETE', $pattern, $handler, $middleware);
    }

    public function patch(string $pattern, callable $handler, array $middleware = []): void
    {
        $this->addRoute('PATCH', $pattern, $handler, $middleware);
    }

    public function addMiddleware(string $name, callable $middleware): void
    {
        $this->middleware[$name] = $middleware;
    }

    public function dispatch(Request $request): void
    {
        $path = $request->getPath();
        $method = $request->getMethod();

        // Strip /api/v1 prefix
        $prefix = '/api/v1';
        if (str_starts_with($path, $prefix)) {
            $path = substr($path, strlen($prefix));
        }
        if ($path === '') {
            $path = '/';
        }

        // Generate or reuse correlation ID for request traceability
        $correlationId = $request->getHeader('x-correlation-id');
        if ($correlationId === null) {
            $correlationId = \Ramsey\Uuid\Uuid::uuid7()->toString();
        }
        $request->setCorrelationId($correlationId);

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (preg_match($route['pattern'], $path, $matches)) {
                $startedAt = microtime(true);
                $params = array_filter($matches, fn($key) => !is_int($key), ARRAY_FILTER_USE_KEY);
                $request->setParams($params);

                // Propagate correlation ID to ServiceHub
                \Platform\Core\ServiceHub::getInstance()->setCorrelationId($correlationId);

                try {
                    foreach ($route['middleware'] as $mwName) {
                        if (isset($this->middleware[$mwName])) {
                            $result = ($this->middleware[$mwName])($request);
                            if ($result instanceof Response) {
                                $this->applyRequestAttributes($result, $request);
                                $this->logAccess($request, $result, $startedAt);
                                $result->send();
                                return;
                            }
                        }
                    }

                    $response = ($route['handler'])($request);
                    if ($response instanceof Response) {
                        $this->applyRequestAttributes($response, $request);
                        $this->logAccess($request, $response, $startedAt);
                        $response->send();
                        return;
                    }
                } catch (ApiException $exception) {
                    $response = Response::error(
                        $exception->getStatusCode(),
                        $exception->getErrorCode(),
                        $exception->getMessage(),
                        $exception->getFieldErrors(),
                        $request->getCorrelationId()
                    );
                    $this->applyRequestAttributes($response, $request);
                    $this->logAccess($request, $response, $startedAt);
                    $response->send();
                    return;
                } catch (Throwable) {
                    $response = Response::error(
                        500,
                        'INTERNAL_ERROR',
                        'An unexpected error occurred',
                        [],
                        $request->getCorrelationId()
                    );
                    $this->applyRequestAttributes($response, $request);
                    $this->logAccess($request, $response, $startedAt);
                    $response->send();
                    return;
                }
            }
        }

        Response::error(404, 'NOT_FOUND', "No route found for {$method} {$path}")->send();
    }

    private function applyRequestAttributes(Response $response, Request $request): void
    {
        foreach ($request->getAttributes() as $name => $value) {
            if (is_string($value)) {
                $response->addHeader($name, $value);
            }
        }
    }

    private function compilePattern(string $pattern): string
    {
        $regex = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $pattern);
        return '#^' . $regex . '$#';
    }

    private function logAccess(Request $request, Response $response, float $startedAt): void
    {
        try {
            $service = Application::getInstance()->getService('config');
            if (!$service instanceof ConfigServiceInterface) {
                return;
            }
            $service->logApiAccess([
                'endpoint' => $request->getPath(),
                'method' => $request->getMethod(),
                'status_code' => $response->getStatusCode(),
                'response_time_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'request_size' => isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : null,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $request->getHeader('user-agent'),
                'correlation_id' => $request->getHeader('x-correlation-id'),
            ]);
        } catch (Throwable) {
        }
    }
}
