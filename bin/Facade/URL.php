<?php

declare(strict_types=1);

namespace Bin\Facade;

use Bin\Route\RouteCollection;

/**
 * URL Facade - 静态代理 URL 生成
 *
 * @method static string to(string $path)
 * @method static string route(string $name, array $params = [])
 * @method static string full()
 * @method static string current()
 */
class URL extends Facade
{
    protected function getClassName(): string
    {
        return RouteCollection::class;
    }

    protected static function getInstance(): object
    {
        // RouteCollection 是纯静态类
        static $instance;
        return $instance ??= new class {
            public function __call(string $method, array $args): mixed
            {
                return RouteCollection::{$method}(...$args);
            }
        };
    }

    /**
     * 生成命名路由 URL
     */
    public static function route(string $name, array $params = []): string
    {
        return RouteCollection::url($name, $params);
    }

    /**
     * 生成路径 URL
     */
    public static function to(string $path): string
    {
        return '/' . ltrim($path, '/');
    }
}
