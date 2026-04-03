<?php

declare(strict_types=1);

namespace Bin\Facade;

use Bin\Events\EventDispatcher;

/**
 * Event Facade - 静态代理事件分发器
 *
 * @method static void listen(string $event, callable|string|array $listener)
 * @method static array|null dispatch(string|object $event, array $payload = [])
 * @method static void forget(string $event)
 * @method static void forgetAll()
 * @method static bool hasListeners(string $event)
 * @method static void subscribe(\Bin\Events\Subscriber $subscriber)
 */
class Event extends Facade
{
    /**
     * 获取 Facade 代理的类名
     */
    protected function getClassName(): string
    {
        return EventDispatcher::class;
    }
}
