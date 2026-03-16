<?php

declare(strict_types=1);

namespace Bin\Config;

/**
 * 配置缓存 - 避免重复读取配置文件
 */
class ConfigCache
{
    private static array $cache = [];

    /**
     * 获取配置值（带缓存）
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $value = config($key, $default);

        if ($value !== null) {
            self::$cache[$key] = $value;
        }

        return $value;
    }

    /**
     * 设置配置值
     */
    public static function set(string $key, mixed $value): void
    {
        self::$cache[$key] = $value;
    }

    /**
     * 检查配置是否存在
     */
    public static function has(string $key): bool
    {
        return isset(self::$cache[$key]) || config($key, null) !== null;
    }

    /**
     * 清除缓存
     */
    public static function clear(): void
    {
        self::$cache = [];
    }

    /**
     * 预加载配置
     */
    public static function preload(array $keys): void
    {
        foreach ($keys as $key) {
            if (!isset(self::$cache[$key])) {
                self::$cache[$key] = config($key);
            }
        }
    }
}
