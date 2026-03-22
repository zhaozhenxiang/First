<?php

declare(strict_types=1);

namespace Bin\Auth;

use Bin\Session\SessionManager;

/**
 * 密码重置管理器
 */
class PasswordResetManager
{
    /**
     * Session 键名
     */
    private static string $sessionKey = '_password_reset_token';

    /**
     * Token 有效期（秒）
     */
    private static int $tokenLifetime = 3600; // 1 小时

    /**
     * 生成重置令牌
     */
    public static function createToken(object $user): string
    {
        $token = bin2hex(random_bytes(32));
        $expiry = time() + self::$tokenLifetime;

        $session = session_manager();
        $tokens = $session->get(self::$sessionKey, []);

        $tokens[$token] = [
            'user_id' => $user->id,
            'expiry' => $expiry,
        ];

        $session->set(self::$sessionKey, $tokens);

        return $token;
    }

    /**
     * 验证重置令牌
     */
    public static function validateToken(string $token): ?object
    {
        $session = session_manager();
        $tokens = $session->get(self::$sessionKey, []);

        if (!isset($tokens[$token])) {
            return null;
        }

        $data = $tokens[$token];

        // 检查是否过期
        if ($data['expiry'] < time()) {
            // 清理过期令牌
            unset($tokens[$token]);
            $session->set(self::$sessionKey, $tokens);
            return null;
        }

        // 获取用户
        $provider = AuthManager::getProvider();

        if ($provider === null || !class_exists($provider)) {
            return null;
        }

        $user = $provider::find($data['user_id']);

        return $user;
    }

    /**
     * 删除重置令牌
     */
    public static function deleteToken(string $token): void
    {
        $session = session_manager();
        $tokens = $session->get(self::$sessionKey, []);

        unset($tokens[$token]);

        $session->set(self::$sessionKey, $tokens);
    }

    /**
     * 重置密码
     */
    public static function resetPassword(string $token, string $newPassword): bool
    {
        $user = self::validateToken($token);

        if ($user === null) {
            return false;
        }

        // 哈希新密码
        $hashedPassword = HashManager::make($newPassword);

        // 更新用户密码
        if (isset($user->password)) {
            $user->password = $hashedPassword;
            $user->save();

            // 删除令牌
            self::deleteToken($token);

            return true;
        }

        return false;
    }

    /**
     * 清理过期令牌
     */
    public static function cleanExpiredTokens(): void
    {
        $session = session_manager();
        $tokens = $session->get(self::$sessionKey, []);

        $now = time();
        foreach ($tokens as $token => $data) {
            if ($data['expiry'] < $now) {
                unset($tokens[$token]);
            }
        }

        $session->set(self::$sessionKey, $tokens);
    }

    /**
     * 设置令牌有效期
     */
    public static function setTokenLifetime(int $seconds): void
    {
        self::$tokenLifetime = $seconds;
    }

    /**
     * 获取令牌有效期
     */
    public static function getTokenLifetime(): int
    {
        return self::$tokenLifetime;
    }
}
