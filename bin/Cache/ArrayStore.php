<?php

declare(strict_types=1);

namespace Bin\Cache;

use Closure;
use DateInterval;

/**
 * 数组缓存存储（请求级别缓存）
 */
class ArrayStore implements CacheRepository
{
    /**
     * 缓存数据
     * @var array<string, mixed>
     */
    protected array $cache = [];

    /**
     * 过期时间
     * @var array<string, int|null>
     */
    protected array $expires = [];

    /**
     * 获取缓存项
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->has($key)) {
            return $default;
        }

        return $this->cache[$key];
    }

    /**
     * 设置缓存项
     */
    public function set(string $key, mixed $value, int|DateInterval|null $ttl = null): bool
    {
        $this->cache[$key] = $value;

        if ($ttl !== null) {
            $this->expires[$key] = $this->calculateExpiration($ttl);
        }

        return true;
    }

    /**
     * 删除缓存项
     */
    public function delete(string $key): bool
    {
        unset($this->cache[$key], $this->expires[$key]);

        return true;
    }

    /**
     * 清空所有缓存
     */
    public function clear(): bool
    {
        $this->cache = [];
        $this->expires = [];

        return true;
    }

    /**
     * 获取多个缓存项
     */
    public function getMultiple(array $keys, mixed $default = null): array
    {
        $results = [];

        foreach ($keys as $key) {
            $results[$key] = $this->get($key, $default);
        }

        return $results;
    }

    /**
     * 设置多个缓存项
     */
    public function setMultiple(array $values, int|DateInterval|null $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }

        return true;
    }

    /**
     * 删除多个缓存项
     */
    public function deleteMultiple(array $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    /**
     * 检查缓存项是否存在
     */
    public function has(string $key): bool
    {
        if (!isset($this->cache[$key])) {
            return false;
        }

        // 检查是否过期
        if (isset($this->expires[$key]) && $this->expires[$key] < time()) {
            unset($this->cache[$key], $this->expires[$key]);
            return false;
        }

        return true;
    }

    /**
     * 获取并删除缓存项
     */
    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);

        $this->delete($key);

        return $value;
    }

    /**
     * 不存在时存储并返回
     */
    public function remember(string $key, int|DateInterval|Closure|null $ttl, Closure $callback): mixed
    {
        if ($this->has($key)) {
            return $this->get($key);
        }

        $value = $callback();

        $this->set($key, $value, $ttl instanceof Closure ? null : $ttl);

        return $value;
    }

    /**
     * 获取或设置
     */
    public function getOrSet(string $key, mixed $value, int|DateInterval|null $ttl = null): mixed
    {
        if ($this->has($key)) {
            return $this->get($key);
        }

        if ($value instanceof \Closure) {
            $value = $value();
        }

        $this->set($key, $value, $ttl);

        return $value;
    }

    /**
     * 计算过期时间
     */
    protected function calculateExpiration(int|DateInterval $ttl): int
    {
        if ($ttl instanceof DateInterval) {
            $now = new \DateTime();
            $now->add($ttl);
            return $now->getTimestamp();
        }

        return time() + $ttl;
    }

    /**
     * 永久存储
     */
    public function forever(string $key, mixed $value): bool
    {
        return $this->set($key, $value, null);
    }

    /**
     * 增加缓存值
     */
    public function increment(string $key, int $value = 1): int
    {
        $current = $this->get($key, 0);

        if (!is_numeric($current)) {
            return false;
        }

        $newValue = (int) $current + $value;

        $this->set($key, $newValue);

        return $newValue;
    }

    /**
     * 减少缓存值
     */
    public function decrement(string $key, int $value = 1): int
    {
        return $this->increment($key, -$value);
    }
}
