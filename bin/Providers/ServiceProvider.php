<?php

declare(strict_types=1);

namespace Bin\Providers;

use Bin\App\App;

/**
 * 服务提供者基类
 *
 * Service Provider 是组织服务注册的中心位置
 * 所有核心服务和第三方包服务都通过 Provider 注册
 */
abstract class ServiceProvider
{
    /**
     * 应用实例
     */
    protected App $app;

    /**
     * 构造函数
     */
    public function __construct(App $app)
    {
        $this->app = $app;
    }

    /**
     * 注册服务
     *
     * 在此方法中绑定服务到容器
     * 不应在此方法中解析其他服务（可能尚未注册）
     */
    abstract public function register(): void;

    /**
     * 启动服务
     *
     * 在所有服务注册完成后调用
     * 可以在此方法中解析其他服务或注册事件监听器
     */
    public function boot(): void
    {
        // 默认空实现
    }

    /**
     * 获取提供者提供的服务
     *
     * 返回此提供者注册的服务名数组
     * 用于延迟加载服务提供者
     */
    public function provides(): array
    {
        return [];
    }

    /**
     * 判断是否延迟加载
     */
    public function isDeferred(): bool
    {
        return count($this->provides()) > 0;
    }

    /**
     * 注册单例
     */
    protected function singleton(string $abstract, callable|string $concrete = null): void
    {
        $this->app->singleton($abstract, $concrete);
    }

    /**
     * 绑定服务
     */
    protected function bind(string $abstract, callable|string $concrete = null, bool $shared = false): void
    {
        $this->app->bind($abstract, $concrete, $shared);
    }

    /**
     * 绑定实例
     */
    protected function instance(string $abstract, object $instance): void
    {
        $this->app->instance($abstract, $instance);
    }

    /**
     * 绑定别名
     */
    protected function alias(string $abstract, string $alias): void
    {
        $this->app->alias($abstract, $alias);
    }

    /**
     * 上下文绑定
     */
    protected function contextual(string $concrete, string $abstract, callable $implementation): void
    {
        $this->app->contextual($concrete, $abstract, $implementation);
    }
}
