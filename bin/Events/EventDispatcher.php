<?php

declare(strict_types=1);

namespace Bin\Events;

use Bin\Container\Container;

/**
 * 通用事件分发器
 *
 * 参考 Laravel Events 实现，支持：
 * - 闭包/Class@method/可调用类 监听器
 * - 通配符事件匹配
 * - 停止传播
 * - 事件对象
 * - 订阅者模式
 */
class EventDispatcher
{
    /**
     * 已注册的监听器
     * @var array<string, array<int, callable>>
     */
    protected array $listeners = [];

    /**
     * 通配符监听器
     * @var array<int, callable>
     */
    protected array $wildcardListeners = [];

    /**
     * IoC 容器实例
     */
    protected ?Container $container = null;

    /**
     * 注册事件监听器
     */
    public function listen(string $event, callable|string|array $listener): void
    {
        if ($event === '*') {
            $this->wildcardListeners[] = $this->makeListener($listener, true);
            return;
        }

        if (!isset($this->listeners[$event])) {
            $this->listeners[$event] = [];
        }

        $this->listeners[$event][] = $this->makeListener($listener, false);
    }

    /**
     * 分发事件
     *
     * @param string|object $event 事件名或事件对象
     * @param array $payload 事件数据（事件对象时忽略）
     * @return array|null 监听器返回值数组，或被停止时返回 null
     */
    public function dispatch(string|object $event, array $payload = []): ?array
    {
        if (is_object($event)) {
            $payload = [$event];
            $eventName = get_class($event);
        } else {
            $eventName = $event;
        }

        $responses = [];

        // 1. 触发具体事件监听器
        if (isset($this->listeners[$eventName])) {
            foreach ($this->listeners[$eventName] as $listener) {
                $response = $listener($eventName, $payload);

                if ($response === false) {
                    return null;
                }

                if ($response !== null) {
                    $responses[] = $response;
                }
            }
        }

        // 2. 触发通配符监听器
        foreach ($this->wildcardListeners as $listener) {
            $response = $listener($eventName, $payload);

            if ($response === false) {
                return null;
            }

            if ($response !== null) {
                $responses[] = $response;
            }
        }

        return $responses;
    }

    /**
     * 移除事件的所有监听器
     */
    public function forget(string $event): void
    {
        unset($this->listeners[$event]);
    }

    /**
     * 移除所有监听器
     */
    public function forgetAll(): void
    {
        $this->listeners = [];
        $this->wildcardListeners = [];
    }

    /**
     * 检查是否有监听器
     */
    public function hasListeners(string $event): bool
    {
        if ($event === '*') {
            return !empty($this->wildcardListeners);
        }

        return !empty($this->listeners[$event]);
    }

    /**
     * 获取监听器
     */
    public function getListeners(?string $event = null): array
    {
        if ($event === null) {
            return $this->listeners;
        }

        return $this->listeners[$event] ?? [];
    }

    /**
     * 直接设置已包装的监听器（跳过 makeListener，用于 withoutEvents 恢复）
     */
    public function setListeners(array $listeners): void
    {
        $this->listeners = $listeners;
    }

    /**
     * 直接设置已包装的通配符监听器
     */
    public function setWildcardListeners(array $listeners): void
    {
        $this->wildcardListeners = $listeners;
    }

    /**
     * 获取通配符监听器
     */
    public function getWildcardListeners(): array
    {
        return $this->wildcardListeners;
    }

    /**
     * 注册订阅者
     */
    public function subscribe(Subscriber $subscriber): void
    {
        $subscriber->subscribe($this);
    }

    /**
     * 设置 IoC 容器
     */
    public function setContainer(Container $container): self
    {
        $this->container = $container;
        return $this;
    }

    /**
     * 获取 IoC 容器
     */
    public function getContainer(): ?Container
    {
        return $this->container;
    }

    /**
     * 创建监听器闭包
     *
     * @param callable|string|array $listener 原始监听器
     * @param bool $isWildcard 是否是通配符监听器
     * @return callable 签名 (string $eventName, array $payload): mixed
     */
    protected function makeListener(callable|string|array $listener, bool $isWildcard = false): callable
    {
        $resolver = $this->createResolver($listener);

        return function (string $eventName, array $payload) use ($resolver, $isWildcard) {
            if ($isWildcard) {
                // 通配符监听器接收 ($eventName, ...$payload)
                return $resolver(array_merge([$eventName], $payload));
            }

            // 普通监听器接收 (...$payload)
            return $resolver($payload);
        };
    }

    /**
     * 创建监听器调用器
     *
     * @return callable(array $args): mixed
     */
    protected function createResolver(callable|string|array $listener): callable
    {
        // 闭包
        if ($listener instanceof \Closure) {
            return function (array $args) use ($listener) {
                return $listener(...$args);
            };
        }

        // 数组格式 [$object, 'method']
        if (is_array($listener)) {
            return function (array $args) use ($listener) {
                return call_user_func_array($listener, $args);
            };
        }

        // Class@method 字符串
        if (is_string($listener) && str_contains($listener, '@')) {
            return function (array $args) use ($listener) {
                [$class, $method] = explode('@', $listener, 2);
                $instance = $this->resolveClass($class);
                return $instance->$method(...$args);
            };
        }

        // 可调用类名字符串（__invoke）
        if (is_string($listener)) {
            return function (array $args) use ($listener) {
                $instance = $this->resolveClass($listener);
                return $instance(...$args);
            };
        }

        // 其他 callable
        return function (array $args) use ($listener) {
            return call_user_func_array($listener, $args);
        };
    }

    /**
     * 解析类实例
     */
    protected function resolveClass(string $class): object
    {
        if ($this->container !== null) {
            return $this->container->make($class);
        }

        return new $class();
    }
}
