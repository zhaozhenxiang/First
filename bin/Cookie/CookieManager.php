<?php

declare(strict_types=1);

namespace Bin\Cookie;

/**
 * Cookie 管理器
 *
 * 安全的 Cookie 操作
 */
class CookieManager
{
    /**
     * 默认配置
     */
    private static string $path = '/';
    private static string $domain = '';
    private static bool $secure = false;
    private static bool $httpOnly = true;
    private static string $sameSite = 'Lax';
    private static ?string $encryptionKey = null;

    /**
     * 设置 Cookie
     */
    public static function set(
        string $name,
        string $value,
        int $minutes = 0,
        ?string $path = null,
        ?string $domain = null,
        ?bool $secure = null,
        ?bool $httpOnly = null,
        ?string $sameSite = null
    ): bool {
        $path = $path ?? self::$path;
        $domain = $domain ?? self::$domain;
        $secure = $secure ?? self::$secure;
        $httpOnly = $httpOnly ?? self::$httpOnly;
        $sameSite = $sameSite ?? self::$sameSite;

        // 加密值（如果设置了密钥）
        $value = self::$encryptionKey !== null
            ? self::encrypt($value)
            : $value;

        $expiry = $minutes > 0 ? time() + ($minutes * 60) : 0;

        $options = [
            'expires' => $expiry,
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => $httpOnly,
            'samesite' => $sameSite,
        ];

        return setcookie($name, $value, $options);
    }

    /**
     * 获取 Cookie
     */
    public static function get(string $name, ?string $default = null): ?string
    {
        if (!isset($_COOKIE[$name])) {
            return $default;
        }

        $value = $_COOKIE[$name];

        // 解密值（如果设置了密钥）
        if (self::$encryptionKey !== null) {
            $decrypted = self::decrypt($value);
            return $decrypted !== null ? $decrypted : $default;
        }

        return $value;
    }

    /**
     * 检查 Cookie 是否存在
     */
    public static function has(string $name): bool
    {
        return isset($_COOKIE[$name]);
    }

    /**
     * 删除 Cookie
     */
    public static function forget(string $name): bool
    {
        return self::set($name, '', -2628000); // 5 年前过期
    }

    /**
     * 删除多个 Cookie
     */
    public static function forgetMultiple(array $names): void
    {
        foreach ($names as $name) {
            self::forget($name);
        }
    }

    /**
     * 永久 Cookie（5 年）
     */
    public static function forever(string $name, string $value): bool
    {
        return self::set($name, $value, 2628000); // 5 年
    }

    /**
     * 设置配置
     */
    public static function setDefaults(array $config): void
    {
        if (isset($config['path'])) {
            self::$path = $config['path'];
        }
        if (isset($config['domain'])) {
            self::$domain = $config['domain'];
        }
        if (isset($config['secure'])) {
            self::$secure = $config['secure'];
        }
        if (isset($config['httpOnly'])) {
            self::$httpOnly = $config['httpOnly'];
        }
        if (isset($config['sameSite'])) {
            self::$sameSite = $config['sameSite'];
        }
    }

    /**
     * 设置加密密钥（传 null 禁用加密）
     */
    public static function setEncryptionKey(?string $key): void
    {
        self::$encryptionKey = $key;
    }

    /**
     * 加密值
     */
    protected static function encrypt(string $value): string
    {
        if (self::$encryptionKey === null) {
            return $value;
        }

        $iv = random_bytes(16);
        $encrypted = openssl_encrypt(
            $value,
            'AES-256-CBC',
            self::$encryptionKey,
            0,
            $iv
        );

        return base64_encode($iv . $encrypted);
    }

    /**
     * 解密值
     */
    protected static function decrypt(string $value): ?string
    {
        if (self::$encryptionKey === null) {
            return $value;
        }

        $decoded = base64_decode($value);

        if ($decoded === false || strlen($decoded) < 16) {
            return null;
        }

        $iv = substr($decoded, 0, 16);
        $encrypted = substr($decoded, 16);

        $decrypted = openssl_decrypt(
            $encrypted,
            'AES-256-CBC',
            self::$encryptionKey,
            0,
            $iv
        );

        return $decrypted !== false ? $decrypted : null;
    }

    /**
     * 获取所有 Cookie
     */
    public static function all(): array
    {
        return $_COOKIE;
    }

    /**
     * 清除所有 Cookie
     */
    public static function flush(): void
    {
        foreach (array_keys($_COOKIE) as $name) {
            self::forget($name);
        }
    }
}
