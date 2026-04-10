<?php

declare(strict_types=1);

namespace Bin\Auth;

/**
 * 哈希管理器
 *
 * 处理密码哈希和验证
 */
class HashManager
{
    /**
     * 默认哈希算法
     */
    private static int|string|null $algorithm = PASSWORD_DEFAULT;

    /**
     * 默认哈希选项
     */
    private static array $options = [
        'cost' => 10,
    ];

    /** @var bool 是否已从 config 加载 */
    private static bool $configLoaded = false;

    /**
     * 从 config/hashing.php 加载配置
     */
    public static function loadFromConfig(): void
    {
        if (self::$configLoaded || !function_exists('config')) {
            return;
        }

        self::$configLoaded = true;

        $driver = config('hashing.driver', 'bcrypt');

        if ($driver === 'bcrypt') {
            self::$algorithm = PASSWORD_BCRYPT;
            self::$options = ['cost' => (int) config('hashing.bcrypt.rounds', 10)];
        } elseif ($driver === 'argon2i') {
            self::$algorithm = PASSWORD_ARGON2I;
            self::$options = [
                'memory_cost' => (int) config('hashing.argon.memory', 65536),
                'time_cost' => (int) config('hashing.argon.time', 4),
                'threads' => (int) config('hashing.argon.threads', 1),
            ];
        } elseif ($driver === 'argon2id' && defined('PASSWORD_ARGON2ID')) {
            self::$algorithm = PASSWORD_ARGON2ID;
            self::$options = [
                'memory_cost' => (int) config('hashing.argon.memory', 65536),
                'time_cost' => (int) config('hashing.argon.time', 4),
                'threads' => (int) config('hashing.argon.threads', 1),
            ];
        }
    }

    /**
     * 设置默认算法
     */
    public static function setAlgorithm(int $algorithm): void
    {
        self::$algorithm = $algorithm;
    }

    /**
     * 设置默认选项
     */
    public static function setOptions(array $options): void
    {
        self::$options = $options;
    }

    /**
     * 哈希密码
     */
    public static function make(string $value, array $options = []): string
    {
        self::loadFromConfig();
        $options = array_merge(self::$options, $options);
        return password_hash($value, self::$algorithm, $options);
    }

    /**
     * 验证密码
     */
    public static function check(string $value, string $hashedValue): bool
    {
        return password_verify($value, $hashedValue);
    }

    /**
     * 检查密码是否需要重新哈希
     */
    public static function needsRehash(string $hashedValue, array $options = []): bool
    {
        $options = array_merge(self::$options, $options);
        return password_needs_rehash($hashedValue, self::$algorithm, $options);
    }

    /**
     * 获取哈希信息
     */
    public static function getInfo(string $hashedValue): array|bool
    {
        return password_get_info($hashedValue);
    }

    /**
     * 使用 Bcrypt 算法哈希
     */
    public static function bcrypt(string $value, int $rounds = 10): string
    {
        return self::make($value, ['cost' => $rounds]);
    }

    /**
     * 使用 Argon2i 算法哈希
     */
    public static function argon2i(string $value, int $memory = 65536, int $time = 4, int $threads = 1): string
    {
        return password_hash($value, PASSWORD_ARGON2I, [
            'memory_cost' => $memory,
            'time_cost' => $time,
            'threads' => $threads,
        ]);
    }

    /**
     * 使用 Argon2id 算法哈希
     */
    public static function argon2id(string $value, int $memory = 65536, int $time = 4, int $threads = 1): string
    {
        return password_hash($value, PASSWORD_ARGON2ID, [
            'memory_cost' => $memory,
            'time_cost' => $time,
            'threads' => $threads,
        ]);
    }
}
