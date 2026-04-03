<?php

declare(strict_types=1);

namespace Bin\Events;

/**
 * 事件订阅者接口
 *
 * 实现此接口的类可以在 subscribe() 方法中
 * 批量注册一组相关的事件监听器
 */
interface Subscriber
{
    /**
     * 注册订阅者的事件监听器
     */
    public function subscribe(EventDispatcher $events): void;
}
