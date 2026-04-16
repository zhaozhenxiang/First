<?php

declare(strict_types=1);

namespace Bin\Providers;

use Bin\App\App;

/**
 * 服务提供者仓库
 *
 * 管理所有服务提供者的注册、加载和启动
 */
class ProviderRepository
{
    /**
     * 应用实例
     */
    protected App $app;

    /**
     * 已注册的服务提供者
     * @var array<string, ServiceProvider>
     */
    protected array $providers = [];

    /**
     * 已启动的服务提供者
     * @var array<string, bool>
     */
    protected array $booted = [];

    /**
     * 延迟加载的服务提供者
     * @var array<string, array<string>>
     */
    protected array $deferredProviders = [];

    /**
     * 已加载的延迟服务
     * @var array<string, bool>
     */
    protected array $loadedDeferred = [];

    /**
     * 构造函数
     */
    public function __construct(App $app)
    {
        $this->app = $app;
    }

    /**
     * 注册服务提供者
     */
    public function register(string|ServiceProvider $provider, bool $force = false): void
    {
        // 如果是类名，创建实例
        if (is_string($provider)) {
            $provider = $this->createProvider($provider);
        }

        $providerClass = get_class($provider);

        // 已经注册且不强制重新注册
        if (isset($this->providers[$providerClass]) && !$force) {
            return;
        }

        // 注册提供者
        $this->providers[$providerClass] = $provider;

        // 如果是延迟提供者，记录其提供的服务
        if ($provider->isDeferred()) {
            foreach ($provider->provides() as $service) {
                $this->deferredProviders[$service] = $providerClass;
            }
        } else {
            // 立即注册服务
            $provider->register();
        }
    }

    /**
     * 启动所有服务提供者
     */
    public function boot(): void
    {
        if (count($this->booted) > 0) {
            return; // 已经启动过
        }

        foreach ($this->providers as $provider) {
            // 跳过延迟提供者
            if ($provider->isDeferred()) {
                continue;
            }

            $this->bootProvider($provider);
        }
    }

    /**
     * 启动单个服务提供者
     */
    protected function bootProvider(ServiceProvider $provider): void
    {
        $providerClass = get_class($provider);

        if (isset($this->booted[$providerClass])) {
            return;
        }

        $provider->boot();

        $this->booted[$providerClass] = true;
    }

    /**
     * 加载延迟服务提供者
     */
    public function loadDeferredProvider(string $service): void
    {
        if (!isset($this->deferredProviders[$service])) {
            return;
        }

        $providerClass = $this->deferredProviders[$service];

        if (isset($this->loadedDeferred[$service])) {
            return; // 已加载
        }

        $provider = $this->providers[$providerClass] ?? null;

        if ($provider === null) {
            return;
        }

        // 注册服务
        $provider->register();

        // 标记为已加载
        foreach ($provider->provides() as $providedService) {
            $this->loadedDeferred[$providedService] = true;
        }

        // 启动提供者
        $this->bootProvider($provider);
    }

    /**
     * 检查服务是否已注册
     */
    public function hasProvider(string $providerClass): bool
    {
        return isset($this->providers[$providerClass]);
    }

    /**
     * 获取已注册的提供者
     */
    public function getProviders(): array
    {
        return $this->providers;
    }

    /**
     * 检查服务是否由延迟提供者提供
     */
    public function isDeferredService(string $service): bool
    {
        return isset($this->deferredProviders[$service]);
    }

    /**
     * 获取服务的提供者类名
     */
    public function getProviderForService(string $service): ?string
    {
        return $this->deferredProviders[$service] ?? null;
    }

    /**
     * 创建服务提供者实例
     */
    protected function createProvider(string $providerClass): ServiceProvider
    {
        /** @var ServiceProvider $provider */
        $provider = $this->app->make($providerClass);

        return $provider;
    }

    /**
     * 重置所有提供者
     */
    public function reset(): void
    {
        $this->providers = [];
        $this->booted = [];
        $this->deferredProviders = [];
        $this->loadedDeferred = [];
    }

    /**
     * 获取已启动的提供者
     */
    public function getBootedProviders(): array
    {
        return array_keys($this->booted);
    }

    /**
     * 检查提供者是否已启动
     */
    public function isBooted(string $providerClass): bool
    {
        return isset($this->booted[$providerClass]);
    }
}
