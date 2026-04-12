<?php

declare(strict_types=1);

namespace Bin\Database\Model;

use Bin\Database\ModelEventDispatcher;
use Bin\Database\Observer;
use Bin\Events\EventDispatcher;

trait HasEvents
{
    /**
     * 触发模型事件（公开接口）
     */
    public function fireModelEvent(string $event): mixed
    {
        return ModelEventDispatcher::dispatchForModel(static::class, $event, $this);
    }

    /**
     * 注册创建事件监听器
     */
    public static function creating(callable $callback): void
    {
        ModelEventDispatcher::listen(static::class . '@creating', $callback);
    }

    /**
     * 注册创建后事件监听器
     */
    public static function created(callable $callback): void
    {
        ModelEventDispatcher::listen(static::class . '@created', $callback);
    }

    /**
     * 注册更新事件监听器
     */
    public static function updating(callable $callback): void
    {
        ModelEventDispatcher::listen(static::class . '@updating', $callback);
    }

    /**
     * 注册更新后事件监听器
     */
    public static function updated(callable $callback): void
    {
        ModelEventDispatcher::listen(static::class . '@updated', $callback);
    }

    /**
     * 注册保存事件监听器
     */
    public static function saving(callable $callback): void
    {
        ModelEventDispatcher::listen(static::class . '@saving', $callback);
    }

    /**
     * 注册保存后事件监听器
     */
    public static function saved(callable $callback): void
    {
        ModelEventDispatcher::listen(static::class . '@saved', $callback);
    }

    /**
     * 注册删除事件监听器
     */
    public static function deleting(callable $callback): void
    {
        ModelEventDispatcher::listen(static::class . '@deleting', $callback);
    }

    /**
     * 注册删除后事件监听器
     */
    public static function deleted(callable $callback): void
    {
        ModelEventDispatcher::listen(static::class . '@deleted', $callback);
    }

    /**
     * 注册恢复事件监听器
     */
    public static function restoring(callable $callback): void
    {
        ModelEventDispatcher::listen(static::class . '@restoring', $callback);
    }

    /**
     * 注册恢复后事件监听器
     */
    public static function restored(callable $callback): void
    {
        ModelEventDispatcher::listen(static::class . '@restored', $callback);
    }

    /**
     * 注册获取事件监听器
     */
    public static function retrieved(callable $callback): void
    {
        ModelEventDispatcher::listen(static::class . '@retrieved', $callback);
    }

    /**
     * 清除模型的所有事件监听器
     */
    public static function flushEventListeners(): void
    {
        $events = ['creating', 'created', 'updating', 'updated', 'saving', 'saved', 'deleting', 'deleted', 'restoring', 'restored', 'retrieved'];

        foreach ($events as $event) {
            ModelEventDispatcher::forget(static::class . '@' . $event);
        }
    }

    /**
     * 注册模型观察者
     */
    public static function observe(object|string $class): void
    {
        $instance = is_object($class) ? $class : new $class();

        foreach (Observer::EVENTS as $event) {
            if (method_exists($instance, $event)) {
                ModelEventDispatcher::listen(static::class . '@' . $event, fn($model) => $instance->$event($model));
            }
        }
    }

    /**
     * 在不触发事件的情况下执行回调
     */
    public static function withoutEvents(callable $callback): mixed
    {
        $dispatcher = self::getEventDispatcher();

        $listeners = $dispatcher->getListeners();
        $wildcards = $dispatcher->getWildcardListeners();

        try {
            $dispatcher->forgetAll();

            return $callback();
        } finally {
            $dispatcher->setListeners($listeners);
            $dispatcher->setWildcardListeners($wildcards);
        }
    }

    /**
     * 获取 EventDispatcher 实例
     */
    protected static function getEventDispatcher(): EventDispatcher
    {
        $app = \Bin\App\App::getInstance();

        try {
            return $app->make('events');
        } catch (\Throwable) {
                return new EventDispatcher();
            }
    }
}
