<?php

declare(strict_types=1);

namespace Bin\App;

use Bin\Container\Container;
use Bin\Container\ContextualBindingBuilder;
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
        'request'   => \Bin\Request\Request::class,
        'response'  => Response::class,
        'route'     => RouteCollection::class,
        'app'       => self::class,
        'container' => Container::class,
        'events'    => \Bin\Events\EventDispatcher::class,
        'cache'     => \Bin\Cache\CacheManager::class,
        'config'    => \Bin\Config\ConfigRepository::class,
        'log'       => \Bin\Log\LogManager::class,
        'session'   => \Bin\Session\SessionManager::class,
        'auth'      => \Bin\Auth\AuthManager::class,
        'gate'      => \Bin\Auth\Gate::class,
        'db'        => \Bin\Database\ConnectionManager::class,
        'cookie'    => \Bin\Cookie\CookieManager::class,
        'hash'      => \Bin\Auth\HashManager::class,
        'view'      => \Bin\View\View::class,
        'validator' => \Bin\Validation\ValidationManager::class,
    ];

    /**
     * Facade 别名映射
     */
    private static array $facades = [
        'Request'   => \Bin\Facade\Request::class,
        'Event'     => \Bin\Facade\Event::class,
        'Cache'     => \Bin\Facade\Cache::class,
        'Config'    => \Bin\Facade\Config::class,
        'Log'       => \Bin\Facade\Log::class,
        'Session'   => \Bin\Facade\Session::class,
        'Auth'      => \Bin\Facade\Auth::class,
        'Gate'      => \Bin\Facade\Gate::class,
        'Hash'      => \Bin\Facade\Hash::class,
        'DB'        => \Bin\Facade\DB::class,
        'Cookie'    => \Bin\Facade\Cookie::class,
        'Route'     => \Bin\Facade\Route::class,
        'URL'       => \Bin\Facade\URL::class,
        'View'      => \Bin\Facade\View::class,
        'Validator' => \Bin\Facade\Validator::class,
    ];

    /**
     * 默认服务提供者
     */
    private static array $defaultProviders = [
        \Bin\Providers\RequestServiceProvider::class,
        \Bin\Providers\ResponseServiceProvider::class,
        \Bin\Providers\RoutingServiceProvider::class,
        \Bin\Providers\EventServiceProvider::class,
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
            // 尝试从 coreAliases 获取对应的服务类名
            $serviceName = self::$coreAliases[strtolower($alias)] ?? null;
            if ($serviceName !== null) {
                $this->container->alias($serviceName, $alias);
            }
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
     * 检查是否已绑定
     */
    public function bound(string $abstract): bool
    {
        return $this->container->bound($abstract);
    }

    /**
     * 扩展服务
     */
    public function extend(string $abstract, \Closure $callback): void
    {
        $this->container->extend($abstract, $callback);
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
     * 检查容器中是否有某个服务
     */
    public function has(string $abstract): bool
    {
        return $this->container->has($abstract);
    }

    /**
     * 为服务注册标签
     */
    public function tag(array|string $abstracts, array|string $tags): void
    {
        $this->container->tag($abstracts, $tags);
    }

    /**
     * 获取标签下的所有服务实例
     */
    public function tagged(string $tag): array
    {
        return $this->container->tagged($tag);
    }

    /**
     * 作用域绑定
     */
    public function scoped(string $abstract, callable|string $concrete = null): void
    {
        $this->container->scoped($abstract, $concrete);
    }

    /**
     * 重置作用域实例
     */
    public function resetScope(): void
    {
        $this->container->resetScope();
    }

    /**
     * 注册解析回调
     */
    public function resolving(string|callable $abstract, ?callable $callback = null): void
    {
        $this->container->resolving($abstract, $callback);
    }

    /**
     * 注册解析后回调
     */
    public function afterResolving(string|callable $abstract, ?callable $callback = null): void
    {
        $this->container->afterResolving($abstract, $callback);
    }

    /**
     * 注册重绑定回调
     */
    public function rebinding(string $abstract, \Closure $callback): void
    {
        $this->container->rebinding($abstract, $callback);
    }

    /**
     * 条件绑定流畅接口
     */
    public function when(string|array $concrete): ContextualBindingBuilder
    {
        return $this->container->when($concrete);
    }

    /**
     * 条件绑定：仅在未绑定时绑定
     */
    public function bindIf(string $abstract, callable|string $concrete = null, bool $shared = false): void
    {
        $this->container->bindIf($abstract, $concrete, $shared);
    }

    /**
     * 条件单例：仅在未绑定时绑定单例
     */
    public function singletonIf(string $abstract, callable|string $concrete = null): void
    {
        $this->container->singletonIf($abstract, $concrete);
    }

    /**
     * 解析服务（别名）
     */
    public function resolve(string $abstract): object
    {
        return $this->container->resolve($abstract);
    }

    /**
     * 魔术方法调用（代理到容器方法）
     */
    public function __call(string $method, array $parameters): mixed
    {
        if (method_exists($this->container, $method)) {
            return $this->container->$method(...$parameters);
        }

        return $this->container->make($method);
    }

    /**
     * 静魔术方法（支持静态访问）
     */
    public static function __callStatic(string $method, array $parameters): mixed
    {
        return self::getInstance()->$method(...$parameters);
    }
}
