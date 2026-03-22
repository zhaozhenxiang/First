<?php

declare(strict_types=1);

namespace Bin\Cache;

/**
 * 缓存管理器
 */
class CacheManager
{
    /**
     * 缓存存储实例
     * @var array<string, CacheRepository>
     */
    protected static array $stores = [];

    /**
     * 默认存储名称
     */
    protected static string $defaultStore = 'file';

    /**
     * 存储配置
     * @var array<string, mixed>
     */
    protected static array $config = [];

    /**
     * 设置配置
     */
    public static function setConfig(array $config): void
    {
        self::$config = $config;
    }

    /**
     * 获取缓存存储
     */
    public static function store(?string $name = null): CacheRepository
    {
        $name = $name ?? self::$defaultStore;

        if (!isset(self::$stores[$name])) {
            self::$stores[$name] = self::resolveStore($name);
        }

        return self::$stores[$name];
    }

    /**
     * 解析缓存存储
     */
    protected static function resolveStore(string $name): CacheRepository
    {
        $config = self::$config[$name] ?? [];

        $driver = $config['driver'] ?? $name;

        return match ($driver) {
            'file' => new FileStore($config['path'] ?? null),
            'redis' => self::createRedisStore($config),
            'array' => new ArrayStore(),
            'null' => new NullStore(),
            default => throw new \RuntimeException("Unsupported cache driver: {$driver}"),
        };
    }

    /**
     * 创建 Redis 存储
     */
    protected static function createRedisStore(array $config): CacheRepository
    {
        // Redis 存储需要 Redis 扩展
        if (!extension_loaded('redis')) {
            throw new \RuntimeException('Redis extension is not loaded.');
        }

        return new RedisStore(
            $config['host'] ?? '127.0.0.1',
            $config['port'] ?? 6379,
            $config['password'] ?? null,
            $config['database'] ?? 0
        );
    }

    /**
     * 设置默认存储
     */
    public static function setDefaultStore(string $name): void
    {
        self::$defaultStore = $name;
    }

    /**
     * 获取默认存储名称
     */
    public static function getDefaultStore(): string
    {
        return self::$defaultStore;
    }

    /**
     * 清除所有存储实例
     */
    public static function flush(): void
    {
        self::$stores = [];
    }

    /**
     * 快捷方法 - 获取
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return self::store()->get($key, $default);
    }

    /**
     * 快捷方法 - 设置
     */
    public static function set(string $key, mixed $value, int $ttl = null): bool
    {
        return self::store()->set($key, $value, $ttl);
    }

    /**
     * 快捷方法 - 删除
     */
    public static function delete(string $key): bool
    {
        return self::store()->delete($key);
    }

    /**
     * 快捷方法 - 记住
     */
    public static function remember(string $key, int $ttl, \Closure $callback): mixed
    {
        return self::store()->remember($key, $ttl, $callback);
    }

    /**
     * 快捷方法 - 永久存储
     */
    public static function forever(string $key, mixed $value): bool
    {
        return self::store()->forever($key, $value);
    }

    /**
     * 快捷方法 - 清空
     */
    public static function clear(): bool
    {
        return self::store()->clear();
    }

    /**
     * 快捷方法 - 检查存在
     */
    public static function has(string $key): bool
    {
        return self::store()->has($key);
    }
}
