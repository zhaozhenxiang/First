<?php

declare(strict_types=1);

namespace Bin\App;

use Bin\Facade\Request;
use Bin\Response\Response;
use Bin\Route\RouteCollection;

class App
{
    private static ?self $instance = null;
    private static array $container = [];

    // 自定义 ioc 的 key
    private static array $classMap = [
        'Request'  => \Bin\Request\Request::class,
        'Response' => Response::class,
        'Route'    => RouteCollection::class,
    ];

    private static array $facadeMap = [
        'Request' => Request::class,
    ];

    /**
     * 获取自身
     * @return self
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Facade 加载
     */
    public static function facade(string $class): ?object
    {
        if (!isset(self::$facadeMap[$class])) {
            return null;
        }

        if (is_file(BASE_PATH . DIRECTORY_SEPARATOR . self::$classMap[$class] . '.php')) {
            class_alias(self::$facadeMap[$class], $class);
            return self::$container[$class] = new $class;
        }

        return null;
    }

    /**
     * 将给定的 class 加载到 container 中
     * @throws \Exception
     */
    public static function make(string $class): object
    {
        if (isset(self::$container[$class])) {
            return self::$container[$class];
        }

        $instance = self::loadClass($class);

        if ($instance !== null) {
            // 查找是否有 mapping
            $mapping = array_filter(self::$classMap, function ($v) use ($class) {
                return $class === $v;
            });

            // 设置别名
            foreach ($mapping as $key => $val) {
                self::$container[$key] = $instance;
            }

            return self::$container[$class] = $instance;
        }

        throw new \Exception('make none');
    }

    /**
     * 加载一个类
     */
    private static function loadClass(string $class): ?object
    {
        $classPath = str_replace('\\', DIRECTORY_SEPARATOR, $class);
        $classPath = lcfirst($classPath);

        if (is_file(BASE_PATH . DIRECTORY_SEPARATOR . $classPath . '.php')) {
            return new $class;
        }

        if (isset(self::$classMap[$class])) {
            $file = BASE_PATH . DIRECTORY_SEPARATOR . self::$classMap[$class] . '.php';

            if (is_file($file)) {
                require_once $file;
                self::$classMap[$class] = $class;
            }
        }

        return new self::$classMap[$class];
    }
}