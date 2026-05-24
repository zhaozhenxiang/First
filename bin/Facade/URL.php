<?php

declare(strict_types=1);

namespace Bin\Facade;

use Bin\Route\RouteCollection;
use Bin\Route\SignedUrl;
use DateTimeInterface;

/**
 * URL Facade - 静态代理 URL 生成
 *
 * @method static string to(string $path)
 * @method static string route(string $name, array $params = [])
 * @method static string signedRoute(string $name, array $params = [])
 * @method static string temporarySignedRoute(string $name, DateTimeInterface|int $expiration, array $params = [])
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
     * Generate a signed named route URL.
     */
    public static function signedRoute(string $name, array $params = []): string
    {
        return SignedUrl::signedRoute($name, $params);
    }

    /**
     * Generate a temporary signed named route URL.
     */
    public static function temporarySignedRoute(string $name, DateTimeInterface|int $expiration, array $params = []): string
    {
        return SignedUrl::temporarySignedRoute($name, $expiration, $params);
    }

    /**
     * 生成路径 URL
     */
    public static function to(string $path): string
    {
        return '/' . ltrim($path, '/');
    }
}
