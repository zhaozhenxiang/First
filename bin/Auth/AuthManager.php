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
    private ?object $user = null;

    /**
     * 用户提供者（模型类名）
     */
    private ?string $provider = null;

    /**
     * Session 键名
     */
    private string $sessionKey = '_auth_user';

    /** @var self|null 单例实例 */
    private static ?self $instance = null;

    public function __construct()
    {
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
     * 设置用户提供者
     */
    public function setProviderFor(string $provider): void
    {
        $this->provider = $provider;
    }

    /**
     * 获取用户提供者
     */
    public function getProviderFor(): ?string
    {
        if ($this->provider !== null) {
            return $this->provider;
        }

        try {
            return config('auth.provider') ?? 'App\\Model\\User';
        } catch (\Exception $e) {
            return 'App\\Model\\User';
        }
    }

    /**
     * 设置 Session 键名
     */
    public function setSessionKeyFor(string $key): void
    {
        $this->sessionKey = $key;
    }

    /**
     * 获取 Session 键名
     */
    public function getSessionKeyFor(): string
    {
        return $this->sessionKey;
    }

    /**
     * 尝试登录用户
     */
    public function attemptFor(array $credentials, bool $remember = false): bool
    {
        $provider = $this->getProviderFor();

        if ($provider === null || !class_exists($provider)) {
            throw new \RuntimeException("Auth provider not found: {$provider}");
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

        if (!HashManager::check($password, $user->password ?? '')) {
            return false;
        }

        $this->loginFor($user, $remember);

        return true;
    }

    /**
     * 登录用户
     */
    public function loginFor(object $user, bool $remember = false): void
    {
        $this->user = $user;

        $session = session_manager();
        $session->set($this->sessionKey, $user->id ?? $user->id);

        if ($remember) {
            $this->rememberUser($user);
        }
    }

    /**
     * 使用 ID 登录用户
     */
    public function loginUsingIdFor(mixed $id, bool $remember = false): ?object
    {
        $provider = $this->getProviderFor();

        if ($provider === null || !class_exists($provider)) {
            return null;
        }

        $user = $provider::find($id);

        if ($user !== null) {
            $this->loginFor($user, $remember);
        }

        return $user;
    }

    /**
     * 登出用户
     */
    public function logoutFor(): void
    {
        $session = session_manager();
        $session->remove($this->sessionKey);
        $session->remove('_auth_remember');

        $this->user = null;

        if (cookie('_auth_remember') !== null) {
            cookie_forget('_auth_remember');
        }
    }

    /**
     * 获取当前认证用户
     */
    public function userFor(): ?object
    {
        if ($this->user !== null) {
            return $this->user;
        }

        $session = session_manager();
        $userId = $session->get($this->sessionKey);

        if ($userId === null) {
            $userId = $this->getRememberUserIdFor();
        }

        if ($userId === null) {
            return null;
        }

        $provider = $this->getProviderFor();

        if ($provider === null || !class_exists($provider)) {
            return null;
        }

        $user = $provider::find($userId);

        if ($user !== null) {
            $this->user = $user;
        }

        return $this->user;
    }

    /**
     * 获取当前用户 ID
     */
    public function idFor(): mixed
    {
        return $this->userFor()?->id;
    }

    /**
     * 检查用户是否已认证
     */
    public function checkFor(): bool
    {
        return $this->userFor() !== null;
    }

    /**
     * 检查用户是否是访客
     */
    public function guestFor(): bool
    {
        return !$this->checkFor();
    }

    /**
     * 验证用户凭据但不登录
     */
    public function validateFor(array $credentials): bool
    {
        $provider = $this->getProviderFor();

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
     * 重置用户缓存
     */
    public function resetUserFor(): void
    {
        $this->user = null;
    }

    /**
     * 设置 Remember Me
     */
    protected function rememberUser(object $user): void
    {
        $token = bin2hex(random_bytes(32));
        $payload = base64_encode(json_encode([
            'id' => $user->id,
            'token' => $token,
        ]));

        $session = session_manager();
        $session->set('_auth_remember', $payload);

        setcookie('_auth_remember', $payload, time() + (30 * 86400), '/', '', false, true);
    }

    /**
     * 从 Remember Me 获取用户 ID
     */
    protected function getRememberUserIdFor(): mixed
    {
        $payload = cookie('_auth_remember') ?? session_manager()->get('_auth_remember');

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

    // ─── @deprecated 静态兼容层 ───────────────────────────

    /**
     * @deprecated 使用 AuthManager::getInstance()->setProviderFor()
     */
    public static function setProvider(string $provider): void
    {
        self::getInstance()->setProviderFor($provider);
    }

    /**
     * @deprecated 使用 AuthManager::getInstance()->getProviderFor()
     */
    public static function getProvider(): ?string
    {
        return self::getInstance()->getProviderFor();
    }

    /**
     * @deprecated 使用 AuthManager::getInstance()->setSessionKeyFor()
     */
    public static function setSessionKey(string $key): void
    {
        self::getInstance()->setSessionKeyFor($key);
    }

    /**
     * @deprecated 使用 AuthManager::getInstance()->getSessionKeyFor()
     */
    public static function getSessionKey(): string
    {
        return self::getInstance()->getSessionKeyFor();
    }

    /**
     * @deprecated 使用 AuthManager::getInstance()->attemptFor()
     */
    public static function attempt(array $credentials, bool $remember = false): bool
    {
        return self::getInstance()->attemptFor($credentials, $remember);
    }

    /**
     * @deprecated 使用 AuthManager::getInstance()->loginFor()
     */
    public static function login(object $user, bool $remember = false): void
    {
        self::getInstance()->loginFor($user, $remember);
    }

    /**
     * @deprecated 使用 AuthManager::getInstance()->loginUsingIdFor()
     */
    public static function loginUsingId(mixed $id, bool $remember = false): ?object
    {
        return self::getInstance()->loginUsingIdFor($id, $remember);
    }

    /**
     * @deprecated 使用 AuthManager::getInstance()->logoutFor()
     */
    public static function logout(): void
    {
        self::getInstance()->logoutFor();
    }

    /**
     * @deprecated 使用 AuthManager::getInstance()->userFor()
     */
    public static function user(): ?object
    {
        return self::getInstance()->userFor();
    }

    /**
     * @deprecated 使用 AuthManager::getInstance()->idFor()
     */
    public static function id(): mixed
    {
        return self::getInstance()->idFor();
    }

    /**
     * @deprecated 使用 AuthManager::getInstance()->checkFor()
     */
    public static function check(): bool
    {
        return self::getInstance()->checkFor();
    }

    /**
     * @deprecated 使用 AuthManager::getInstance()->guestFor()
     */
    public static function guest(): bool
    {
        return self::getInstance()->guestFor();
    }

    /**
     * @deprecated 使用 AuthManager::getInstance()->validateFor()
     */
    public static function validate(array $credentials): bool
    {
        return self::getInstance()->validateFor($credentials);
    }

    /**
     * @deprecated 使用 AuthManager::getInstance()->resetUserFor()
     */
    public static function resetUser(): void
    {
        self::getInstance()->resetUserFor();
    }
}
