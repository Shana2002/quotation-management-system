<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Router
 *
 * Minimal regex-based router supporting path parameters ({id}, {token}) and
 * per-route middleware. Routes are registered with their HTTP method, then
 * dispatch() resolves the current request to a controller action.
 */
final class Router
{
    /** @var array<int,array{method:string,pattern:string,regex:string,params:string[],handler:array,middleware:array}> */
    private array $routes = [];

    /**
     * Register a route.
     *
     * @param array{0:class-string,1:string} $handler [ControllerClass, method]
     * @param string[]                       $middleware
     */
    public function add(string $method, string $path, array $handler, array $middleware = []): void
    {
        $params = [];
        // Convert /quotations/{id}/pdf into a regex with named captures.
        $regex = preg_replace_callback('/\{([a-zA-Z_]+)\}/', function ($m) use (&$params) {
            $params[] = $m[1];
            return '([^/]+)';
        }, $path);

        $this->routes[] = [
            'method'     => strtoupper($method),
            'pattern'    => $path,
            'regex'      => '#^' . $regex . '$#',
            'params'     => $params,
            'handler'    => $handler,
            'middleware' => $middleware,
        ];
    }

    public function get(string $path, array $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, array $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    /**
     * Match the request and invoke the resolved controller action.
     * Returns false when no route matches (404).
     */
    public function dispatch(Request $request): bool
    {
        $method = $request->method();
        $path   = $request->path();

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            if (preg_match($route['regex'], $path, $matches) !== 1) {
                continue;
            }

            // Build named parameters from captured groups.
            $args = [];
            foreach ($route['params'] as $i => $name) {
                $args[$name] = $matches[$i + 1] ?? null;
            }

            // Run middleware; any of them may halt the request (redirect/abort).
            foreach ($route['middleware'] as $middleware) {
                $this->runMiddleware($middleware);
            }

            [$class, $action] = $route['handler'];
            if (!class_exists($class)) {
                throw new RuntimeException("Controller not found: {$class}");
            }

            $controller = new $class();
            if (!method_exists($controller, $action)) {
                throw new RuntimeException("Action not found: {$class}::{$action}");
            }

            // Pass route params as ordered arguments.
            $controller->{$action}(...array_values($args));

            return true;
        }

        return false;
    }

    /**
     * Resolve and execute a middleware token.
     *
     * Tokens:
     *   'auth'            -> require authentication
     *   'role:admin'      -> require one of the comma-separated roles
     */
    private function runMiddleware(string $token): void
    {
        [$name, $param] = array_pad(explode(':', $token, 2), 2, null);

        switch ($name) {
            case 'auth':
                (new \App\Middleware\AuthMiddleware())->handle();
                break;
            case 'role':
                (new \App\Middleware\RoleMiddleware())->handle((string) $param);
                break;
            default:
                throw new RuntimeException("Unknown middleware: {$token}");
        }
    }
}
