<?php

declare(strict_types=1);

namespace Bin\Route;

use Bin\App\App;
use Bin\Exception\MethodNotAllowedHttpException;
use Bin\Exception\NotFoundHttpException;
use Bin\Request\Request;
use Exception;
use RuntimeException;

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
    /** @var Route|null 当前匹配路由 */
    private static ?Route $currentRoute = null;
    /** @var ResourceRegistrar|null */
    private static ?ResourceRegistrar $registrar = null;

    /**
     * 路由组属性栈
     *
     * 嵌套 group 时，每层属性推入栈中。
     * - prefix: 按顺序拼接（/admin + /settings → /admin/settings）
     * - name: 按顺序拼接（admin. + settings. → admin.settings.）
     * - namespace: 按顺序拼接（App\Controllers + Admin → App\Controllers\Admin）
     * - controller: 子非空覆盖父（裸方法名组合为 Controller@method）
     * - domain: 最后定义的覆盖前面的
     * - where: 数组合并（外层约束可被内层覆盖）
     * - middleware: 累积（所有层级合并）
     *
     * @var array<int, array{prefix:string, name:string, namespace:string, controller:string, domain:string, where:array<string,string>, middleware:array<string>, middleware_group:string|array<int, string>|null}>
     */
    private static array $groupStack = [];

    private function __construct()
    {
    }

    /**
     * 获取匹配到的路由
     * @throws \Exception
     */
    public static function getRoute(): Route
    {
        // REQUEST_URI 含 query string，匹配只使用路径部分
        $path = parse_url(getUrl(), PHP_URL_PATH) ?: '/';
        $method = strtoupper(getMethod());

        return self::resolve($method, $path);
    }

    /**
     * 解析匹配路由（优化版：静态路由 O(1)，动态路由遍历）
     * @throws \Exception
     */
    private static function resolve(string $method, string $path): Route
    {
        self::$currentRoute = null;

        // 静态路由直接索引查找 O(1)
        $key = $method . ':' . $path;
        if (isset(self::$staticRoutes[$key])) {
            return self::setCurrentRoute(self::$staticRoutes[$key]);
        }

        // 动态路由遍历匹配
        foreach (self::$dynamicRoutes as $route) {
            if ($route->getMethod() === $method && $route->withSuccess($path)) {
                return self::setCurrentRoute($route);
            }
        }

        // HEAD 请求回退到 GET 路由（RFC 语义：HEAD 只取响应头）
        if ($method === 'HEAD') {
            return self::resolve('GET', $path);
        }

        // 兜底路由
        if (self::$fallbackRoute !== null) {
            return self::setCurrentRoute(self::$fallbackRoute);
        }

        // 路径存在但 HTTP 方法不匹配 → 405
        $allowed = self::allowedMethodsForPath($method, $path);
        if ($allowed !== []) {
            throw new MethodNotAllowedHttpException("Method not allowed: {$method}", $allowed);
        }

        throw new NotFoundHttpException('Route not found');
    }

    /**
     * 收集匹配路径的其他 HTTP 方法（用于 405 响应的 Allow 头）
     *
     * @param  string  $currentMethod  当前请求方法
     * @param  string  $path  请求路径
     * @return array<string>
     */
    private static function allowedMethodsForPath(string $currentMethod, string $path): array
    {
        $allowed = [];

        foreach (array_keys(self::$staticRoutes) as $key) {
            [$method, $routePath] = explode(':', $key, 2);
            if ($method !== $currentMethod && $routePath === $path) {
                $allowed[] = $method;
            }
        }

        foreach (self::$dynamicRoutes as $route) {
            if ($route->getMethod() !== $currentMethod && $route->matches($path)) {
                $allowed[] = $route->getMethod();
            }
        }

        return array_values(array_unique($allowed));
    }

    private static function setCurrentRoute(Route $route): Route
    {
        self::$currentRoute = $route;

        return $route;
    }

    /**
     * 判断路由是否为动态路由（包含参数占位符）
     */
    private static function isDynamicRoute(string $path): bool
    {
        return str_contains($path, '{');
    }

    /**
     * 注册路由（组感知：创建时立即应用组栈属性）
     *
     * 当路由在 group 内创建时，自动应用合并后的组属性：
     * prefix → 修改 path，namespace → 修改 action，domain/middleware/where → 设置到 Route
     */
    public static function action(string $method, string $path, mixed $action): Route
    {
        $method = strtoupper($method);

        // 获取当前组栈的合并属性
        $groupAttrs = self::$groupStack !== [] ? end(self::$groupStack) : null;

        // 应用前缀
        if ($groupAttrs !== null && $groupAttrs['prefix'] !== '') {
            $path = '/' . trim($groupAttrs['prefix'], '/') . '/' . trim($path, '/');
            $path = '/' . trim($path, '/');
        }

        // 应用命名空间前缀
        if ($groupAttrs !== null && $groupAttrs['controller'] !== '') {
            // 裸方法名（无 @、无命名空间分隔符）组合为 Controller@method
            if (is_string($action) && $action !== '' && !str_contains($action, '@') && !str_contains($action, '\\')) {
                $action = $groupAttrs['controller'] . '@' . $action;
            }
        }

        // 应用命名空间前缀
        if ($groupAttrs !== null && $groupAttrs['namespace'] !== '') {
            if (is_string($action) && !str_contains($action, '\\') && str_contains($action, '@')) {
                $action = $groupAttrs['namespace'] . '\\' . $action;
            }
        }

        $route = new Route($method, $path, $action);

        // 应用域名约束
        if ($groupAttrs !== null && $groupAttrs['domain'] !== '') {
            $route->setDomain($groupAttrs['domain']);
        }

        // 应用 where 约束
        if ($groupAttrs !== null && $groupAttrs['where'] !== []) {
            $route->mergeWheres($groupAttrs['where']);
        }

        // 应用中间件
        if ($groupAttrs !== null && $groupAttrs['middleware'] !== []) {
            $route->middleware($groupAttrs['middleware']);
        }

        // 应用中间件组
        if ($groupAttrs !== null && $groupAttrs['middleware_group'] !== null) {
            $route->middlewareGroup($groupAttrs['middleware_group']);
        }

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
     * 注册命名路由（自动应用组 name 前缀）
     */
    public static function registerNamedRoute(string $name, Route $route): void
    {
        // 应用组 name 前缀
        $prefix = self::currentGroupNamePrefix();
        $fullName = $prefix . $name;

        // 更新路由存储的名称
        $route->setRawName($fullName);

        self::$namedRoutes[$fullName] = $route;
    }

    /**
     * 获取当前组栈的 name 前缀
     */
    public static function currentGroupNamePrefix(): string
    {
        if (self::$groupStack === []) {
            return '';
        }
        return end(self::$groupStack)['name'] ?? '';
    }

    /**
     * 处理 middle（兼容旧 API）
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
            throw new \Bin\Exception\MethodNotAllowedHttpException("Method not allowed: {$method}");
        }

        if (!isset($param[0]) || !isset($param[1])) {
            throw new \Bin\Exception\HttpException(400, 'Route requires path and action');
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
     * 匹配多种 HTTP 方法（Laravel 风格别名）
     */
    public static function match(array $methods, string $path, mixed $action): void
    {
        static::matchMethods($methods, $path, $action);
    }

    /**
     * 匹配多种 HTTP 方法
     */
    public static function matchMethods(array $methods, string $path, mixed $action): void
    {
        foreach ($methods as $method) {
            $method = strtoupper($method);
            if (in_array(strtolower($method), self::$methods, true)) {
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
     * 路由分组（Laravel 风格组属性栈）
     *
     * 支持属性：
     *   - prefix: 路径前缀（拼接）
     *   - name: 路由名前缀（拼接，如 'admin.'）
     *   - middleware: 中间件列表（累积）
     *   - middleware_group: 中间件组名或列表（累积）
     *   - namespace: 控制器命名空间前缀（拼接）
     *   - controller: 组控制器（子覆盖父，裸方法名组合为 Controller@method）
     *   - domain: 子域名约束（覆盖，最后定义的生效）
     *   - where: 参数正则约束（合并）
     */
    public static function group(array $attributes, \Closure $callback): void
    {
        // 计算当前组属性（合并父组栈）
        $merged = static::mergeGroupAttributes($attributes);

        // 推入栈（路由创建时自动应用）
        self::$groupStack[] = $merged;

        // 执行回调（路由在 action() 中自动获得组属性）
        $callback();

        // 弹出栈
        array_pop(self::$groupStack);
    }

    /**
     * 合并组属性（与父栈合并）
     *
     * 合并规则：
     * - prefix: 父 + 子（用 / 拼接）
     * - name: 父 + 子（直接拼接）
     * - namespace: 父 + 子（用 \ 拼接）
     * - controller: 子覆盖父（非空则覆盖）
     * - domain: 子覆盖父（非空则覆盖）
     * - where: 数组合并（子覆盖同名 key）
     * - middleware: 数组合并（累积）
     * - middleware_group: 父 + 子（累积）
     */
    private static function mergeGroupAttributes(array $new): array
    {
        // 默认值
        $merged = [
            'prefix' => $new['prefix'] ?? '',
            'name' => $new['name'] ?? '',
            'namespace' => $new['namespace'] ?? '',
            'controller' => $new['controller'] ?? '',
            'domain' => $new['domain'] ?? '',
            'where' => $new['where'] ?? [],
            'middleware' => isset($new['middleware'])
                ? (is_array($new['middleware']) ? $new['middleware'] : [$new['middleware']])
                : [],
            'middleware_group' => isset($new['middleware_group'])
                ? (is_array($new['middleware_group']) ? $new['middleware_group'] : [$new['middleware_group']])
                : null,
        ];

        // 与父栈合并
        if (self::$groupStack !== []) {
            $parent = end(self::$groupStack);

            // prefix: 拼接
            $parentPrefix = $parent['prefix'] ?? '';
            if ($parentPrefix !== '') {
                $merged['prefix'] = '/' . trim($parentPrefix, '/') . '/' . trim($merged['prefix'], '/');
                $merged['prefix'] = '/' . trim($merged['prefix'], '/');
            }

            // name: 拼接
            $parentName = $parent['name'] ?? '';
            $merged['name'] = $parentName . $merged['name'];

            // namespace: 拼接
            $parentNs = $parent['namespace'] ?? '';
            if ($parentNs !== '') {
                if ($merged['namespace'] !== '') {
                    $merged['namespace'] = $parentNs . '\\' . $merged['namespace'];
                } else {
                    $merged['namespace'] = $parentNs;
                }
            }

            // domain: 子非空则覆盖，否则继承父
            if ($merged['domain'] === '') {
                $merged['domain'] = $parent['domain'] ?? '';
            }

            // controller: 子非空则覆盖，否则继承父
            if ($merged['controller'] === '') {
                $merged['controller'] = $parent['controller'] ?? '';
            }

            // where: 合并（子优先）
            $merged['where'] = array_merge($parent['where'] ?? [], $merged['where']);

            // middleware: 累积
            $parentMw = $parent['middleware'] ?? [];
            $merged['middleware'] = array_values(array_unique(array_merge($parentMw, $merged['middleware'])));

            // middleware_group: 累积
            $parentGroups = $parent['middleware_group'] ?? null;
            $parentGroups = $parentGroups === null ? [] : (is_array($parentGroups) ? $parentGroups : [$parentGroups]);
            $childGroups = $merged['middleware_group'] ?? null;
            $childGroups = $childGroups === null ? [] : (is_array($childGroups) ? $childGroups : [$childGroups]);
            $groups = array_values(array_unique(array_merge($parentGroups, $childGroups)));
            $merged['middleware_group'] = $groups === [] ? null : $groups;
        }

        return $merged;
    }

    /**
     * 根据名称生成 URL
     */
    public static function url(string $name, array $params = []): string
    {
        if (!isset(self::$namedRoutes[$name])) {
            throw new NotFoundHttpException("Named route '{$name}' not found");
        }

        return self::$namedRoutes[$name]->url($params);
    }

    public static function current(): ?Route
    {
        return self::$currentRoute;
    }

    public static function currentRouteName(): ?string
    {
        return self::$currentRoute?->getName();
    }

    public static function currentRouteAction(): mixed
    {
        return self::$currentRoute?->getAction();
    }

    /**
     * @return array<int, array{method: string, uri: string, name: string, action: string, middleware: string}>
     */
    public static function routeTable(): array
    {
        return array_map(static function (Route $route): array {
            $middleware = array_values(array_unique(array_merge(
                $route->getMiddleware(),
                $route->getMiddlewareGroups()
            )));

            return [
                'method' => $route->getMethod(),
                'uri' => $route->getPath(),
                'name' => $route->getName() ?? '',
                'action' => self::describeAction($route->getAction()),
                'middleware' => implode(', ', $middleware),
            ];
        }, self::$route);
    }

    private static function describeAction(mixed $action): string
    {
        if (is_string($action)) {
            return $action;
        }

        if ($action instanceof \Closure) {
            return 'Closure';
        }

        if (is_array($action)) {
            $target = $action[0] ?? '';
            $method = $action[1] ?? '';
            $class = is_object($target) ? $target::class : (string) $target;

            return $method !== '' ? $class . '@' . $method : $class;
        }

        if (is_object($action)) {
            return $action::class;
        }

        return get_debug_type($action);
    }

    /**
     * @return array{routes: array<int, array<string, mixed>>, fallback: array<string, mixed>|null}
     */
    public static function exportForCache(): array
    {
        return [
            'routes' => array_map(static fn (Route $route): array => self::exportRoute($route), self::$route),
            'fallback' => self::$fallbackRoute === null ? null : self::exportRoute(self::$fallbackRoute),
        ];
    }

    /**
     * @param array{routes?: array<int, array<string, mixed>>, fallback?: array<string, mixed>|null} $payload
     */
    public static function loadFromCache(array $payload): void
    {
        self::clear();

        foreach ($payload['routes'] ?? [] as $route) {
            self::restoreCachedRoute($route, false);
        }

        $fallback = $payload['fallback'] ?? null;

        if (is_array($fallback)) {
            self::restoreCachedRoute($fallback, true);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function exportRoute(Route $route): array
    {
        return [
            'method' => $route->getMethod(),
            'uri' => $route->getPath(),
            'action' => self::exportCacheableAction($route->getAction(), $route->getPath()),
            'name' => $route->getName(),
            'domain' => $route->getDomain(),
            'where' => $route->getWheres(),
            'preg' => $route->getPreg(),
            'middleware' => $route->getMiddleware(),
            'middleware_groups' => $route->getMiddlewareGroups(),
            'excluded_middleware' => $route->getExcludedMiddleware(),
            'scoped' => $route->getScoped(),
        ];
    }

    private static function exportCacheableAction(mixed $action, string $uri): string|array
    {
        if (is_string($action)) {
            return $action;
        }

        if (is_array($action)) {
            $target = $action[0] ?? null;
            $method = $action[1] ?? null;

            if (is_string($target) && is_string($method)) {
                return [$target, $method];
            }
        }

        throw new RuntimeException("Unable to cache route [{$uri}] because it uses a non-cacheable action.");
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function restoreCachedRoute(array $data, bool $fallback): void
    {
        $route = $fallback
            ? self::fallback($data['action'])
            : self::action((string) $data['method'], (string) $data['uri'], $data['action']);

        if (($data['domain'] ?? null) !== null) {
            $route->setDomain((string) $data['domain']);
        }

        if (($data['where'] ?? []) !== []) {
            $route->where($data['where']);
        }

        foreach ($data['preg'] ?? [] as $pattern) {
            $route->with((string) $pattern);
        }

        if (($data['middleware'] ?? []) !== []) {
            $route->middleware($data['middleware']);
        }

        if (($data['middleware_groups'] ?? []) !== []) {
            $route->middlewareGroup($data['middleware_groups']);
        }

        if (($data['excluded_middleware'] ?? []) !== []) {
            $route->withoutMiddleware($data['excluded_middleware']);
        }

        if (($data['scoped'] ?? []) !== []) {
            $route->scoped($data['scoped']);
        }

        $name = $data['name'] ?? null;

        if (is_string($name) && $name !== '') {
            $route->name($name);
        }
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
        self::$currentRoute = null;
        self::$groupStack = [];
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
     *
     * 兜底路由不添加到主路由数组或动态路由索引中，
     * 仅在静态路由和动态路由都无法匹配时作为最终回退。
     */
    public static function fallback(mixed $action): Route
    {
        $route = new Route('GET', '/', $action);
        self::$fallbackRoute = $route;
        return $route;
    }

    /**
     * 注册重定向路由
     */
    public static function redirect(string $path, string $destination, int $status = 302): Route
    {
        return self::action('GET', $path, function () use ($destination, $status) {
            return new \Bin\Response\Response('', $status, [
                'Location' => $destination,
            ]);
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
