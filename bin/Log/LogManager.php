<?php

declare(strict_types=1);

namespace Bin\Log;

/**
 * 日志管理器 - 提供全局日志访问
 */
class LogManager
{
    /** @var array<string, Logger> 日志通道 */
    private array $channels = [];

    /** @var string 默认通道 */
    private string $defaultChannel = '';

    /** @var self|null 单例实例 */
    private static ?self $instance = null;

    public function __construct()
    {
        if ($this->defaultChannel === '' && function_exists('config')) {
            $this->defaultChannel = config('logging.default') ?? 'app';
        }
        if ($this->defaultChannel === '') {
            $this->defaultChannel = 'app';
        }
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
     * 获取日志通道
     */
    public function channelFor(?string $name = null): Logger
    {
        $name = $name ?? $this->defaultChannel;

        if (!isset($this->channels[$name])) {
            $this->channels[$name] = new Logger($name);
        }

        return $this->channels[$name];
    }

    /**
     * 设置默认通道
     */
    public function setDefaultChannelFor(string $name): void
    {
        $this->defaultChannel = $name;
    }

    /**
     * 清除所有日志通道并重置默认通道
     */
    public function clearFor(): void
    {
        $this->channels = [];
        $this->defaultChannel = 'app';
    }

    /**
     * 注册自定义 Logger 实例
     */
    public function registerChannelFor(string $name, Logger $logger): void
    {
        $this->channels[$name] = $logger;
    }

    // ─── @deprecated 静态兼容层 ───────────────────────────

    /**
     * 获取日志通道
     * @deprecated 使用 app('log')->channelFor() 或 LogManager::getInstance()->channelFor()
     */
    public static function channel(?string $name = null): Logger
    {
        return self::getInstance()->channelFor($name);
    }

    /**
     * 设置默认通道
     * @deprecated 使用 LogManager::getInstance()->setDefaultChannelFor()
     */
    public static function setDefaultChannel(string $name): void
    {
        self::getInstance()->setDefaultChannelFor($name);
    }

    /**
     * 快捷方法 - Debug
     * @deprecated 使用 app('log')->channelFor()->debug()
     */
    public static function debug(string $message, array $context = []): void
    {
        self::getInstance()->channelFor()->debug($message, $context);
    }

    /**
     * 快捷方法 - Info
     * @deprecated 使用 app('log')->channelFor()->info()
     */
    public static function info(string $message, array $context = []): void
    {
        self::getInstance()->channelFor()->info($message, $context);
    }

    /**
     * 快捷方法 - Warning
     * @deprecated 使用 app('log')->channelFor()->warning()
     */
    public static function warning(string $message, array $context = []): void
    {
        self::getInstance()->channelFor()->warning($message, $context);
    }

    /**
     * 快捷方法 - Error
     * @deprecated 使用 app('log')->channelFor()->error()
     */
    public static function error(string $message, array $context = []): void
    {
        self::getInstance()->channelFor()->error($message, $context);
    }

    /**
     * 清除所有日志通道
     * @deprecated 使用 LogManager::getInstance()->clearFor()
     */
    public static function clear(): void
    {
        self::getInstance()->clearFor();
    }

    /**
     * 注册自定义 Logger 实例
     * @deprecated 使用 LogManager::getInstance()->registerChannelFor()
     */
    public static function registerChannel(string $name, Logger $logger): void
    {
        self::getInstance()->registerChannelFor($name, $logger);
    }
}
