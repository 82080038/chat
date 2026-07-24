<?php

declare(strict_types=1);

namespace Platform\Core\Http;

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

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (preg_match($route['pattern'], $path, $matches)) {
                $params = array_filter($matches, fn($key) => !is_int($key), ARRAY_FILTER_USE_KEY);
                $request->setParams($params);

                // Run middleware
                foreach ($route['middleware'] as $mwName) {
                    if (isset($this->middleware[$mwName])) {
                        $result = ($this->middleware[$mwName])($request);
                        if ($result instanceof Response) {
                            $result->send();
                            return;
                        }
                    }
                }

                $response = ($route['handler'])($request);
                if ($response instanceof Response) {
                    $response->send();
                    return;
                }
            }
        }

        Response::error(404, 'NOT_FOUND', "No route found for {$method} {$path}")->send();
    }

    private function compilePattern(string $pattern): string
    {
        $regex = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $pattern);
        return '#^' . $regex . '$#';
    }
}
