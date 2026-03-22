<?php

declare(strict_types=1);

namespace Bin\App;

use Bin\Container\Container;
use Bin\Contracts\ContainerInterface;
use Bin\Facade\Facade;
use Bin\Providers\ProviderRepository;
use Bin\Providers\ServiceProvider;
use Bin\Response\Response;
use Bin\Route\RouteCollection;

/**
 * 应用程序入口和 IoC 容器门面
 */
class App implements ContainerInterface
{
    /**
     * 全局应用实例
     */
    private static ?self $instance = null;

    /**
     * 底层容器实例
     */
    private Container $container;

    /**
     * 核心服务别名映射
     */
    private static array $coreAliases = [
        'request'  => \Bin\Request\Request::class,
        'response' => Response::class,
        'route'    => RouteCollection::class,
        'app'      => self::class,
        'container' => Container::class,
    ];

    /**
     * Facade 别名映射
     */
    private static array $facades = [
        'Request' => \Bin\Facade\Request::class,
    ];

    /**
     * 默认服务提供者
     */
    private static array $defaultProviders = [
        \Bin\Providers\RequestServiceProvider::class,
        \Bin\Providers\ResponseServiceProvider::class,
        \Bin\Providers\RoutingServiceProvider::class,
        \Bin\Providers\DatabaseServiceProvider::class,
        \Bin\Providers\ViewServiceProvider::class,
    ];

    /**
     * 服务提供者仓库
     */
    private ?ProviderRepository $providerRepository = null;

    /**
     * 应用是否已启动
     */
    private bool $booted = false;

    /**
     * 构造函数
     */
    private function __construct()
    {
        $this->container = new Container();
        $this->providerRepository = new ProviderRepository($this);
        $this->registerCoreServices();
    }

    /**
     * 获取全局应用实例
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
            self::$instance->setGlobalInstance();
        }

        return self::$instance;
    }

    /**
     * 设置全局容器实例
     */
    public static function setInstance(?self $instance): void
    {
        self::$instance = $instance;
        if ($instance !== null) {
            $instance->setGlobalInstance();
        }
    }

    /**
     * 设置全局容器实例（用于静态访问）
     */
    private function setGlobalInstance(): void
    {
        Container::setInstance($this->container);
    }

    /**
     * 注册核心服务
     */
    private function registerCoreServices(): void
    {
        // 注册单例服务
        foreach (self::$coreAliases as $alias => $class) {
            if (!$this->container->bound($alias)) {
                $this->container->singleton($alias, $class);
            }
        }

        // 注册 Facade
        foreach (self::$facades as $alias => $facade) {
            $this->container->alias($class = self::$coreAliases[strtolower($alias)], $alias);
            Facade::setFacadeContainer($alias, $this->container);
        }

        // 注册默认服务提供者
        $this->registerDefaultProviders();
    }

    /**
     * 注册默认服务提供者
     */
    private function registerDefaultProviders(): void
    {
        foreach (self::$defaultProviders as $provider) {
            $this->register($provider);
        }
    }

    /**
     * 注册服务提供者
     */
    public function register(string|ServiceProvider $provider, bool $force = false): void
    {
        $this->providerRepository?->register($provider, $force);
    }

    /**
     * 启动应用
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->providerRepository?->boot();

        $this->booted = true;
    }

    /**
     * 检查应用是否已启动
     */
    public function isBooted(): bool
    {
        return $this->booted;
    }

    /**
     * 获取服务提供者仓库
     */
    public function getProviderRepository(): ?ProviderRepository
    {
        return $this->providerRepository;
    }

    /**
     * 解析服务（重写以支持延迟服务提供者）
     */
    public function make(string $abstract): object
    {
        // 检查是否是延迟服务
        if ($this->providerRepository?->isDeferredService($abstract)) {
            $this->providerRepository->loadDeferredProvider($abstract);
        }

        return $this->container->make($abstract);
    }

    /**
     * 设置默认服务提供者
     */
    public function setDefaultProviders(array $providers): void
    {
        self::$defaultProviders = $providers;
    }

    /**
     * 添加服务提供者
     */
    public function addProvider(string|ServiceProvider $provider): void
    {
        self::$defaultProviders[] = is_string($provider) ? $provider : get_class($provider);
        $this->register($provider);
    }

    /**
     * 绑定服务到容器
     */
    public function bind(string $abstract, callable|string $concrete = null, bool $shared = false): void
    {
        $this->container->bind($abstract, $concrete, $shared);
    }

    /**
     * 绑定单例
     */
    public function singleton(string $abstract, callable|string $concrete = null): void
    {
        $this->container->singleton($abstract, $concrete);
    }

    /**
     * 绑定实例
     */
    public function instance(string $abstract, object $instance): void
    {
        $this->container->instance($abstract, $instance);
    }

    /**
     * 绑定别名
     */
    public function alias(string $abstract, string $alias): void
    {
        $this->container->alias($abstract, $alias);
    }

    /**
     * 上下文绑定
     */
    public function contextual(string $concrete, string $abstract, callable $implementation): void
    {
        $this->container->contextual($concrete, $abstract, $implementation);
    }

