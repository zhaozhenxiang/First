<?php

declare(strict_types=1);

namespace Bin\Cache;

use Closure;
use DateInterval;

/**
 * Redis 缓存存储
 */
class RedisStore implements CacheRepository
{
    /**
     * Redis 连接
     */
    protected mixed $redis;

    /**
     * 键前缀
     */
    protected string $prefix = 'cache:';

    /**
     * 构造函数
     */
    public function __construct(
        string $host = '127.0.0.1',
        int $port = 6379,
        ?string $password = null,
        int $database = 0
    ) {
        if (!extension_loaded('redis')) {
            throw new \RuntimeException('Redis extension is not loaded.');
        }

        $this->redis = new \Redis();
        $this->redis->connect($host, $port);

        if ($password !== null) {
            $this->redis->auth($password);
        }

        if ($database !== 0) {
            $this->redis->select($database);
        }
    }

    /**
     * 获取缓存项
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->redis->get($this->prefix . $key);

        if ($value === false || $value === null) {
            return $default;
        }

        return unserialize($value);
    }

    /**
     * 设置缓存项
     */
    public function set(string $key, mixed $value, int|DateInterval|null $ttl = null): bool
    {
        $serialized = serialize($value);
        $redisKey = $this->prefix . $key;

        if ($ttl !== null) {
            $ttl = $this->calculateExpiration($ttl);

            return $this->redis->setex($redisKey, $ttl, $serialized);
        }

        return $this->redis->set($redisKey, $serialized) !== false;
    }

    /**
     * 删除缓存项
     */
    public function delete(string $key): bool
    {
        return $this->redis->del($this->prefix . $key) > 0;
    }

    /**
     * 清空所有缓存
     */
    public function clear(): bool
    {
        // 只删除带有前缀的键
        $keys = $this->redis->keys($this->prefix . '*');

        if (empty($keys)) {
            return true;
        }

        return $this->redis->del($keys) > 0;
    }

    /**
     * 获取多个缓存项
     */
    public function getMultiple(array $keys, mixed $default = null): array
    {
        $redisKeys = array_map(fn($k) => $this->prefix . $k, $keys);
        $values = $this->redis->mget($redisKeys);

        $results = [];

        foreach ($keys as $i => $key) {
            if ($values[$i] === false || $values[$i] === null) {
                $results[$key] = $default;
            } else {
                $results[$key] = unserialize($values[$i]);
            }
        }

        return $results;
    }

    /**
     * 设置多个缓存项
     */
    public function setMultiple(array $values, int|DateInterval|null $ttl = null): bool
    {
        $success = true;

        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * 删除多个缓存项
     */
    public function deleteMultiple(array $keys): bool
    {
        $redisKeys = array_map(fn($k) => $this->prefix . $k, $keys);

        return $this->redis->del($redisKeys) > 0;
    }

    /**
     * 检查缓存项是否存在
     */
    public function has(string $key): bool
    {
        return $this->redis->exists($this->prefix . $key) > 0;
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
        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        // 如果 ttl 是闭包，执行它获取值和 TTL
        if ($ttl instanceof Closure) {
            $result = $ttl();
            $ttl = null;
        } else {
            $result = $callback();
        }

        $this->set($key, $result, $ttl);

        return $result;
    }

    /**
     * 获取或设置
     */
    public function getOrSet(string $key, mixed $value, int|DateInterval|null $ttl = null): mixed
    {
        if ($this->has($key)) {
            return $this->get($key);
        }

        // 如果 value 是闭包，执行它
        if ($value instanceof \Closure) {
            $value = $value();
        }

        $this->set($key, $value, $ttl);

        return $value;
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
    public function increment(string $key, int $value = 1): int|false
    {
        $result = $this->redis->incrBy($this->prefix . $key, $value);

        if ($result === false) {
            return false;
        }

        return (int) $result;
    }

    /**
     * 减少缓存值
     */
    public function decrement(string $key, int $value = 1): int|false
    {
        return $this->increment($key, -$value);
    }

    /**
     * 计算过期时间（秒）
     */
    protected function calculateExpiration(int|DateInterval $ttl): int
    {
        if ($ttl instanceof DateInterval) {
            $now = new \DateTime();
            $now->add($ttl);
            return $now->getTimestamp() - time();
        }

        return $ttl;
    }

    /**
     * 获取 Redis 实例
     */
    public function getRedis(): mixed
    {
        return $this->redis;
    }
}
