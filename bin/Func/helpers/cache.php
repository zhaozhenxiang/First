<?php

declare(strict_types=1);

if (!function_exists('cache')) {
    /**
     * 缓存辅助函数
     */
    function cache(?string $key = null, mixed $value = null, ?int $ttl = null): mixed
    {
        if ($key === null) {
            return \Bin\Cache\CacheManager::store();
        }

        if ($value === null) {
            return \Bin\Cache\CacheManager::get($key);
        }

        return \Bin\Cache\CacheManager::set($key, $value, $ttl);
    }
}

if (!function_exists('remember')) {
    /**
     * 记住缓存值
     */
    function remember(string $key, int $ttl, \Closure $callback): mixed
    {
        return \Bin\Cache\CacheManager::remember($key, $ttl, $callback);
    }
}

if (!function_exists('cache_forever')) {
    /**
     * 永久缓存
     */
    function cache_forever(string $key, mixed $value): bool
    {
        return \Bin\Cache\CacheManager::forever($key, $value);
    }
}

if (!function_exists('cache_forget')) {
    /**
     * 获取并删除缓存
     */
    function cache_forget(string $key, mixed $default = null): mixed
    {
        return \Bin\Cache\CacheManager::store()->pull($key, $default);
    }
}
