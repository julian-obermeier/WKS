<?php
declare(strict_types=1);

namespace WKS\Core;

use WKS\Middleware\AuthMiddleware;
use WKS\Middleware\ForcePasswordChangeMiddleware;
use WKS\Middleware\LocationMiddleware;
use WKS\Middleware\PermissionMiddleware;

final class Router
{
    private array $routes = [];

    public function get(string $path, array|callable $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, array|callable $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    private function add(string $method, string $path, array|callable $handler, array $middleware): void
    {
        $this->routes[] = compact('method', 'path', 'handler', 'middleware');
    }

    public function dispatch(Request $request): Response
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->method()) {
                continue;
            }

            $params = $this->match($route['path'], $request->path());
            if ($params === null) {
                continue;
            }

            $destination = function (Request $req) use ($route, $params): Response {
                $handler = $route['handler'];
                if (is_callable($handler)) {
                    return $handler($req, ...array_values($params));
                }

                [$class, $method] = $handler;
                return (new $class())->{$method}($req, ...array_values($params));
            };

            $pipeline = array_reduce(
                array_reverse($route['middleware']),
                function (callable $next, string $middleware): callable {
                    return function (Request $req) use ($middleware, $next): Response {
                        $instance = $this->resolveMiddleware($middleware);
                        return $instance->handle($req, $next);
                    };
                },
                $destination
            );

            return $pipeline($request);
        }

        throw new HttpException(404, 'Die angeforderte Seite wurde nicht gefunden.');
    }

    private function match(string $routePath, string $requestPath): ?array
    {
        $names = [];
        $pattern = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', static function (array $m) use (&$names): string {
            $names[] = $m[1];
            return '([^/]+)';
        }, $routePath);

        if ($pattern === null || !preg_match('#^' . $pattern . '$#', $requestPath, $matches)) {
            return null;
        }

        array_shift($matches);
        return array_combine($names, array_map('urldecode', $matches)) ?: [];
    }

    private function resolveMiddleware(string $definition): object
    {
        if ($definition === 'auth') {
            return new AuthMiddleware();
        }
        if ($definition === 'location') {
            return new LocationMiddleware();
        }
        if ($definition === 'password') {
            return new ForcePasswordChangeMiddleware();
        }
        if (str_starts_with($definition, 'permission:')) {
            return new PermissionMiddleware(substr($definition, 11));
        }

        throw new \RuntimeException('Unbekannte Middleware: ' . $definition);
    }
}
