<?php

declare(strict_types=1);

namespace Bin\Log;

/**
 * 日志管理器 - 提供全局日志访问
 */
class LogManager
{
    /** @var array<string, Logger> 日志通道 */
    private static array $channels = [];

    /** @var string 默认通道 */
    private static string $defaultChannel = 'app';

    /**
     * 获取日志通道
     */
    public static function channel(string $name = null): Logger
    {
        $name = $name ?? self::$defaultChannel;

        if (!isset(self::$channels[$name])) {
            self::$channels[$name] = new Logger($name);
        }

        return self::$channels[$name];
    }

    /**
     * 设置默认通道
     */
    public static function setDefaultChannel(string $name): void
    {
        self::$defaultChannel = $name;
    }

    /**
     * 快捷方法 - Debug
     */
    public static function debug(string $message, array $context = []): void
    {
        self::channel()->debug($message, $context);
    }

    /**
     * 快捷方法 - Info
     */
    public static function info(string $message, array $context = []): void
    {
        self::channel()->info($message, $context);
    }

    /**
     * 快捷方法 - Warning
     */
    public static function warning(string $message, array $context = []): void
    {
        self::channel()->warning($message, $context);
    }

    /**
     * 快捷方法 - Error
     */
    public static function error(string $message, array $context = []): void
    {
        self::channel()->error($message, $context);
    }

    /**
     * 清除所有日志通道
     */
    public static function clear(): void
    {
        self::$channels = [];
    }
}
