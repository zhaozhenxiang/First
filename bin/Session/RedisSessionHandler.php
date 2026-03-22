<?php

declare(strict_types=1);

namespace Bin\Session;

use Redis;
use RedisException;
use RuntimeException;
use \SessionHandlerInterface;

/**
 * Redis Session 处理器
 */
class RedisSessionHandler implements \SessionHandlerInterface
{
    /** @var Redis Redis 连接 */
    private Redis $redis;

    /** @var string 键前缀 */
    private string $prefix;

    /** @var int Session 生命周期（秒） */
    private int $lifetime;

    /**
     * 构造函数
     */
    public function __construct(
        ?Redis $redis = null,
        string $prefix = 'session:',
        int $minutes = 120
    ) {
        if ($redis === null) {
            if (!extension_loaded('redis')) {
                throw new RuntimeException('Redis extension is not loaded.');
            }
            $redis = new Redis();
        }

        $this->redis = $redis;
        $this->prefix = $prefix;
        $this->lifetime = $minutes * 60;
    }

    /**
     * 打开 Session
     */
    public function open(string $path, string $name): bool
    {
        return true;
    }

    /**
     * 关闭 Session
     */
    public function close(): bool
    {
        return true;
    }

    /**
     * 读取 Session 数据
     */
    public function read(string $id): string
    {
        try {
            $key = $this->getPrefix() . $id;
            $data = $this->redis->get($key);

            if ($data === false || $data === null) {
                return '';
            }

            return $data;
        } catch (RedisException $e) {
            return '';
        }
    }

    /**
     * 写入 Session 数据
     */
    public function write(string $id, string $data): bool
    {
        try {
            $key = $this->getPrefix() . $id;
            $result = $this->redis->setex($key, $this->lifetime, $data);

            return $result !== false;
        } catch (RedisException $e) {
            return false;
        }
    }

    /**
     * 销毁 Session
     */
    public function destroy(string $id): bool
    {
        try {
            $key = $this->getPrefix() . $id;
            return $this->redis->del($key) > 0;
        } catch (RedisException $e) {
            return false;
        }
    }

    /**
     * 垃圾回收（Redis 通过 TTL 自动处理）
     */
    public function gc(int $max_lifetime): int
    {
        // Redis 通过 TTL 自动处理过期
        return 0;
    }

    /**
     * 获取 Redis 实例
     */
    public function getRedis(): Redis
    {
        return $this->redis;
    }

    /**
     * 获取键前缀
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /**
     * 设置键前缀
     */
    public function setPrefix(string $prefix): void
    {
        $this->prefix = $prefix;
    }

    /**
     * 获取活跃 Session 数量
     */
    public function countActive(): int
    {
        try {
            $pattern = $this->prefix . '*';
            $keys = $this->redis->keys($pattern);

            return count($keys);
        } catch (RedisException $e) {
            return 0;
        }
    }

    /**
     * 清空所有 Session
     */
    public function clear(): bool
    {
        try {
            $pattern = $this->prefix . '*';
            $keys = $this->redis->keys($pattern);

            if (!empty($keys)) {
                return $this->redis->del($keys) > 0;
            }

            return true;
        } catch (RedisException $e) {
            return false;
        }
    }

    /**
     * 获取 Session 的剩余时间（秒）
     */
    public function getTtl(string $id): int
    {
        try {
            $key = $this->prefix . $id;
            return $this->redis->ttl($key);
        } catch (RedisException $e) {
            return -1;
        }
    }

    /**
     * 刷新 Session 过期时间
     */
    public function refresh(string $id): bool
    {
        try {
            $key = $this->prefix . $id;
            return $this->redis->expire($key, $this->lifetime);
        } catch (RedisException $e) {
            return false;
        }
    }
}
