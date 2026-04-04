<?php

declare(strict_types=1);

namespace Bin\Container;

use Bin\Container\Exceptions\BindingResolutionException;
use Bin\Container\Exceptions\CircularDependencyException;
use Bin\Container\Exceptions\NotFoundException;
use Bin\Contracts\ContainerInterface;
use Bin\Psr\Container\ContainerInterface as PsrContainerInterface;
use Bin\Psr\Container\NotFoundExceptionInterface;
use Closure;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionParameter;
use RuntimeException;

/**
 * IoC 服务容器
 *
 * 参考 Laravel Container 实现，支持：
 * - 绑定、单例、作用域绑定
 * - 别名、标签绑定
 * - 上下文绑定（when()->needs()->give()）
 * - 解析回调（resolving/afterResolving）
 * - 重绑定回调
 * - 方法注入
 * - PSR-11 兼容
 * - 循环依赖检测
 */
class Container implements ContainerInterface, PsrContainerInterface
{
    /**
     * 已绑定的服务
     * @var array<string, array{concrete: callable|string, shared: bool}>
     */
    protected array $bindings = [];

    /**
     * 共享实例（单例）
     * @var array<string, object>
     */
    protected array $instances = [];

    /**
     * 作用域实例
     * @var array<string, object>
     */
    protected array $scopedInstances = [];

    /**
     * 别名映射
     * @var array<string, string>
     */
    protected array $aliases = [];

    /**
     * 上下文绑定
     * @var array<string, array<string, mixed>>
     */
    protected array $contextual = [];

    /**
     * 标签映射
     * @var array<string, string[]>
     */
    protected array $tags = [];

    /**
     * 扩展回调
     * @var array<string, callable[]>
     */
    protected array $extenders = [];

    /**
     * 全局解析回调
     * @var callable[]
     */
    protected array $globalResolvingCallbacks = [];

    /**
     * 全局解析后回调
     * @var callable[]
     */
    protected array $globalAfterResolvingCallbacks = [];

    /**
     * 按抽象名分组的解析回调
     * @var array<string, callable[]>
     */
    protected array $resolvingCallbacks = [];

    /**
     * 按抽象名分组的解析后回调
     * @var array<string, callable[]>
     */
    protected array $afterResolvingCallbacks = [];

    /**
     * 重绑定回调
     * @var array<string, callable[]>
     */
    protected array $reboundCallbacks = [];

    /**
     * 构建堆栈（用于循环依赖检测）
     * @var string[]
     */
    protected array $buildStack = [];

    /**
     * 已解析标记
     * @var array<string, bool>
     */
    protected array $resolved = [];

    /**
     * 全局容器实例
     */
    protected static ?self $instance = null;

    /**
     * 获取全局容器实例
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * 设置全局实例
     */
    public static function setInstance(?self $instance): void
    {
        self::$instance = $instance;
    }

    // ===== 绑定方法 =====

    /**
     * 绑定服务到容器
     */
    public function bind(string $abstract, callable|string $concrete = null, bool $shared = false): void
    {
        $this->dropStaleInstances($abstract);

        if ($concrete === null) {
            $concrete = $abstract;
        }

        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'shared' => $shared,
        ];

