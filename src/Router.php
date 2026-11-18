<?php
declare(strict_types=1);

namespace AM;

/**
 * 极简路由
 *  - 支持 GET/POST 等方法
 *  - 支持参数占位符 {id}
 */
class Router
{
    /** @var array<string, array<string, array{handler:callable, middleware:array}>> */
    private array $routes = [];
    /** @var array */
    private array $globalMiddleware = [];

    public function get(string $path, callable $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }
    public function post(string $path, callable $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }
    public function put(string $path, callable $handler, array $middleware = []): void
    {
        $this->add('PUT', $path, $handler, $middleware);
    }
    public function delete(string $path, callable $handler, array $middleware = []): void
    {
        $this->add('DELETE', $path, $handler, $middleware);
    }
    public function any(array $methods, string $path, callable $handler, array $middleware = []): void
    {
        foreach ($methods as $m) {
            $this->add($m, $path, $handler, $middleware);
        }
    }

    private function add(string $method, string $path, callable $handler, array $middleware): void
    {
        $this->routes[$method][$path] = ['handler' => $handler, 'middleware' => $middleware];
    }

    public function dispatch(string $method, string $uri): void
    {
        $method = strtoupper($method);
        // 兼容表单 _method
        if ($method === 'POST' && !empty($_POST['_method'])) {
            $method = strtoupper($_POST['_method']);
        }
        $path = parse_url($uri, PHP_URL_PATH) ?? '/';
        $path = '/' . trim($path, '/');
        if ($path === '') {
            $path = '/';
        }

        $routes = $this->routes[$method] ?? [];
        foreach ($routes as $pattern => $route) {
            $params = [];
            if ($this->match($pattern, $path, $params)) {
                // 中间件链
                foreach ($route['middleware'] as $mw) {
                    $mw();
                }
                ($route['handler'])($params);
                return;
            }
        }

        http_response_code(404);
        Helper::view('errors/404');
    }

    private function match(string $pattern, string $path, array &$params): bool
    {
        if ($pattern === $path) {
            return true;
        }
        // 参数占位符
        $regex = preg_replace('#\{([a-zA-Z_]+)\}#', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';
        if (preg_match($regex, $path, $matches)) {
            foreach ($matches as $k => $v) {
                if (!is_int($k)) {
                    $params[$k] = $v;
                }
            }
            return true;
        }
        return false;
    }
}
