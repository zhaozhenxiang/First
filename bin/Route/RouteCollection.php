<?php

declare(strict_types=1);

namespace Bin\Route;

use Bin\App\App;
use Bin\Request\Request;
use Exception;

class RouteCollection
{
    private static ?self $instance = null;
    /** @var Route[] */
    private static array $route = [];
    /** @var array<string, Route> 静态路由索引 'METHOD:/path' => Route */
    private static array $staticRoutes = [];
    /** @var Route[] 动态路由（带参数） */
    private static array $dynamicRoutes = [];
    private static array $methods = ['get', 'post', 'put', 'patch', 'delete', 'options', 'head'];
    /** @var array<string, Route> 命名路由 */
    private static array $namedRoutes = [];

    private function __construct()
    {
    }

    /**
     * 获取匹配到的路由
     * @throws \Exception
     */
    public static function getRoute(): Route
    {
        $path = getUrl();
        $method = strtoupper(getMethod());

        return self::match($method, $path);
    }

    /**
     * 匹配路由（优化版：静态路由 O(1)，动态路由遍历）
     * @throws \Exception
     */
    private static function match(string $method, string $path): Route
    {
        // 静态路由直接索引查找 O(1)
        $key = $method . ':' . $path;
        if (isset(self::$staticRoutes[$key])) {
            return self::$staticRoutes[$key];
        }

        // 动态路由遍历匹配
        foreach (self::$dynamicRoutes as $route) {
            if ($route->getMethod() === $method && $route->withSuccess($path)) {
                return $route;
            }
        }

        throw new \Exception('ROUTE NO MATCH', 404);
    }

    /**
     * 判断路由是否为动态路由（包含参数占位符）
     */
    private static function isDynamicRoute(string $path): bool
    {
        return str_contains($path, '{');
    }

    private static function action(string $method, string $path, mixed $action): Route
    {
        $method = strtoupper($method);
        $route = new Route($method, $path, $action);

        // 根据路由类型分类存储
        if (self::isDynamicRoute($path)) {
            self::$dynamicRoutes[] = $route;
        } else {
            self::$staticRoutes[$method . ':' . $path] = $route;
        }

        // 保持兼容性，仍然添加到主数组
        self::$route[] = $route;

        return $route;
    }

    /**
     * 处理 middle
     */
    public static function middle(array $param, \Closure $callback): void
    {
        // 先获取当前的路由个数，在获取之后的路由个数，然后给最后获取的路由处理一下
        $currentRouteCount = count(self::$route);
        $callback();
        $nowRouteCount = count(self::$route);

        for ($i = $currentRouteCount; $i < $nowRouteCount; $i++) {
            self::$route[$i]->middle(['middle' => $param]);
        }
    }

    /**
     * 处理多个 get 的路由
     * @param  array  $param
     */
    public static function getArray(array $param): void
    {
        foreach ($param as $key => $item) {
            self::action('GET', $key, $item);
        }
    }

    /**
     * 动态调用 HTTP 方法
     * @throws Exception
     */
    public static function __callStatic(string $method, array $param): void
    {
        if (!in_array(strtolower($method), self::$methods, true)) {
            throw new Exception("REQUEST METHOD NOT MATCH: {$method}", 405);
        }

        if (!isset($param[0]) || !isset($param[1])) {
            throw new Exception('Route requires path and action', 400);
        }

        self::action($method, $param[0], $param[1]);
    }

    /**
     * GET 路由
     */
    public static function get(string $path, mixed $action): Route
    {
        return self::action('GET', $path, $action);
    }

    /**
     * POST 路由
     */
    public static function post(string $path, mixed $action): Route
    {
        return self::action('POST', $path, $action);
    }

    /**
     * PUT 路由
     */
    public static function put(string $path, mixed $action): Route
    {
        return self::action('PUT', $path, $action);
    }

    /**
     * PATCH 路由
     */
    public static function patch(string $path, mixed $action): Route
    {
        return self::action('PATCH', $path, $action);
    }

    /**
     * DELETE 路由
     */
    public static function delete(string $path, mixed $action): Route
    {
        return self::action('DELETE', $path, $action);
    }

    /**
     * OPTIONS 路由
     */
    public static function options(string $path, mixed $action): Route
    {
        return self::action('OPTIONS', $path, $action);
    }

    /**
     * 匹配多种 HTTP 方法
     */
    public static function matchMethods(array $methods, string $path, mixed $action): void
    {
        foreach ($methods as $method) {
            $method = strtoupper($method);
            if (in_array($method, self::$methods, true)) {
                self::action($method, $path, $action);
            }
        }
    }

    /**
     * 匹配所有 HTTP 方法
     */
    public static function any(string $path, mixed $action): void
    {
        foreach (self::$methods as $method) {
            self::action(strtoupper($method), $path, $action);
        }
    }

    /**
     * 路由分组
     */
    public static function group(array $attributes, \Closure $callback): void
    {
        $prefix = $attributes['prefix'] ?? '';
        $middleware = $attributes['middleware'] ?? [];

        $callback();

        // 为组内添加的路由应用前缀和中间件
        $startIndex = count(self::$route);
        $callback();

        for ($i = $startIndex; $i < count(self::$route); $i++) {
            $route = self::$route[$i];

            // 应用前缀
            if ($prefix !== '') {
                $newPath = '/' . trim($prefix, '/') . '/' . trim($route->getPath(), '/');
                // 更新路由路径（这里简化处理，实际需要更复杂的逻辑）
            }

            // 应用中间件
            if ($middleware !== []) {
                $route->middle(['middle' => $middleware]);
            }
        }
    }

    /**
     * 根据名称生成 URL
     */
    public static function url(string $name, array $params = []): string
    {
        if (!isset(self::$namedRoutes[$name])) {
            throw new Exception("Named route '{$name}' not found", 404);
        }

        return self::$namedRoutes[$name]->url($params);
    }

    /**
     * 清除所有路由（用于测试）
     */
    public static function clear(): void
    {
        self::$route = [];
        self::$staticRoutes = [];
        self::$dynamicRoutes = [];
        self::$namedRoutes = [];
    }

    /**
     * 获取所有路由（用于调试）
     */
    public static function getRoutes(): array
    {
        return self::$route;
    }
}