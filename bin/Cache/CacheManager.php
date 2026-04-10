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
    protected array $stores = [];

    /**
     * 默认存储名称
     */
    protected string $defaultStore = '';

    /**
     * 存储配置
     * @var array<string, mixed>
     */
    protected array $config = [];

    /** @var self|null 单例实例 */
    private static ?self $instance = null;

    public function __construct()
    {
        // 自动从 config 加载（如果可用）
        if ($this->config === [] && function_exists('config')) {
            $this->config = config('cache.stores') ?? [];
            if ($this->defaultStore === '') {
                $this->defaultStore = config('cache.default') ?? 'file';
            }
        }
        if ($this->defaultStore === '') {
            $this->defaultStore = 'file';
        }
    }

    /**
     * 获取单例实例
     */
    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * 重置单例（用于测试）
     */
    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    /**
     * 设置配置
     */
    public function setConfigFor(array $config): void
    {
        $this->config = $config;
    }

    /**
     * 获取缓存存储
     */
    public function storeFor(?string $name = null): CacheRepository
    {
        $name = $name ?? $this->defaultStore;

        if (!isset($this->stores[$name])) {
            $this->stores[$name] = $this->resolveStore($name);
        }

        return $this->stores[$name];
    }

    /**
     * 解析缓存存储
     */
    protected function resolveStore(string $name): CacheRepository
    {
        // 优先从 stores 子数组查找，兼容旧格式直接以 name 为 key
        $config = $this->config[$name] ?? [];
        if ($config === [] && isset($this->config['stores'][$name])) {
            $config = $this->config['stores'][$name];
        }

        $driver = $config['driver'] ?? $name;

        return match ($driver) {
            'file' => new FileStore($config['path'] ?? null),
            'redis' => $this->createRedisStore($config),
            'array' => new ArrayStore(),
            'null' => new NullStore(),
            default => throw new \RuntimeException("Unsupported cache driver: {$driver}"),
        };
    }

    /**
     * 创建 Redis 存储
     */
    protected function createRedisStore(array $config): CacheRepository
    {
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
    public function setDefaultStoreFor(string $name): void
    {
        $this->defaultStore = $name;
    }

    /**
     * 获取默认存储名称
     */
    public function getDefaultStoreFor(): string
    {
        return $this->defaultStore;
    }

    /**
     * 清除所有存储实例
     */
    public function flushFor(): void
    {
        $this->stores = [];
    }

    // ─── @deprecated 静态兼容层 ───────────────────────────

    /**
     * @deprecated 使用 CacheManager::getInstance()->setConfigFor()
     */
    public static function setConfig(array $config): void
    {
        self::getInstance()->setConfigFor($config);
    }

    /**
     * @deprecated 使用 CacheManager::getInstance()->storeFor()
     */
    public static function store(?string $name = null): CacheRepository
    {
        return self::getInstance()->storeFor($name);
    }

    /**
     * @deprecated 使用 CacheManager::getInstance()->setDefaultStoreFor()
     */
    public static function setDefaultStore(string $name): void
    {
        self::getInstance()->setDefaultStoreFor($name);
    }

    /**
     * @deprecated 使用 CacheManager::getInstance()->getDefaultStoreFor()
     */
    public static function getDefaultStore(): string
    {
        return self::getInstance()->getDefaultStoreFor();
    }

    /**
     * @deprecated 使用 CacheManager::getInstance()->flushFor()
     */
    public static function flush(): void
    {
        self::getInstance()->flushFor();
    }

    /**
     * @deprecated 使用 CacheManager::getInstance()->storeFor()->get()
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return self::getInstance()->storeFor()->get($key, $default);
    }

    /**
     * @deprecated 使用 CacheManager::getInstance()->storeFor()->set()
     */
    public static function set(string $key, mixed $value, int $ttl = null): bool
    {
        return self::getInstance()->storeFor()->set($key, $value, $ttl);
    }

    /**
     * @deprecated 使用 CacheManager::getInstance()->storeFor()->delete()
     */
    public static function delete(string $key): bool
    {
        return self::getInstance()->storeFor()->delete($key);
    }

    /**
     * @deprecated 使用 CacheManager::getInstance()->storeFor()->remember()
     */
    public static function remember(string $key, int $ttl, \Closure $callback): mixed
    {
        return self::getInstance()->storeFor()->remember($key, $ttl, $callback);
    }

    /**
     * @deprecated 使用 CacheManager::getInstance()->storeFor()->forever()
     */
    public static function forever(string $key, mixed $value): bool
    {
        return self::getInstance()->storeFor()->forever($key, $value);
    }

    /**
     * @deprecated 使用 CacheManager::getInstance()->storeFor()->clear()
     */
    public static function clear(): bool
    {
        return self::getInstance()->storeFor()->clear();
    }

    /**
     * @deprecated 使用 CacheManager::getInstance()->storeFor()->has()
     */
    public static function has(string $key): bool
    {
        return self::getInstance()->storeFor()->has($key);
    }
}
