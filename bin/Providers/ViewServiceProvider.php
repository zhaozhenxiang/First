<?php

declare(strict_types=1);

namespace Bin\Providers;

use Bin\View\View;

/**
 * 视图服务提供者
 */
class ViewServiceProvider extends ServiceProvider
{
    /**
     * 注册视图服务
     */
    public function register(): void
    {
        $this->singleton('view', View::class);
        $this->alias(View::class, 'view');

        // 注册视图编译器
        $this->bind('view.compiler', function () {
            return new \Bin\View\Compiler();
        });
    }

    /**
     * 启动视图服务
     */
    public function boot(): void
    {
        // 设置视图路径
        $view = $this->app->make('view');
        if (!defined('VIEW_PATH')) {
            define('VIEW_PATH', basePath('/views'));
        }
    }

    /**
     * 提供的服务
     */
    public function provides(): array
    {
        return ['view', 'view.compiler', View::class];
    }
}
