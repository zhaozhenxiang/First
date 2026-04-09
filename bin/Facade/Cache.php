<?php

declare(strict_types=1);

namespace Bin\Facade;

use Bin\Cache\CacheManager;

/**
 * Cache Facade - 静态代理缓存管理器
 *
 * @method static mixed get(string $key, mixed $default = null)
 * @method static bool set(string $key, mixed $value, ?int $ttl = null)
 * @method static bool delete(string $key)
 * @method static bool has(string $key)
 * @method static mixed remember(string $key, \Closure $callback, ?int $ttl = null)
 * @method static bool forever(string $key, mixed $value)
 * @method static bool flush()
 * @method static void setConfig(array $config)
 */
class Cache extends Facade
{
    protected function getClassName(): string
    {
        return CacheManager::class;
    }

    protected static function getInstance(): object
    {
        return CacheManager::getInstance();
    }
}
