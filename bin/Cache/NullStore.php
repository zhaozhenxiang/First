<?php

declare(strict_types=1);

namespace Bin\Cache;

use Closure;
use DateInterval;

/**
 * 空缓存存储（不缓存任何内容）
 */
class NullStore implements CacheRepository
{
    /**
     * 获取缓存项（始终返回默认值）
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $default;
    }

    /**
     * 设置缓存项（不做任何操作）
     */
    public function set(string $key, mixed $value, int|DateInterval|null $ttl = null): bool
    {
        return true;
    }

    /**
     * 删除缓存项
     */
    public function delete(string $key): bool
    {
        return true;
    }

    /**
     * 清空所有缓存
     */
    public function clear(): bool
    {
        return true;
    }

    /**
     * 获取多个缓存项
     */
    public function getMultiple(array $keys, mixed $default = null): array
    {
        $results = [];

        foreach ($keys as $key) {
            $results[$key] = $default;
        }

        return $results;
    }

    /**
     * 设置多个缓存项
     */
    public function setMultiple(array $values, int|DateInterval|null $ttl = null): bool
    {
        return true;
    }

    /**
     * 删除多个缓存项
     */
    public function deleteMultiple(array $keys): bool
    {
        return true;
    }

    /**
     * 检查缓存项是否存在
     */
    public function has(string $key): bool
    {
        return false;
    }

    /**
     * 获取并删除缓存项
     */
    public function pull(string $key, mixed $default = null): mixed
    {
        return $default;
    }

    /**
     * 不存在时存储并返回
     */
    public function remember(string $key, int|DateInterval|Closure|null $ttl, Closure $callback): mixed
    {
        return $callback();
    }

    /**
     * 获取或设置
     */
    public function getOrSet(string $key, mixed $value, int|DateInterval|null $ttl = null): mixed
    {
        if ($value instanceof \Closure) {
            return $value();
        }

        return $value;
    }

    /**
     * 永久存储
     */
    public function forever(string $key, mixed $value): bool
    {
        return true;
    }

    /**
     * 增加缓存值
     */
    public function increment(string $key, int $value = 1): int|false
    {
        return false;
    }

    /**
     * 减少缓存值
     */
    public function decrement(string $key, int $value = 1): int|false
    {
        return false;
    }
}