        if ($this->resolved($abstract)) {
            $this->rebound($abstract);
        }
    }

    /**
     * 绑定单例
     */
    public function singleton(string $abstract, callable|string $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }

    /**
     * 绑定实例
     */
    public function instance(string $abstract, object $instance): void
    {
        $this->removeAbstractAlias($abstract);

        $isBound = $this->bound($abstract);

        $this->instances[$abstract] = $instance;

        $this->resolved[$abstract] = true;

        if ($isBound) {
            $this->rebound($abstract);
        }
    }

    /**
     * 绑定别名
     */
    public function alias(string $abstract, string $alias): void
    {
        $this->aliases[$alias] = $abstract;
    }

    /**
     * 作用域绑定
     *
     * 在同一作用域/请求生命周期内共享实例
     */
    public function scoped(string $abstract, callable|string $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);

        // 标记为 scoped
        if (isset($this->bindings[$abstract])) {
            $this->bindings[$abstract]['scoped'] = true;
        }
    }

    /**
     * 条件绑定：仅在未绑定时绑定
     */
    public function bindIf(string $abstract, callable|string $concrete = null, bool $shared = false): void
    {
        if (!$this->bound($abstract)) {
            $this->bind($abstract, $concrete, $shared);
        }
    }

    /**
     * 条件单例：仅在未绑定时绑定单例
     */
    public function singletonIf(string $abstract, callable|string $concrete = null): void
    {
        if (!$this->bound($abstract)) {
            $this->singleton($abstract, $concrete);
        }
    }

    // ===== 标签绑定 =====

    /**
     * 为服务注册标签
     *
     * @param string|string[] $abstracts 服务名
     * @param string|string[] $tags 标签名
     */
    public function tag(array|string $abstracts, array|string $tags): void
    {
        $abstracts = (array) $abstracts;
        $tags = (array) $tags;

        foreach ($tags as $tag) {
            if (!isset($this->tags[$tag])) {
                $this->tags[$tag] = [];
            }

            foreach ($abstracts as $abstract) {
                if (!in_array($abstract, $this->tags[$tag], true)) {
                    $this->tags[$tag][] = $abstract;
                }
            }
        }
    }

    /**
     * 获取标签下的所有服务实例
     *
     * @return object[]
     */
    public function tagged(string $tag): array
    {
        $abstracts = $this->tags[$tag] ?? [];

        return array_map(fn(string $abstract) => $this->make($abstract), $abstracts);
    }

    // ===== 解析回调 =====

    /**
     * 注册解析回调
     *
     * 两个签名：
     * - resolving($callback) - 全局回调
     * - resolving($abstract, $callback) - 特定抽象名回调
     */
    public function resolving(string|callable $abstract, ?callable $callback = null): void
    {
        if ($abstract instanceof \Closure || is_callable($abstract)) {
            $this->globalResolvingCallbacks[] = $abstract;
        } else {
            $this->resolvingCallbacks[$abstract][] = $callback;
        }
    }

    /**
     * 注册解析后回调
     */
    public function afterResolving(string|callable $abstract, ?callable $callback = null): void
    {
        if ($abstract instanceof \Closure || is_callable($abstract)) {
            $this->globalAfterResolvingCallbacks[] = $abstract;
        } else {
            $this->afterResolvingCallbacks[$abstract][] = $callback;
        }
    }

    // ===== 重绑定回调 =====

    /**
     * 注册重绑定回调
     */
    public function rebinding(string $abstract, \Closure $callback): void
    {
        $this->reboundCallbacks[$abstract][] = $callback;

        // 如果已有实例，立即触发
        if ($this->hasInstance($abstract)) {
            $instance = $this->make($abstract);
            $callback($this, $instance);
        }
    }

    /**
     * 刷新服务（重绑定辅助方法）
     */
    public function refresh(string $abstract, object $target, string $method): mixed
    {
        return $this->rebinding($abstract, function ($container, $instance) use ($target, $method) {
            $target->{$method}($instance);
        });
    }

    // ===== 上下文绑定 =====

    /**
     * 旧式上下文绑定（保持向后兼容）
     */
    public function contextual(string $concrete, string $abstract, callable $implementation): void
    {
        $this->contextual[$concrete][$abstract] = $implementation;
    }

    /**
     * 条件绑定流畅接口
     */
    public function when(string|array $concrete): ContextualBindingBuilder
    {
        $concretes = (array) $concrete;

        return new ContextualBindingBuilder($this, $concretes);
    }

    /**
     * 内部：添加上下文绑定（由 ContextualBindingBuilder 调用）
     */
    public function addContextualBinding(string $concrete, string $abstract, mixed $implementation): void
    {
        $this->contextual[$concrete][$abstract] = $implementation;
    }

    // ===== 解析方法 =====

    /**
     * 解析服务
     */
    public function make(string $abstract): object
    {
        return $this->resolve($abstract);
    }

    /**
     * 解析服务
     */
    public function resolve(string $abstract): object
    {
        $abstract = $this->getAlias($abstract);

        // 检查循环依赖
        if (in_array($abstract, $this->buildStack, true)) {
            throw new CircularDependencyException($abstract, $this->buildStack);
        }

        $needsContextualBuild = !empty($this->buildStack)
            && isset($this->contextual[end($this->buildStack)][$abstract]);

        // 返回已缓存的实例
        if (isset($this->instances[$abstract]) && !$needsContextualBuild) {
            return $this->instances[$abstract];
        }

        // 返回作用域实例
        if (isset($this->scopedInstances[$abstract]) && !$needsContextualBuild) {
            return $this->scopedInstances[$abstract];
        }

        $concrete = $this->getConcrete($abstract);

        $this->buildStack[] = $abstract;

        try {
            $object = $this->build($concrete, $abstract);
        } finally {
            array_pop($this->buildStack);
        }

        // 触发扩展回调
        foreach ($this->getExtenders($abstract) as $extender) {
            $object = $extender($object, $this);
        }

        // 触发解析回调
        $this->fireResolvingCallbacks($abstract, $object);

        // 缓存实例
        if ($this->isShared($abstract) && !$needsContextualBuild) {
            if ($this->isScoped($abstract)) {
                $this->scopedInstances[$abstract] = $object;
            } else {
                $this->instances[$abstract] = $object;
            }
        }

        $this->resolved[$abstract] = true;

        return $object;
    }

    /**
     * 构建实例
     */
    protected function build(callable|string $concrete, string $abstract): object
    {
        if ($concrete instanceof Closure) {
            return $concrete($this, $this);
        }

        return $this->buildWithReflection($concrete);
    }

    /**
     * 使用反射构建实例
     */
    protected function buildWithReflection(string $concrete): object
    {
        if (!class_exists($concrete)) {
            throw new BindingResolutionException($concrete, "Class [{$concrete}] does not exist");
        }
        $reflector = new ReflectionClass($concrete);        if (!$reflector->isInstantiable()) {
            throw new BindingResolutionException($concrete, "Class [{$concrete}] is not instantiable");
        }

        $constructor = $reflector->getConstructor();

        if ($constructor === null) {
            return new $concrete;
        }

        $dependencies = $constructor->getParameters();

        if (empty($dependencies)) {
            return new $concrete();
        }

        try {
            $instances = $this->resolveDependencies($dependencies);
        } catch (CircularDependencyException $e) {
            throw $e;
        } catch (BindingResolutionException $e) {
            throw BindingResolutionException::dependencyFailed($concrete, $e->getAbstract(), $e);
        }

        return $reflector->newInstanceArgs($instances);
    }

    /**
     * 解析依赖
     */
    protected function resolveDependencies(array $dependencies): array
    {
        $results = [];

        foreach ($dependencies as $dependency) {
            $type = $dependency->getType();

            if ($type !== null && $type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $abstract = $type->getName();

                // 检查上下文绑定
                if (!empty($this->buildStack)) {
                    $buildingClass = end($this->buildStack);

                    if (isset($this->contextual[$buildingClass][$abstract])) {
                        $concrete = $this->contextual[$buildingClass][$abstract];

                        if ($concrete instanceof Closure) {
                            $results[] = $concrete($this);
                        } elseif (is_string($concrete)) {
                            $results[] = $this->make($concrete);
                        } else {
                            $results[] = $concrete;
                        }

                        continue;
                    }
                }

                $results[] = $this->make($abstract);
                continue;
            }

            // 检查上下文绑定（按参数名）
            if (!empty($this->buildStack)) {
                $buildingClass = end($this->buildStack);
                $paramName = $dependency->getName();

                if (isset($this->contextual[$buildingClass][$paramName])) {
                    $concrete = $this->contextual[$buildingClass][$paramName];
                    $results[] = $concrete instanceof Closure ? $concrete($this) : $concrete;
                    continue;
                }
            }

            if ($dependency->isDefaultValueAvailable()) {
                $results[] = $dependency->getDefaultValue();
            } else {
                throw new BindingResolutionException(
                    $dependency->getName(),
                    "Unable to resolve parameter [{$dependency->getName()}]"
                );
            }
        }

        return $results;
    }

    /**
     * 获取具体实现类名
     */
    protected function getConcrete(string $abstract): callable|string
    {
        if (!isset($this->bindings[$abstract])) {
            return $abstract;
        }

        return $this->bindings[$abstract]['concrete'];
    }

    // ===== 方法调用 =====

    /**
     * 调用回调并返回结果
     *
     * 支持：闭包、Class@method 字符串、数组回调 [$instance, 'method']
     */
    public function call(callable|string $callback, array $parameters = []): mixed
    {
        // 闭包
        if ($callback instanceof \Closure) {
            return $this->callClosure($callback, $parameters);
        }

        // 数组回调 [$instance, 'method']
        if (is_array($callback)) {
            return $this->callMethod($callback[0], $callback[1], $parameters);
        }

        // Class@method 字符串
        if (is_string($callback) && str_contains($callback, '@')) {
            [$class, $method] = explode('@', $callback, 2);
            $instance = $this->make($class);
            return $this->callMethod($instance, $method, $parameters);
        }

        // 可调用函数
        if (is_callable($callback)) {
            return $callback(...$parameters);
        }

        throw new BindingResolutionException(
            is_string($callback) ? $callback : 'unknown',
            "Unsupported callback type"
        );
    }

    /**
     * 调用闭包并注入依赖
     */
    protected function callClosure(\Closure $closure, array $parameters = []): mixed
    {
        $reflector = new ReflectionFunction($closure);
        $args = $this->resolveMethodParameters($reflector->getParameters(), $parameters);

        return $closure(...$args);
    }

    /**
     * 调用对象方法并注入依赖
     */
    protected function callMethod(object $instance, string $method, array $parameters = []): mixed
    {
        $reflector = new ReflectionMethod($instance, $method);
        $args = $this->resolveMethodParameters($reflector->getParameters(), $parameters);

        return $instance->{$method}(...$args);
    }

    /**
     * 解析方法参数
     */
    protected function resolveMethodParameters(array $dependencies, array $parameters): array
    {
        $results = [];

        foreach ($dependencies as $dependency) {
            $name = $dependency->getName();

            // 显式参数优先
            if (array_key_exists($name, $parameters)) {
                $results[] = $parameters[$name];
                continue;
            }

            $type = $dependency->getType();

            if ($type !== null && $type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $results[] = $this->make($type->getName());
                continue;
            }

            if ($dependency->isDefaultValueAvailable()) {
                $results[] = $dependency->getDefaultValue();
                continue;
            }

            if ($dependency->isVariadic()) {
                continue;
            }

            throw new BindingResolutionException(
                $name,
                "Unable to resolve parameter [{$name}] in method call"
            );
        }

        return $results;
    }

    // ===== 查询方法 =====

    /**
     * 检查是否是单例
     */
    protected function isShared(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) &&
               $this->bindings[$abstract]['shared'] === true;
    }

    /**
     * 检查是否是作用域绑定
     */
    protected function isScoped(string $abstract): bool
    {
        return isset($this->bindings[$abstract]['scoped']) &&
               $this->bindings[$abstract]['scoped'] === true;
    }

    /**
     * 检查是否已绑定
     */
    public function bound(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) ||
               isset($this->instances[$abstract]) ||
               $this->hasAlias($abstract);
    }

    /**
     * PSR-11 has
     */
    public function has(string $id): bool
    {
        return $this->bound($id);
    }

    /**
     * PSR-11 get
     */
    public function get(string $id): mixed
    {
        try {
            return $this->make($id);
        } catch (BindingResolutionException $e) {
            throw new NotFoundException($id, $e->getMessage(), 0, $e);
        }
    }

    /**
     * 检查是否有别名
     */
    public function hasAlias(string $name): bool
    {
        return isset($this->aliases[$name]);
    }

    /**
     * 获取别名对应的抽象名
     */
    public function getAlias(string $abstract): string
    {
        if (!isset($this->aliases[$abstract])) {
            return $abstract;
        }

        return $this->getAlias($this->aliases[$abstract]);
    }

    /**
     * 移除抽象名的别名
     */
    protected function removeAbstractAlias(string $abstract): void
    {
        if (!isset($this->aliases[$abstract])) {
            return;
        }

        unset($this->aliases[$abstract]);
    }

    /**
     * 检查服务是否已解析
     */
    public function resolved(string $abstract): bool
    {
        if (isset($this->resolved[$abstract])) {
            return true;
        }

        return $this->isShared($abstract) && isset($this->instances[$abstract]);
    }

    /**
     * 扩展服务
     */
    public function extend(string $abstract, Closure $callback): void
    {
        $this->extenders[$abstract][] = $callback;

        if (isset($this->instances[$abstract])) {
            $this->instances[$abstract] = $callback($this->instances[$abstract], $this);
        }
    }

    // ===== 内部方法 =====

    /**
     * 清除过时的实例
     */
    protected function dropStaleInstances(string $abstract): void
    {
        unset($this->instances[$abstract], $this->scopedInstances[$abstract]);
    }

    /**
     * 重新绑定并解析服务
     */
    protected function rebound(string $abstract): void
    {
        $instance = $this->make($abstract);

        foreach ($this->getReboundCallbacks($abstract) as $callback) {
            $callback($this, $instance);
        }
    }

    /**
     * 获取重绑定回调
     */
    protected function getReboundCallbacks(string $abstract): array
    {
        return $this->reboundCallbacks[$abstract] ?? [];
    }

    /**
     * 触发解析回调
     */
    protected function fireResolvingCallbacks(string $abstract, object $object): void
    {
        // 全局解析回调
        foreach ($this->globalResolvingCallbacks as $callback) {
            $callback($object, $this);
        }

        // 特定抽象名解析回调
        if (isset($this->resolvingCallbacks[$abstract])) {
            foreach ($this->resolvingCallbacks[$abstract] as $callback) {
                $callback($object, $this);
            }
        }

        // 全局解析后回调
        foreach ($this->globalAfterResolvingCallbacks as $callback) {
            $callback($object, $this);
        }

        // 特定抽象名解析后回调
        if (isset($this->afterResolvingCallbacks[$abstract])) {
            foreach ($this->afterResolvingCallbacks[$abstract] as $callback) {
                $callback($object, $this);
            }
        }
    }

    /**
     * 获取扩展回调
     */
    protected function getExtenders(string $abstract): array
    {
        return $this->extenders[$abstract] ?? [];
    }

    /**
     * 伪装实例
     */
    public function mock(string $abstract, ?object $mock = null): object
    {
        if ($mock === null) {
            $mock = $this->createMock($abstract);
        }

        $this->instance($abstract, $mock);

        return $mock;
    }

    /**
     * 创建模拟对象
     */
    protected function createMock(string $abstract): object
    {
        $concrete = $this->getConcrete($abstract);

        if ($concrete instanceof Closure) {
            $concrete = $concrete($this);
        }

        if ($abstract === 'test' || !$this->bound($abstract)) {
            return new \StdClass();
        }

        if (class_exists($concrete) && $concrete !== \StdClass::class) {
            return new $concrete();
        }

        return new \StdClass();
    }

    // ===== 作用域管理 =====

    /**
     * 重置作用域实例
     */
    public function resetScope(): void
    {
        $this->scopedInstances = [];
    }

    // ===== 管理方法 =====

    /**
     * 刷新所有绑定和实例
     */
    public function flush(): void
    {
        $this->aliases = [];
        $this->bindings = [];
        $this->instances = [];
        $this->scopedInstances = [];
        $this->contextual = [];
        $this->tags = [];
        $this->extenders = [];
        $this->globalResolvingCallbacks = [];
        $this->globalAfterResolvingCallbacks = [];
        $this->resolvingCallbacks = [];
        $this->afterResolvingCallbacks = [];
        $this->reboundCallbacks = [];
        $this->resolved = [];
        $this->buildStack = [];
    }

    /**
     * 刷新单个服务
     */
    public function forget(string $abstract): void
    {
        unset(
            $this->instances[$abstract],
            $this->scopedInstances[$abstract],
            $this->bindings[$abstract],
            $this->resolved[$abstract]
        );
    }

    /**
     * 获取所有绑定
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * 检查是否有特定绑定
     */
    public function hasBinding(string $abstract): bool
    {
        return isset($this->bindings[$abstract]);
    }

    /**
     * 注册 facade
     */
    public function facade(string $class): ?object
    {
        if ($this->hasAlias($class)) {
            return $this->make($class);
        }

        if ($this->bound($class)) {
            return $this->make($class);
        }

        return null;
    }

    /**
     * 设置别名
     */
    public function setAlias(string $abstract, string $alias): void
    {
        $this->alias($abstract, $alias);
    }

    /**
     * 批量绑定
     */
    public function bindArray(array $bindings): void
    {
        foreach ($bindings as $abstract => $concrete) {
            $this->bind($abstract, $concrete);
        }
    }

    /**
     * 批量单例
     */
    public function singletonArray(array $bindings): void
    {
        foreach ($bindings as $abstract => $concrete) {
            $this->singleton($abstract, $concrete);
        }
    }

    /**
     * 批量实例
     */
    public function instanceArray(array $instances): void
    {
        foreach ($instances as $abstract => $instance) {
            $this->instance($abstract, $instance);
        }
    }

    /**
     * 检查是否在构建堆栈中
     */
    public function isBuildStack(string $abstract): bool
    {
        return in_array($abstract, $this->buildStack);
    }

    /**
     * 检查是否有扩展
     */
    public function hasExtenders(string $abstract): bool
    {
        return isset($this->extenders[$abstract]);
    }

    /**
     * 检查是否有实例
     */
    public function hasInstance(string $abstract): bool
    {
        return isset($this->instances[$abstract]);
    }

    /**
     * 获取指定抽象名的实例
     */
    public function getInstanceOf(string $abstract): ?object
    {
        return $this->instances[$abstract] ?? null;
    }

    /**
     * 设置实例
     */
    public function setInstanceOf(string $abstract, object $instance): void
    {
        $this->instance($abstract, $instance);
    }

    /**
     * 注册工厂函数
     */
    public function factory(string $abstract, callable $factory): void
    {
        $this->bind($abstract, $factory);
    }

    /**
     * 绑定并立即解析
     */
    public function bindAndMake(string $abstract, callable|string $concrete = null): object
    {
        $this->bind($abstract, $concrete);

        return $this->make($abstract);
    }

    /**
     * 绑定单例并立即解析
     */
    public function singletonAndMake(string $abstract, callable|string $concrete = null): object
    {
        $this->singleton($abstract, $concrete);

        return $this->make($abstract);
    }

    /**
     * 检查是否在解析中
     */
    public function isResolving(string $abstract): bool
    {
        return $this->isBuildStack($abstract);
    }

    /**
     * 获取构建堆栈
     */
    public function getBuildStack(): array
    {
        return $this->buildStack;
    }

    /**
     * 魔术方法调用
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->make($method);
    }

    /**
     * 静态方法调用
     */
    public static function __callStatic(string $method, array $parameters): mixed
    {
        return self::getInstance()->$method(...$parameters);
    }
}
