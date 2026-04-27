<?php

declare(strict_types=1);

namespace Bin\Routing;

use Bin\App\App;
use Bin\Database\Model;
use Bin\Request\Request;
use Bin\Response\Response;
use Bin\Route\Route;
use Bin\Route\RouteBinding;
use Bin\Validation\FormRequest;

/**
 * 控制器调度器
 *
 * 统一 controller action 和闭包 action 的调用方式，
 * 通过 IoC 容器解析依赖、注入参数。
 *
 * 参数解析优先级：
 * 1. FormRequest → 创建实例并验证
 * 2. 路由模型绑定（显式/隐式）
 * 3. URL 路由参数（按名称匹配）
 * 4. 容器自动解析（由 Container::call() 处理）
 */
class ControllerDispatcher
{
    private function responseFactory(): \Bin\Response\ResponseFactory
    {
        return App::getInstance()->make(\Bin\Response\ResponseFactory::class);
    }

    /**
     * 调度控制器方法
     *
     * 通过容器实例化控制器、解析方法参数、调用方法。
     */
    public function dispatch(string $controller, string $method, Route $route): mixed
    {
        $app = App::getInstance();
        $instance = $app->make($controller);
        $parameters = $this->resolveMethodParameters($controller, $method, $route);

        $result = $app->getContainer()->call([$instance, $method], $parameters);

        return $this->responseFactory()->make($result);
    }

    /**
     * 调度闭包 action
     *
     * 通过容器调用闭包，自动注入依赖。
     */
    public function dispatchClosure(callable $closure, Route $route): mixed
    {
        $app = App::getInstance();
        $parameters = $this->resolveClosureParameters($closure, $route);

        $result = $app->getContainer()->call($closure, $parameters);

        return $this->responseFactory()->make($result);
    }

    /**
     * 解析控制器方法参数
     */
    protected function resolveMethodParameters(string $class, string $method, Route $route): array
    {
        if (!class_exists($class) || !method_exists($class, $method)) {
            return [];
        }

        $reflection = new \ReflectionMethod($class, $method);
        return $this->buildParameterMap($reflection->getParameters(), $route);
    }

    /**
     * 解析闭包参数
     */
    protected function resolveClosureParameters(callable $closure, Route $route): array
    {
        $refFunc = $closure instanceof \Closure
            ? new \ReflectionFunction($closure)
            : new \ReflectionFunction(\Closure::fromCallable($closure));

        return $this->buildParameterMap($refFunc->getParameters(), $route);
    }

    /**
     * 构建参数映射
     *
     * 遍历反射参数，按优先级确定每个参数的来源：
     * - FormRequest → 创建并验证
     * - 路由模型绑定（显式/隐式）
     * - URL 路由参数（按名称匹配）
     * - 其他类型提示：留给 Container::call() 通过 make() 解析
     */
    protected function buildParameterMap(array $reflectionParams, Route $route): array
    {
        // 优先从容器获取（可能有已设置的路由参数），否则重新捕获
        $container = App::getInstance();
        if ($container->has(\Bin\Request\Request::class)) {
            $request = $container->make(\Bin\Request\Request::class);
        } else {
            $request = Request::capture();
        }
        $urlParams = $request->getUrlParam() ?? [];
        $parameters = [];

        foreach ($reflectionParams as $param) {
            $type = $param->getType();
            $name = $param->getName();

            if ($type !== null && $type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $typeName = $type->getName();

                // 1. FormRequest 注入
                if (class_exists($typeName) && is_subclass_of($typeName, FormRequest::class)) {
                    $parameters[$name] = $this->resolveFormRequest($typeName, $request);
                    continue;
                }

                // 2. 显式路由模型绑定
                if (RouteBinding::hasBinding($name) && isset($urlParams[$name])) {
                    $parameters[$name] = RouteBinding::resolve($name, $urlParams[$name]);
                    continue;
                }

                // 3. 隐式模型绑定
                if (class_exists($typeName) && is_subclass_of($typeName, Model::class) && isset($urlParams[$name])) {
                    $resolved = RouteBinding::resolveForClass($typeName, $urlParams[$name]);
                    if ($resolved !== null) {
                        $parameters[$name] = $resolved;
                        continue;
                    }
                }

                // 4. 其他类型提示：不预填充，由 Container::call() 通过 make() 解析
                continue;
            }

            // 无类型/内置类型：从 URL 参数按名称映射
            if (isset($urlParams[$name])) {
                $parameters[$name] = $urlParams[$name];
            }
        }

        return $parameters;
    }

    /**
     * 创建并验证 FormRequest
     */
    protected function resolveFormRequest(string $className, Request $request): FormRequest
    {
        /** @var FormRequest $formRequest */
        $formRequest = $className::fromBaseRequest($request);
        $formRequest->validateResolved();

        return $formRequest;
    }
}
