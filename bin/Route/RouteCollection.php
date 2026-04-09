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
    /** @var Route|null 兜底路由 */
    private static ?Route $fallbackRoute = null;
    /** @var ResourceRegistrar|null */
    private static ?ResourceRegistrar $registrar = null;

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

        // 兜底路由
        if (self::$fallbackRoute !== null) {
            return self::$fallbackRoute;
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

    public static function action(string $method, string $path, mixed $action): Route
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
     * 注册命名路由
     */
    public static function registerNamedRoute(string $name, Route $route): void
    {
        self::$namedRoutes[$name] = $route;
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
     *
     * 支持属性：
     *   - prefix: 路径前缀
     *   - middleware: 中间件列表
     *   - middleware_group: 中间件组名
     *   - namespace: 控制器命名空间前缀
     *   - domain: 子域名约束
     */
    public static function group(array $attributes, \Closure $callback): void
    {
        $prefix = $attributes['prefix'] ?? '';
        $middleware = $attributes['middleware'] ?? [];
        $middlewareGroup = $attributes['middleware_group'] ?? null;
        $namespace = $attributes['namespace'] ?? '';
        $domain = $attributes['domain'] ?? '';

        // 记录当前路由数量
        $startIndex = count(self::$route);

        // 执行回调（只调用一次！）
        $callback();

        // 为组内新增的路由应用属性
        $routeCount = count(self::$route);
        for ($i = $startIndex; $i < $routeCount; $i++) {
            $route = self::$route[$i];

            // 应用前缀：需要更新静态/动态路由索引
            if ($prefix !== '') {
                $newPath = '/' . trim($prefix, '/') . '/' . trim($route->getPath(), '/');
                $newPath = '/' . trim($newPath, '/');

                // 更新静态路由索引
                $oldKey = $route->getMethod() . ':' . $route->getPath();
                if (isset(self::$staticRoutes[$oldKey])) {
                    unset(self::$staticRoutes[$oldKey]);
                    self::$staticRoutes[$route->getMethod() . ':' . $newPath] = $route;
                }

                // 更新路由路径
                $route->updatePath($newPath);
            }

            // 应用命名空间前缀
            if ($namespace !== '') {
                $action = $route->getAction();
                if (is_string($action) && !str_contains($action, '\\') && str_contains($action, '@')) {
                    $route->setAction($namespace . '\\' . $action);
                }
            }

            // 应用域名约束
            if ($domain !== '') {
                $route->setDomain($domain);
            }

            // 应用中间件
            if ($middleware !== []) {
                $middleware = is_array($middleware) ? $middleware : [$middleware];
                $route->middleware($middleware);
            }

            // 应用中间件组
            if ($middlewareGroup !== null) {
                $route->middlewareGroup($middlewareGroup);
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
        self::$fallbackRoute = null;
    }

    /**
     * 获取所有路由（用于调试）
     */
    public static function getRoutes(): array
    {
        return self::$route;
    }

    // ================================================================
    // RESTful 资源路由
    // ================================================================

    /**
     * 注册 RESTful 资源路由
     *
     * @return Route[]
     */
    public static function resource(string $name, string $controller, array $options = []): array
    {
        return self::getRegistrar()->register($name, $controller, $options);
    }

    /**
     * 注册 API 资源路由（无 create/edit）
     *
     * @return Route[]
     */
    public static function apiResource(string $name, string $controller, array $options = []): array
    {
        return self::getRegistrar()->apiRegister($name, $controller, $options);
    }

    /**
     * 获取 ResourceRegistrar 实例
     */
    public static function getRegistrar(): ResourceRegistrar
    {
        if (self::$registrar === null) {
            self::$registrar = new ResourceRegistrar();
        }
        return self::$registrar;
    }

    // ================================================================
    // 快捷路由
    // ================================================================

    /**
     * 注册兜底路由（无匹配时触发）
     */
    public static function fallback(mixed $action): Route
    {
        $route = new Route('GET', '{fallback}', $action);
        self::$fallbackRoute = $route;
        return $route;
    }

    /**
     * 注册重定向路由
     */
    public static function redirect(string $path, string $destination, int $status = 302): Route
    {
        return self::action('GET', $path, function () use ($destination, $status) {
            header("Location: {$destination}", true, $status);
            exit;
        });
    }

    /**
     * 注册永久重定向路由
     */
    public static function permanentRedirect(string $path, string $destination): Route
    {
        return self::redirect($path, $destination, 301);
    }

    /**
     * 注册返回视图的路由
     */
    public static function view(string $path, string $viewName, array $data = []): Route
    {
        return self::action('GET', $path, function () use ($viewName, $data) {
            $v = \Bin\View\View::make($viewName);
            foreach ($data as $key => $value) {
                $v->with($key, $value);
            }
            return $v;
        });
    }

    /**
     * 根据名称查找路由
     */
    public static function namedRoute(string $name): ?Route
    {
        return self::$namedRoutes[$name] ?? null;
    }

    // ================================================================
    // 路由模型绑定
    // ================================================================

    /**
     * 注册模型绑定
     */
    public static function model(string $key, string $class, ?callable $callback = null): void
    {
        RouteBinding::model($key, $class, $callback);
    }

    /**
     * 注册自定义绑定解析器
     */
    public static function bind(string $key, callable $resolver): void
    {
        RouteBinding::bind($key, $resolver);
    }
}