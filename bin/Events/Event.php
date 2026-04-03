<?php

declare(strict_types=1);

namespace Bin\Events;

/**
 * 事件基类
 *
 * 可选的事件对象封装。继承此类创建类型安全的事件对象。
 * 分发时会自动用类名作为事件名。
 */
class Event
{
    /**
     * 是否停止传播
     */
    protected bool $propagationStopped = false;

    /**
     * 停止事件传播
     */
    public function stopPropagation(): self
    {
        $this->propagationStopped = true;
        return $this;
    }

    /**
     * 是否已停止传播
     */
    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }
}
