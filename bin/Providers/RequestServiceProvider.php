<?php

declare(strict_types=1);

namespace Bin\Providers;

use Bin\Request\Request;

/**
 * 请求服务提供者
 */
class RequestServiceProvider extends ServiceProvider
{
    /**
     * 注册请求服务
     */
    public function register(): void
    {
        $this->singleton('request', Request::class);
        $this->alias(Request::class, 'request');
    }

    /**
     * 提供的服务
     */
    public function provides(): array
    {
        return ['request', Request::class];
    }
}
