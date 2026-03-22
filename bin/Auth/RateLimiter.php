<?php

declare(strict_types=1);

namespace Bin\Auth;

/**
 * 速率限制器
 *
 * 基于时间窗口的请求频率限制
 */
class RateLimiter
{
    /**
     * 限制记录存储
     */
    private static array $limits = [];

    /**
     * 缓存实例（用于持久化）
     */
    private static $cache = null;

    /**
     * 设置缓存实例
     */
    public static function setCache($cache): void
    {
        self::$cache = $cache;
    }

    /**
     * 尝试执行操作（检查是否超过限制）
     */
    public static function attempt(string $key, int $maxAttempts, int $decaySeconds): bool
    {
        $now = (int) microtime(true);

        if (!isset(self::$limits[$key])) {
            self::$limits[$key] = [
                'hits' => 0,
                'reset_at' => $now + $decaySeconds,
                'decay' => $decaySeconds,
            ];
        }

        $limit = &self::$limits[$key];

        // 检查是否需要重置
        if ($now >= $limit['reset_at']) {
            $limit['hits'] = 0;
            $limit['reset_at'] = $now + $limit['decay'];
        }

        // 检查是否超过限制
        if ($limit['hits'] >= $maxAttempts) {
            return false;
        }

        $limit['hits']++;

        // 尝试持久化到缓存
        if (self::$cache !== null) {
            self::$cache->set($key, $limit, $decaySeconds);
        }

        return true;
    }

    /**
     * 获取剩余尝试次数
     */
    public static function remaining(string $key, int $maxAttempts, int $decaySeconds): int
    {
        $limit = self::getLimit($key, $decaySeconds);

        if ($limit === null) {
            return $maxAttempts;
        }

        return max(0, $maxAttempts - $limit['hits']);
    }

    /**
     * 获取重置时间（秒）
     */
    public static function availableIn(string $key, int $decaySeconds): int
    {
        $limit = self::getLimit($key, $decaySeconds);

        if ($limit === null) {
            return 0;
        }

        $now = (int) microtime(true);

        return max(0, $limit['reset_at'] - $now);
    }

    /**
     * 清除限制记录
     */
    public static function clear(string $key): void
    {
        unset(self::$limits[$key]);

        if (self::$cache !== null) {
            self::$cache->delete($key);
        }
    }

    /**
     * 获取限制记录
     */
    protected static function getLimit(string $key, int $decaySeconds): ?array
    {
        // 先从内存获取
        if (isset(self::$limits[$key])) {
            return self::$limits[$key];
        }

        // 尝试从缓存获取
        if (self::$cache !== null) {
            $limit = self::$cache->get($key);

            if ($limit !== null) {
                self::$limits[$key] = $limit;
                return $limit;
            }
        }

        return null;
    }

    /**
     * 检查是否被限制
     */
    public static function isLocked(string $key, int $decaySeconds): bool
    {
        return !self::attempt($key, PHP_INT_MAX, $decaySeconds);
    }

    /**
     * 获取当前尝试次数
     */
    public static function attempts(string $key, int $decaySeconds): int
    {
        $limit = self::getLimit($key, $decaySeconds);

        return $limit['hits'] ?? 0;
    }

    /**
     * 清除所有限制
     */
    public static function reset(): void
    {
        self::$limits = [];
    }

    /**
     * 使用标识符生成键名
     */
    public static function key(string $identifier, string $suffix = ''): string
    {
        $key = 'rate_limit:' . $identifier;

        if ($suffix !== '') {
            $key .= ':' . $suffix;
        }

        return $key;
    }
}
