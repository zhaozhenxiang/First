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
    private static array $method = [
        'get',
        'post',
    ];

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

    public static function __callStatic(string $method, array $param): void
    {
        if (!in_array(strtolower($method), self::$method, true)) {
            throw new Exception('REQUEST METHOD NOT MATCH', 1);
        }

        self::action($method, $param[0], $param[1]);
    }

    public static function get(string $path, mixed $param): Route
    {
        return self::action('GET', $path, $param);
    }

    public static function post(string $path, mixed $param): Route
    {
        return self::action('POST', $path, $param);
    }
}