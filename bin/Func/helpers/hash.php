<?php

declare(strict_types=1);

if (!function_exists('hash_make')) {
    /**
     * 哈希密码
     */
    function hash_make(string $value, array $options = []): string
    {
        return \Bin\Auth\HashManager::make($value, $options);
    }
}

if (!function_exists('hash_check')) {
    /**
     * 验证密码
     */
    function hash_check(string $value, string $hashedValue): bool
    {
        return \Bin\Auth\HashManager::check($value, $hashedValue);
    }
}

if (!function_exists('hash_bcrypt')) {
    /**
     * 使用 Bcrypt 算法哈希
     */
    function hash_bcrypt(string $value, int $rounds = 10): string
    {
        return \Bin\Auth\HashManager::bcrypt($value, $rounds);
    }
}
