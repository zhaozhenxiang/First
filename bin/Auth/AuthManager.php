<?php

declare(strict_types=1);

namespace Bin\Auth;

use Bin\Session\SessionManager;

/**
 * 认证管理器
 *
 * 处理用户认证和会话管理
 */
class AuthManager
{
    /**
     * 当前认证用户
     */
    private static ?object $user = null;

    /**
     * 用户提供者（模型类名）
     */
    private static ?string $provider = null;

    /**
     * Session 键名
     */
    private static string $sessionKey = '_auth_user';

    /**
     * 设置用户提供者
     */
    public static function setProvider(string $provider): void
    {
        self::$provider = $provider;
    }

    /**
     * 获取用户提供者
     */
    public static function getProvider(): ?string
    {
        if (self::$provider !== null) {
            return self::$provider;
        }

        // 尝试从配置获取，如果失败则使用默认值
        try {
            return config('auth.provider') ?? 'App\\Model\\User';
        } catch (\Exception $e) {
            return 'App\\Model\\User';
        }
    }

    /**
     * 设置 Session 键名
     */
    public static function setSessionKey(string $key): void
    {
        self::$sessionKey = $key;
    }

    /**
     * 获取 Session 键名
     */
    public static function getSessionKey(): string
    {
        return self::$sessionKey;
    }

    /**
     * 尝试登录用户
     */
    public static function attempt(array $credentials, bool $remember = false): bool
    {
        $provider = self::getProvider();

        if ($provider === null || !class_exists($provider)) {
            throw new \RuntimeException("Auth provider not found: {$provider}");
        }

        // 获取用户标识符（通常是 email 或 username）
        $identifier = $credentials['email'] ?? $credentials['username'] ?? null;

        if ($identifier === null) {
            return false;
        }

        // 查找用户
        $user = $provider::where('email', $identifier)
            ->orWhere('username', $identifier)
            ->first();

        if ($user === null) {
            return false;
        }

        // 验证密码
        $password = $credentials['password'] ?? null;
        if ($password === null) {
            return false;
        }

        if (!HashManager::check($password, $user->password ?? '')) {
            return false;
        }

        // 登录成功
        self::login($user, $remember);

        return true;
    }

    /**
     * 登录用户
     */
    public static function login(object $user, bool $remember = false): void
    {
        self::$user = $user;

        $session = session_manager();
        $session->set(self::$sessionKey, $user->id ?? $user->id);

        // Remember Me 功能
        if ($remember) {
            self::remember($user);
        }
    }

    /**
     * 使用 ID 登录用户
     */
    public static function loginUsingId(mixed $id, bool $remember = false): ?object
    {
        $provider = self::getProvider();

        if ($provider === null || !class_exists($provider)) {
            return null;
        }

        $user = $provider::find($id);

        if ($user !== null) {
            self::login($user, $remember);
        }

        return $user;
    }

    /**
     * 登出用户
     */
    public static function logout(): void
    {
        $session = session_manager();
        $session->remove(self::$sessionKey);
        $session->remove('_auth_remember');

        self::$user = null;

        // 清除 remember me cookie
        if (isset($_COOKIE['_auth_remember'])) {
            setcookie('_auth_remember', '', time() - 3600, '/');
            unset($_COOKIE['_auth_remember']);
        }
    }

    /**
     * 获取当前认证用户
     */
    public static function user(): ?object
    {
        if (self::$user !== null) {
            return self::$user;
        }

        $session = session_manager();
        $userId = $session->get(self::$sessionKey);

        if ($userId === null) {
            // 尝试从 remember me 恢复
            $userId = self::getRememberUserId();
        }

        if ($userId === null) {
            return null;
        }

        $provider = self::getProvider();

        if ($provider === null || !class_exists($provider)) {
            return null;
        }

        $user = $provider::find($userId);

        if ($user !== null) {
            self::$user = $user;
        }

        return self::$user;
    }

    /**
     * 获取当前用户 ID
     */
    public static function id(): mixed
    {
        return self::user()?->id;
    }

    /**
     * 检查用户是否已认证
     */
    public static function check(): bool
    {
        return self::user() !== null;
    }

    /**
     * 检查用户是否是访客
     */
    public static function guest(): bool
    {
        return !self::check();
    }

    /**
     * 验证用户凭据但不登录
     */
    public static function validate(array $credentials): bool
    {
        $provider = self::getProvider();

        if ($provider === null || !class_exists($provider)) {
            return false;
        }

        $identifier = $credentials['email'] ?? $credentials['username'] ?? null;

        if ($identifier === null) {
            return false;
        }

        $user = $provider::where('email', $identifier)
            ->orWhere('username', $identifier)
            ->first();

        if ($user === null) {
            return false;
        }

        $password = $credentials['password'] ?? null;
        if ($password === null) {
            return false;
        }

        return HashManager::check($password, $user->password ?? '');
    }

    /**
     * 设置 Remember Me
     */
    protected static function remember(object $user): void
    {
        $token = bin2hex(random_bytes(32));
        $payload = base64_encode(json_encode([
            'id' => $user->id,
            'token' => $token,
        ]));

        // 存储到 session
        $session = session_manager();
        $session->set('_auth_remember', $payload);

        // 设置 cookie（30 天）
        setcookie('_auth_remember', $payload, time() + (30 * 86400), '/', '', false, true);
    }

    /**
     * 从 Remember Me 获取用户 ID
     */
    protected static function getRememberUserId(): mixed
    {
        $payload = $_COOKIE['_auth_remember'] ?? session_manager()->get('_auth_remember');

        if ($payload === null) {
            return null;
        }

        try {
            $data = json_decode(base64_decode($payload), true);

            if (!isset($data['id']) || !isset($data['token'])) {
                return null;
            }

            return $data['id'];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * 重置用户缓存
     */
    public static function resetUser(): void
    {
        self::$user = null;
    }
}
