<?php

declare(strict_types=1);

namespace Bin\Cache;

use Closure;
use DateInterval;

/**
 * 缓存仓库接口
 */
interface CacheRepository
{
    /**
     * 获取缓存项
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * 设置缓存项
     */
    public function set(string $key, mixed $value, int|DateInterval|null $ttl = null): bool;

    /**
     * 删除缓存项
     */
    public function delete(string $key): bool;

    /**
     * 清空所有缓存
     */
    public function clear(): bool;

    /**
     * 获取多个缓存项
     */
    public function getMultiple(array $keys, mixed $default = null): array;

    /**
     * 设置多个缓存项
     */
    public function setMultiple(array $values, int|DateInterval|null $ttl = null): bool;

    /**
     * 删除多个缓存项
     */
    public function deleteMultiple(array $keys): bool;

    /**
     * 检查缓存项是否存在
     */
    public function has(string $key): bool;

    /**
     * 获取并删除缓存项
     */
    public function pull(string $key, mixed $default = null): mixed;

    /**
     * 不存在时存储并返回
     */
    public function remember(string $key, int|DateInterval|Closure|null $ttl, Closure $callback): mixed;

    /**
     * 获取或设置
     */
    public function getOrSet(string $key, mixed $value, int|DateInterval|null $ttl = null): mixed;
}