    /**
     * 扩展服务
     */
    public function extend(string $abstract, \Closure $callback): void
    {
        $this->container->extend($abstract, $callback);
    }

    /**
     * 检查是否已绑定
     */
    public function bound(string $abstract): bool
    {
        return $this->container->bound($abstract);
    }

    /**
     * 检查服务是否已解析
     */
    public function resolved(string $abstract): bool
    {
        return $this->container->resolved($abstract);
    }

    /**
     * 伪装实例（用于测试）
     */
    public function mock(string $abstract, ?object $mock = null): object
    {
        return $this->container->mock($abstract, $mock);
    }

    /**
     * 调用方法并返回
     */
    public function call(string $method, array $parameters = []): mixed
    {
        return $this->container->call($method, $parameters);
    }

    /**
     * 刷新所有绑定和实例
     */
    public function flush(): void
    {
        $this->container->flush();
        $this->registerCoreServices();
    }

    /**
     * 刷新单个服务
     */
    public function forget(string $abstract): void
    {
        $this->container->forget($abstract);
    }

    /**
     * 获取所有绑定
     */
    public function getBindings(): array
    {
        return $this->container->getBindings();
    }

    /**
     * 检查是否有特定绑定
     */
    public function hasBinding(string $abstract): bool
    {
        return $this->container->hasBinding($abstract);
    }

    /**
     * 注册 Facade（向后兼容）
     */
    public function facade(string $class): ?object
    {
        // 检查是否是核心 Facade
        $normalizedName = strtolower($class);

        if (isset(self::$facades[$class])) {
            $facadeClass = self::$facades[$class];
            Facade::setFacadeContainer($class, $this->container);

            return $this->make(self::$coreAliases[$normalizedName]);
        }

        // 尝试从容器解析
        if ($this->container->hasAlias($class)) {
            return $this->make($class);
        }

        if ($this->container->bound($class)) {
            return $this->make($class);
        }

        return null;
    }

    /**
     * 获取底层容器
     */
    public function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * 批量绑定
     */
    public function bindArray(array $bindings): void
    {
        $this->container->bindArray($bindings);
    }

    /**
     * 批量单例
     */
    public function singletonArray(array $bindings): void
    {
        $this->container->singletonArray($bindings);
    }

    /**
     * 批量实例
     */
    public function instanceArray(array $instances): void
    {
        $this->container->instanceArray($instances);
    }

    /**
     * 检查是否在构建堆栈中
     */
    public function isBuildStack(string $abstract): bool
    {
        return $this->container->isBuildStack($abstract);
    }

    /**
     * 检查是否有扩展
     */
    public function hasExtenders(string $abstract): bool
    {
        return $this->container->hasExtenders($abstract);
    }

    /**
     * 检查是否有实例
     */
    public function hasInstance(string $abstract): bool
    {
        return $this->container->hasInstance($abstract);
    }

    /**
     * 获取实例
     */
    public function getInstanceOf(string $abstract): ?object
    {
        return $this->container->getInstanceOf($abstract);
    }

    /**
     * 设置实例
     */
    public function setInstanceOf(string $abstract, object $instance): void
    {
        $this->container->setInstanceOf($abstract, $instance);
    }

    /**
     * 检查容器中是否有某个服务
     */
    public function has(string $abstract): bool
    {
        return $this->container->has($abstract);
    }

    /**
     * 注册工厂函数
     */
    public function factory(string $abstract, callable $factory): void
    {
        $this->container->factory($abstract, $factory);
    }

    /**
     * 绑定并立即解析
     */
    public function bindAndMake(string $abstract, callable|string $concrete = null): object
    {
        return $this->container->bindAndMake($abstract, $concrete);
    }

    /**
     * 绑定单例并立即解析
     */
    public function singletonAndMake(string $abstract, callable|string $concrete = null): object
    {
        return $this->container->singletonAndMake($abstract, $concrete);
    }

    /**
     * 检查是否在解析中
     */
    public function isResolving(string $abstract): bool
    {
        return $this->container->isResolving($abstract);
    }

    /**
     * 获取构建堆栈
     */
    public function getBuildStack(): array
    {
        return $this->container->getBuildStack();
    }

    /**
     * 解析服务（别名）
     */
    public function resolve(string $abstract): object
    {
        return $this->container->resolve($abstract);
    }

    /**
     * 检查是否有别名
     */
    public function hasAlias(string $name): bool
    {
        return $this->container->hasAlias($name);
    }

    /**
     * 获取别名对应的抽象名
     */
    public function getAlias(string $abstract): string
    {
        return $this->container->getAlias($abstract);
    }

    /**
     * 设置别名（别名）
     */
    public function setAlias(string $abstract, string $alias): void
    {
        $this->container->setAlias($abstract, $alias);
    }

    /**
     * 魔术方法调用（代理到容器）
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->container->__call($method, $parameters);
    }

    /**
     * 静魔术方法（支持静态访问）
     */
    public static function __callStatic(string $method, array $parameters): mixed
    {
        return self::getInstance()->$method(...$parameters);
    }
}
