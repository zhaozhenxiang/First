<?php

declare(strict_types=1);

namespace Bin\Database;

use Bin\Events\EventDispatcher;

/**
 * 模型事件调度器
 *
 * 委托给通用 EventDispatcher，保持向后兼容。
 * 模型事件键格式：`ClassName@event`
 */
class ModelEventDispatcher
{
    /**
     * 获取 EventDispatcher 单例（延迟初始化）
     */
    protected static function getDispatcher(): EventDispatcher
    {
        static $dispatcher = null;

        if ($dispatcher === null) {
            // 尝试从容器解析
            try {
                $app = \Bin\App\App::getInstance();
                $dispatcher = $app->make('events');
            } catch (\Throwable) {
                // 容器不可用时创建独立实例
                $dispatcher = new EventDispatcher();
            }
        }

        return $dispatcher;
    }

    /**
     * 注册事件监听器
     */
    public static function listen(string $event, callable $callback): void
    {
        static::getDispatcher()->listen($event, function (Model $model) use ($callback) {
            return $callback($model);
        });
    }

    /**
     * 触发事件
     */
    public static function dispatch(string $event, Model $model): mixed
    {
        return static::getDispatcher()->dispatch($event, [$model]);
    }

    /**
     * 触发模型类的事件
     *
     * 先触发 model-specific 监听器（ClassName@event），
     * 再触发全局事件监听器（event）。
     * 任一监听器返回 false 时停止传播。
     */
    public static function dispatchForModel(string $modelClass, string $event, Model $model): mixed
    {
        $dispatcher = static::getDispatcher();

        // 1. 触发 model-specific 监听器
        $key = $modelClass . '@' . $event;
        $result = $dispatcher->dispatch($key, [$model]);

        if ($result === null) {
            return false;
        }

        // 2. 触发全局事件监听器
        $result = $dispatcher->dispatch($event, [$model]);

        if ($result === null) {
            return false;
        }

        return true;
    }

    /**
     * 移除事件监听器
     */
    public static function forget(string $event): void
    {
        static::getDispatcher()->forget($event);
    }

    /**
     * 移除所有事件监听器
     */
    public static function forgetAll(): void
    {
        static::getDispatcher()->forgetAll();
    }

    /**
     * 检查是否有事件监听器
     */
    public static function hasListeners(string $event): bool
    {
        return static::getDispatcher()->hasListeners($event);
    }

    /**
     * 获取所有监听器
     */
    public static function getListeners(?string $event = null): array
    {
        return static::getDispatcher()->getListeners($event);
    }
}
