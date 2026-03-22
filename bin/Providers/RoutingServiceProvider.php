<?php

declare(strict_types=1);

namespace Bin\Providers;

use Bin\Route\RouteCollection;

/**
 * 路由服务提供者
 */
class RoutingServiceProvider extends ServiceProvider
{
    /**
     * 注册路由服务
     */
    public function register(): void
    {
        // 注册路由集合单例
        $this->singleton('route', RouteCollection::class);
        $this->alias(RouteCollection::class, 'route');
    }

    /**
     * 启动路由服务
     */
    public function boot(): void
    {
        // 路由服务会在 app/routes.php 中被使用
    }

    /**
     * 提供的服务
     */
    public function provides(): array
    {
        return ['route', RouteCollection::class];
    }
}
