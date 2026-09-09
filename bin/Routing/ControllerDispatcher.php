<?php

declare(strict_types=1);

namespace Bin\Routing;

use Bin\App\App;
use Bin\Database\Model;
use Bin\Exception\NotFoundHttpException;
use Bin\Request\Request;
use Bin\Response\Response;
use Bin\Response\ResponseFactory;
use Bin\Route\Route;
use Bin\Route\RouteBinding;
use Bin\Route\ResourceRegistrar;
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
    /** @var Response|null 隐式绑定 missing 回调产生的响应（命中时短路调度） */
    private ?Response $missingResponse = null;

    private function responseFactory(): ResponseFactory
    {
        return App::getInstance()->make(ResponseFactory::class);
    }

    /**
     * 调度控制器方法
     *
     * 通过容器实例化控制器、解析方法参数、调用方法。
     */
    public function dispatch(string $controller, string $method, Route $route): mixed
    {
        $app = App::getInstance();
        $this->missingResponse = null;
        $instance = $app->make($controller);
        $parameters = $this->resolveMethodParameters($controller, $method, $route);

        if ($this->missingResponse !== null) {
            return $this->missingResponse;
        }

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
        $this->missingResponse = null;
        $parameters = $this->resolveClosureParameters($closure, $route);

        if ($this->missingResponse !== null) {
            return $this->missingResponse;
        }

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
     * - URL 路由参数（按名称匹配；旧式 with() 路由为数字键，按位置匹配）
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
        // 旧式 with() 路由的参数是位置索引（数字键），按声明顺序绑定
        $positionalParams = array_values(array_filter(
            $urlParams,
            static fn (int|string $key): bool => is_int($key),
            ARRAY_FILTER_USE_KEY
        ));
        $positionalIndex = 0;
        $parameters = [
            \Bin\Container\Container::ROUTE_PARAMETER_CONTEXT => $urlParams,
        ];

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
                if (RouteBinding::hasBinding($name) && array_key_exists($name, $urlParams)) {
                    $parameters[$name] = RouteBinding::resolve($name, $urlParams[$name]);
                    continue;
                }

                // 3. 隐式模型绑定（支持 scoped 嵌套约束与 missing 回调）
                if (class_exists($typeName) && is_subclass_of($typeName, Model::class) && array_key_exists($name, $urlParams)) {
                    $resolved = $this->resolveImplicitBinding($route, $typeName, $name, $urlParams[$name], $urlParams);
                    if ($resolved !== null) {
                        $parameters[$name] = $resolved;
                        continue;
                    }
                }

                // 4. 其他类型提示：不预填充，由 Container::call() 通过 make() 解析
                continue;
            }

            // 无类型/内置类型：从 URL 参数按名称映射，名称未命中时按位置兜底
            if (array_key_exists($name, $urlParams)) {
                $parameters[$name] = $urlParams[$name];
            } elseif ($positionalIndex < count($positionalParams)) {
                $parameters[$name] = $positionalParams[$positionalIndex++];
            }
        }

        return $parameters;
    }

    /**
     * 解析隐式模型绑定（含 scoped 上下文与 missing 回调）
     */
    protected function resolveImplicitBinding(
        Route $route,
        string $typeName,
        string $paramName,
        mixed $value,
        array $urlParams
    ): mixed {
        $scope = $this->resolveScopeContext($route, $paramName, $urlParams);

        try {
            return RouteBinding::resolveForClass($typeName, $value, $scope);
        } catch (NotFoundHttpException $exception) {
            $callback = $route->getMissingCallback();

            if ($callback === null) {
                throw $exception;
            }

            $result = $callback($exception);
            $this->missingResponse = $this->responseFactory()->make($result);

            return null;
        }
    }

    /**
     * 计算 scoped 嵌套绑定上下文
     *
     * scoped 映射两种形式：
     * - 'comment' => 'post'：外键默认为 post_id
     * - 'comment' => ['post' => 'blog_id']：显式外键
     *
     * @param array<string, mixed> $urlParams
     * @return array{foreign_key?: string, value?: mixed}
     */
    protected function resolveScopeContext(Route $route, string $paramName, array $urlParams): array
    {
        $binding = $route->getScoped()[$paramName] ?? null;

        if ($binding === null) {
            return [];
        }

        if (is_array($binding)) {
            $parentParam = (string) array_key_first($binding);
            $foreignKey = (string) ($binding[$parentParam] ?? '');
        } else {
            $parentParam = (string) $binding;
            $foreignKey = ResourceRegistrar::singularize($parentParam) . '_id';
        }

        if ($foreignKey === '' || !array_key_exists($parentParam, $urlParams)) {
            return [];
        }

        return [
            'foreign_key' => $foreignKey,
            'value' => $urlParams[$parentParam],
        ];
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
