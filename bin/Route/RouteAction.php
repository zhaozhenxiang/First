<?php

declare(strict_types=1);

namespace Bin\Route;

use Bin\Middleware\MiddlewareNameResolver;
use Bin\Middleware\MiddlewareStack;
use Bin\Middleware\Pipeline;
use Bin\Reflection\Reflection;
use Bin\Response\Response;
use Exception;

class RouteAction
{
    private function __construct()
    {
    }

    /**
     * 执行路由
     * @throws \Exception
     */
    public static function action(): mixed
    {
        $route = RouteCollection::getRoute();
        $request = \Bin\Request\Request::capture();

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
        return (new Pipeline())
            ->send($request)
            ->through(static::buildMiddlewareInstances($resolved))
            ->then(function () use ($route): mixed {
                return static::dispatch($route);
            });
    }

    /**
     * 分发到路由的 action
     * @throws \Exception
     */
    private static function dispatch(Route $route): mixed
    {
        $action = $route->getAction();

        $result = match (true) {
            is_callable($action) => static::doCallback($action),
            is_string($action) => static::doClassMethod($action, $route),
            default => abort(404)
        };

        return $result instanceof Response ? $result : new Response($result);
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
     * @param  array<array{0: class-string, 1: array}>  $resolved
     * @return array
     */
    private static function buildMiddlewareInstances(array $resolved): array
    {
        $instances = [];

        foreach ($resolved as [$class, $parameters]) {
            $instance = new $class();
            if ($parameters !== []) {
                $instance->setOptions($parameters);
            }
            $instances[] = $instance;
        }

        return $instances;
    }

    /**
     * 执行闭包回调
     */
    private static function doCallback(callable $action): mixed
    {
        $params = app(Reflection::class)->getCallBackParam($action);

        // 模型绑定：检测参数类型提示
        $params = static::resolveModelBindings($action, $params);

        return call_user_func_array($action, $params);
    }

    /**
     * 执行控制器方法
     * @throws \Exception
     */
    private static function doClassMethod(string $action, ?Route $route = null): Response
    {
        [$class, $method] = explode('@', $action);

        // 检查是否已经有完整命名空间
        if (!str_contains($class, '\\')) {
            $fullClass = '\App\Controllers\\' . $class;
        } else {
            $fullClass = $class;
        }

        $params = app(Reflection::class)->getClassMethodParamInject($fullClass, $method);
        $instance = new $fullClass();

        return new Response(call_user_func_array([$instance, $method], $params));
    }

    /**
     * 解析模型绑定参数
     */
    private static function resolveModelBindings(callable $action, array $params): array
    {
        $reflection = new \ReflectionFunction($action instanceof \Closure ? $action : \Closure::fromCallable($action));
        $request = \Bin\Request\Request::capture();
        $urlParams = $request->getUrlParam() ?? [];

        foreach ($reflection->getParameters() as $index => $param) {
            $type = $param->getType();
            if ($type === null || $type->isBuiltin()) {
                continue;
            }

            $typeName = $type->getName();
            $paramName = $param->getName();

            // 检查是否有显式绑定
            if (RouteBinding::hasBinding($paramName)) {
                $value = $urlParams[$paramName] ?? ($params[$index] ?? null);
                if ($value !== null) {
                    $params[$index] = RouteBinding::resolve($paramName, $value);
                }
                continue;
            }

            // 隐式绑定：类型是模型类
            if (class_exists($typeName) && is_subclass_of($typeName, \Bin\Database\Model::class)) {
                $value = $urlParams[$paramName] ?? ($params[$index] ?? null);
                if ($value !== null && !($value instanceof $typeName)) {
                    $params[$index] = RouteBinding::resolveForClass($typeName, $value);
                }
            }
        }

        return $params;
    }
}
