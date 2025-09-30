<?php

namespace BusBooking\Core;

/**
 * Simple router for handling HTTP requests
 */
class Router
{
    private array $routes = [];
    private array $middleware = [];
    private array $routeMiddleware = [];

    public function addMiddleware(object $middleware): void
    {
        $this->middleware[] = $middleware;
    }

    public function get(string $path, $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }

    public function put(string $path, $handler): void
    {
        $this->addRoute('PUT', $path, $handler);
    }

    public function patch(string $path, $handler): void
    {
        $this->addRoute('PATCH', $path, $handler);
    }

    public function delete(string $path, $handler): void
    {
        $this->addRoute('DELETE', $path, $handler);
    }

    public function options(string $path, $handler): void
    {
        $this->addRoute('OPTIONS', $path, $handler);
    }

    public function group(string $prefix, array $middleware, callable $callback): void
    {
        $originalMiddleware = $this->routeMiddleware;
        $this->routeMiddleware = array_merge($this->routeMiddleware, $middleware);
        
        $originalRoutes = $this->routes;
        $callback($this);
        
        // Apply prefix to new routes
        $newRoutes = array_slice($this->routes, count($originalRoutes));
        foreach ($newRoutes as $key => $route) {
            $route['path'] = rtrim($prefix, '/') . $route['path'];
            $route['middleware'] = array_merge($route['middleware'], $middleware);
            $this->routes[$key] = $route;
        }
        
        $this->routeMiddleware = $originalMiddleware;
    }

    private function addRoute(string $method, string $path, $handler): void
    {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler,
            'middleware' => $this->routeMiddleware
        ];
    }

    public function handle(Request $request): array
    {
        $method = $request->getMethod();
        $uri = $request->getUri();

        // Handle OPTIONS requests for CORS
        if ($method === 'OPTIONS') {
            return ['message' => 'OK'];
        }

        // Apply global middleware
        foreach ($this->middleware as $middleware) {
            $result = $middleware->handle($request);
            if ($result !== null) {
                return $result;
            }
        }

        // Find matching route
        $matchedRoute = null;
        $params = [];

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $routeParams = $this->matchRoute($route['path'], $uri);
            if ($routeParams !== false) {
                $matchedRoute = $route;
                $params = $routeParams;
                break;
            }
        }

        if ($matchedRoute === null) {
            throw new \Exception('Route not found', 404);
        }

        // Apply route middleware
        foreach ($matchedRoute['middleware'] as $middlewareClass) {
            if (is_string($middlewareClass)) {
                $middleware = new $middlewareClass();
            } else {
                $middleware = $middlewareClass;
            }
            
            $result = $middleware->handle($request);
            if ($result !== null) {
                return $result;
            }
        }

        // Execute handler
        $handler = $matchedRoute['handler'];
        
        if (is_callable($handler)) {
            return $handler($request, $params);
        }

        if (is_array($handler) && count($handler) === 2) {
            [$controllerClass, $method] = $handler;
            $controller = new $controllerClass();
            return $controller->$method($request, $params);
        }

        throw new \Exception('Invalid route handler', 500);
    }

    private function matchRoute(string $routePath, string $requestPath): array|false
    {
        // Convert route path to regex
        $pattern = preg_replace('/\{([^}]+)\}/', '([^/]+)', $routePath);
        $pattern = '#^' . $pattern . '$#';

        if (!preg_match($pattern, $requestPath, $matches)) {
            return false;
        }

        // Extract parameter names
        preg_match_all('/\{([^}]+)\}/', $routePath, $paramNames);
        
        $params = [];
        for ($i = 1; $i < count($matches); $i++) {
            $paramName = $paramNames[1][$i - 1] ?? 'param' . $i;
            $params[$paramName] = $matches[$i];
        }

        return $params;
    }
}