<?php

declare(strict_types=1);

if (!function_exists('rate_limiter')) {
    /**
     * 获取速率限制器实例
     */
    function rate_limiter(): \Bin\Auth\RateLimiter
    {
        static $limiter = null;

        if ($limiter === null) {
            $limiter = new \Bin\Auth\RateLimiter();
        }

        return $limiter;
    }
}

if (!function_exists('throttle')) {
    /**
     * 检查速率限制
     */
    function throttle(string $key, int $maxAttempts, int $decaySeconds): bool
    {
        return \Bin\Auth\RateLimiter::attempt($key, $maxAttempts, $decaySeconds);
    }
}

if (!function_exists('rate_limit_remaining')) {
    /**
     * 获取剩余尝试次数
     */
    function rate_limit_remaining(string $key, int $maxAttempts, int $decaySeconds): int
    {
        return \Bin\Auth\RateLimiter::remaining($key, $maxAttempts, $decaySeconds);
    }
}

if (!function_exists('rate_limit_clear')) {
    /**
     * 清除速率限制
     */
    function rate_limit_clear(string $key): void
    {
        \Bin\Auth\RateLimiter::clear($key);
    }
}
