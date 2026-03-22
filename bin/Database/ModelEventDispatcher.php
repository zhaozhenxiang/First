<?php

declare(strict_types=1);

namespace Bin\Database;

/**
 * 模型事件调度器
 *
 * 支持的事件:
 * - retrieved: 从数据库获取模型后
 * - creating: 创建模型前
 * - created: 创建模型后
 * - updating: 更新模型前
 * - updated: 更新模型后
 * - saving: 保存模型前（创建或更新）
 * - saved: 保存模型后（创建或更新）
 * - deleting: 删除模型前
 * - deleted: 删除模型后
 * - restoring: 恢复模型前
 * - restored: 恢复模型后
 */
class ModelEventDispatcher
{
    /**
     * 注册的事件监听器
     */
    protected static array $listeners = [];

    /**
     * 注册事件监听器
     */
    public static function listen(string $event, callable $callback): void
    {
        if (!isset(self::$listeners[$event])) {
            self::$listeners[$event] = [];
        }

        self::$listeners[$event][] = $callback;
    }

    /**
     * 触发事件
     */
    public static function dispatch(string $event, Model $model): mixed
    {
        $result = true;

        if (!isset(self::$listeners[$event])) {
            return $result;
        }

        foreach (self::$listeners[$event] as $callback) {
            $callbackResult = $callback($model);

            // 如果回调返回 false，则停止事件传播
            if ($callbackResult === false) {
                $result = false;
                break;
            }
        }

        return $result;
    }

    /**
     * 触发模型类的事件
     */
    public static function dispatchForModel(string $modelClass, string $event, Model $model): mixed
    {
        $result = true;

        // 触发特定模型类的监听器
        $key = $modelClass . '@' . $event;
        if (isset(self::$listeners[$key])) {
            foreach (self::$listeners[$key] as $callback) {
                $callbackResult = $callback($model);
                if ($callbackResult === false) {
                    $result = false;
                }
            }
        }

        // 触发全局事件监听器
        if ($result !== false && isset(self::$listeners[$event])) {
            foreach (self::$listeners[$event] as $callback) {
                $callbackResult = $callback($model);
                if ($callbackResult === false) {
                    $result = false;
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * 移除事件监听器
     */
    public static function forget(string $event): void
    {
        unset(self::$listeners[$event]);
    }

    /**
     * 移除所有事件监听器
     */
    public static function forgetAll(): void
    {
        self::$listeners = [];
    }

    /**
     * 检查是否有事件监听器
     */
    public static function hasListeners(string $event): bool
    {
        return isset(self::$listeners[$event]) && !empty(self::$listeners[$event]);
    }

    /**
     * 获取所有监听器
     */
    public static function getListeners(?string $event = null): array
    {
        if ($event !== null) {
            return self::$listeners[$event] ?? [];
        }

        return self::$listeners;
    }
}