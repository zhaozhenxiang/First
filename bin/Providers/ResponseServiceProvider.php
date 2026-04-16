<?php

declare(strict_types=1);

namespace Bin\Providers;

use Bin\Response\Response;

/**
 * 响应服务提供者
 */
class ResponseServiceProvider extends ServiceProvider
{
    /**
     * 注册响应服务
     */
    public function register(): void
    {
        $this->singleton(\Bin\Response\ResponseFactory::class, \Bin\Response\ResponseFactory::class);
        $this->alias(\Bin\Response\ResponseFactory::class, 'response.factory');

        $this->bind('response', Response::class);
        $this->alias(Response::class, 'response');
    }

    /**
     * 提供的服务
     */
    public function provides(): array
    {
        return ['response', Response::class, 'response.factory', \Bin\Response\ResponseFactory::class];
    }
}
