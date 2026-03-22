<?php

declare(strict_types=1);

namespace Bin\Facade;

use Bin\Container\Container;
use Bin\App\App;

/**
 * Facade 基类 - 提供静态访问容器服务的门面
 */
abstract class Facade
{
    /**
     * 已解析的 Facade 实例缓存
     * @var array<string, object>
     */
    private static array $instances = [];

    /**
     * Facade 容器映射
     * @var array<string, Container>
     */
    private static array $containers = [];

    /**
     * 默认应用实例
     */
    private static ?App $app = null;

    /**
     * 获取 Facade 背后的实际类名
     */
    abstract protected function getClassName(): string;

    /**
     * 获取 Facade 的容器实例
     */
    protected static function getFacadeContainer(): Container
    {
        // 如果已设置专用容器，使用它
        if (isset(self::$containers[static::class])) {
            return self::$containers[static::class];
        }

        // 否则使用全局应用容器
        if (self::$app === null) {
            self::$app = App::getInstance();
        }

        return self::$app->getContainer();
    }

    /**
     * 设置 Facade 的容器
     */
    public static function setFacadeContainer(string $facade, Container $container): void
    {
        self::$containers[$facade] = $container;
    }

    /**
     * 设置全局应用实例
     */
    public static function setApp(App $app): void
    {
        self::$app = $app;
    }

    /**
     * 清除所有缓存
     */
    public static function clear(): void
    {
        self::$instances = [];
        self::$containers = [];
    }

    /**
     * 清除特定 Facade 的缓存
     */
    public static function clearFacade(string $facade): void
    {
        unset(self::$instances[$facade], self::$containers[$facade]);
    }

    /**
     * 获取底层实例
     */
    protected static function getInstance(): object
    {
        $facadeClass = static::class;

        if (!isset(self::$instances[$facadeClass])) {
            $className = (new static)->getClassName();
            $container = static::getFacadeContainer();

            self::$instances[$facadeClass] = $container->make($className);
        }

        return self::$instances[$facadeClass];
    }

    /**
     * 设置底层实例（用于测试）
     */
    public static function setInstance(object $instance): void
    {
        self::$instances[static::class] = $instance;
    }

    /**
     * 静态方法调用代理
     */
    public static function __callStatic(string $method, array $args): mixed
    {
        $instance = static::getInstance();

        return $instance->$method(...$args);
    }
}
