<?php

declare(strict_types=1);

namespace Bin\Providers;

use Bin\Events\EventDispatcher;

/**
 * 事件服务提供者
 */
class EventServiceProvider extends ServiceProvider
{
    /**
     * 注册事件服务
     */
    public function register(): void
    {
        $this->singleton(EventDispatcher::class, function () {
            $dispatcher = new EventDispatcher();
            $dispatcher->setContainer($this->app->getContainer());
            return $dispatcher;
        });

        $this->alias(EventDispatcher::class, 'events');
    }

    /**
     * 提供的服务
     */
    public function provides(): array
    {
        return [
            EventDispatcher::class,
            'events',
        ];
    }
}
