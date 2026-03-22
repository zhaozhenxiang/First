<?php

declare(strict_types=1);

namespace Bin\Auth;

/**
 * 节流器
 *
 * 预定义的速率限制规则
 */
class Throttle
{
    /**
     * API 速率限制（每分钟 60 次）
     */
    public static function api(string $identifier): bool
    {
        return RateLimiter::attempt(
            RateLimiter::key($identifier, 'api'),
            60,
            60
        );
    }

    /**
     * 登录尝试限制（每分钟 5 次）
     */
    public static function login(string $identifier): bool
    {
        return RateLimiter::attempt(
            RateLimiter::key($identifier, 'login'),
            5,
            60
        );
    }

    /**
     * 注册尝试限制（每小时 3 次）
     */
    public static function register(string $identifier): bool
    {
        return RateLimiter::attempt(
            RateLimiter::key($identifier, 'register'),
            3,
            3600
        );
    }

    /**
     * 密码重置限制（每小时 3 次）
     */
    public static function passwordReset(string $identifier): bool
    {
        return RateLimiter::attempt(
            RateLimiter::key($identifier, 'password_reset'),
            3,
            3600
        );
    }

    /**
     * 短信/邮件发送限制（每小时 10 次）
     */
    public static function sms(string $identifier): bool
    {
        return RateLimiter::attempt(
            RateLimiter::key($identifier, 'sms'),
            10,
            3600
        );
    }

    /**
     * 邮件发送限制（每小时 20 次）
     */
    public static function email(string $identifier): bool
    {
        return RateLimiter::attempt(
            RateLimiter::key($identifier, 'email'),
            20,
            3600
        );
    }

    /**
     * 文件上传限制（每分钟 10 次）
     */
    public static function upload(string $identifier): bool
    {
        return RateLimiter::attempt(
            RateLimiter::key($identifier, 'upload'),
            10,
            60
        );
    }

    /**
     * 自定义限制
     */
    public static function custom(
        string $identifier,
        string $suffix,
        int $maxAttempts,
        int $decaySeconds
    ): bool {
        return RateLimiter::attempt(
            RateLimiter::key($identifier, $suffix),
            $maxAttempts,
            $decaySeconds
        );
    }

    /**
     * 获取剩余次数
     */
    public static function remaining(string $identifier, string $suffix, int $maxAttempts, int $decaySeconds): int
    {
        return RateLimiter::remaining(
            RateLimiter::key($identifier, $suffix),
            $maxAttempts,
            $decaySeconds
        );
    }

    /**
     * 获取重置时间
     */
    public static function availableIn(string $identifier, string $suffix, int $decaySeconds): int
    {
        return RateLimiter::availableIn(
            RateLimiter::key($identifier, $suffix),
            $decaySeconds
        );
    }

    /**
     * 清除限制
     */
    public static function clear(string $identifier, string $suffix = ''): void
    {
        RateLimiter::clear(RateLimiter::key($identifier, $suffix));
    }

    /**
     * IP 地址获取器
     */
    public static function ip(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * 用户 ID 获取器
     */
    public static function userId(): ?string
    {
        return auth_id() ? (string) auth_id() : null;
    }

    /**
     * 使用 IP 作为标识符
     */
    public static function byIp(string $suffix, int $maxAttempts, int $decaySeconds): bool
    {
        return self::custom(self::ip(), $suffix, $maxAttempts, $decaySeconds);
    }

    /**
     * 使用用户 ID 作为标识符
     */
    public static function byUser(string $suffix, int $maxAttempts, int $decaySeconds): bool
    {
        $userId = self::userId();

        if ($userId === null) {
            return self::byIp($suffix, $maxAttempts, $decaySeconds);
        }

        return self::custom($userId, $suffix, $maxAttempts, $decaySeconds);
    }
}
