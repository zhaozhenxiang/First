<?php

declare(strict_types=1);

namespace Bin\Route;

use Bin\App\App;
use Bin\Middleware\MiddlewareNameResolver;
use Bin\Middleware\MiddlewareStack;
use Bin\Middleware\Pipeline;
use Bin\Request\Request;
use Bin\Routing\ControllerDispatcher;

class RouteAction
{
    /** @var Pipeline|null 最近一次执行使用的 Pipeline（用于 terminate） */
    private static ?Pipeline $lastPipeline = null;

    /** @var ControllerDispatcher|null 控制器调度器 */
    private static ?ControllerDispatcher $dispatcher = null;

    private function __construct()
    {
    }

    /**
     * 获取控制器调度器
     */
    public static function getDispatcher(): ControllerDispatcher
    {
        if (static::$dispatcher === null) {
            $app = App::getInstance();
            static::$dispatcher = $app->make(ControllerDispatcher::class);
        }

        return static::$dispatcher;
    }

    /**
     * 设置控制器调度器（测试用）
     */
    public static function setDispatcher(ControllerDispatcher $dispatcher): void
    {
        static::$dispatcher = $dispatcher;
    }

    /**
     * 执行路由
     * @throws \Exception
     */
    public static function dispatch(Request $request): mixed
    {
        App::getInstance()->instance(Request::class, $request);

        $route = RouteCollection::getRoute();

        // 收集所有中间件（全局 + 组 + 路由指定 - 排除）
        $stack = MiddlewareStack::getInstance();
        $middleware = $stack->collectRouteMiddleware(
            $route->getMiddleware(),
            $route->getMiddlewareGroups(),
            $route->getExcludedMiddleware()
        );

        // 兼容旧 API：如果新 middleware 为空但旧 middle 有值
        if ($middleware === [] && $route->getMiddle() !== null) {
            $middleware = static::legacyMiddleware($route);
        }

        // 解析中间件别名和参数
        $aliases = $stack->getAliases();
        $resolved = MiddlewareNameResolver::resolveAll($middleware, $aliases);

        // 构建 Pipeline
        $pipeline = new Pipeline();
        $pipeline->send($request)
            ->through(static::buildMiddlewareInstances($resolved));

        // 保存引用用于 terminate
        static::$lastPipeline = $pipeline;

        return $pipeline->then(function () use ($route): mixed {
            return static::dispatchRoute($route);
        });
    }

    /**
     * 执行路由
     * @throws \Exception
     */
    public static function action(): mixed
    {
        return static::dispatch(Request::capture());
    }

    /**
     * 在响应发送后调用所有中间件的 terminate 方法
     *
     * @param mixed $request  请求对象
     * @param mixed $response 响应对象
     */
    public static function terminate(mixed $request, mixed $response): void
    {
        if (static::$lastPipeline !== null) {
            static::$lastPipeline->terminate($request, $response);
            static::$lastPipeline = null;
        }
    }

    /**
     * 分发到路由的 action
     *
     * 通过 ControllerDispatcher 统一调度，支持：
     * - 闭包 action：容器注入参数
     * - Controller@method 字符串：容器实例化 + 方法注入
     */
    private static function dispatchRoute(Route $route): mixed
    {
        $action = $route->getAction();
        $dispatcher = static::getDispatcher();

        return match (true) {
            is_callable($action) => $dispatcher->dispatchClosure($action, $route),
            is_string($action) => static::dispatchController($action, $route, $dispatcher),
            default => abort(404),
        };
    }

    /**
     * 调度控制器方法
     */
    private static function dispatchController(string $action, Route $route, ControllerDispatcher $dispatcher): mixed
    {
        [$class, $method] = explode('@', $action);

        // 检查是否已经有完整命名空间
        if (!str_contains($class, '\\')) {
            $fullClass = '\App\Controllers\\' . $class;
        } else {
            $fullClass = $class;
        }

        return $dispatcher->dispatch($fullClass, $method, $route);
    }

    /**
     * 兼容旧版中间件格式
     *
     * 旧格式：Route::middle(['middleware' => ['auth' => [...]]], callback)
     */
    private static function legacyMiddleware(Route $route): array
    {
        $middle = $route->getMiddle();
        if ($middle === null) {
            return [];
        }

        $result = [];
        foreach ($middle as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $name => $params) {
                    $result[] = is_string($name) ? $name : $params;
                }
            } else {
                $result[] = $value;
            }
        }

        return $result;
    }

    /**
     * 构建中间件实例数组
     *
     * 优先通过容器构建以支持构造函数依赖注入。
     * 如果容器无法构建，回退到直接实例化。
     *
     * @param  array<array{0: class-string, 1: array}>  $resolved
     */
    private static function buildMiddlewareInstances(array $resolved): array
    {
        $container = App::getInstance();
        $instances = [];

        foreach ($resolved as [$class, $parameters]) {
            try {
                $instance = $container->make($class);
            } catch (\Throwable) {
                $instance = new $class();
            }
            if ($parameters !== []) {
                $instance->setOptions($parameters);
            }
            $instances[] = $instance;
        }

        return $instances;
    }
}
