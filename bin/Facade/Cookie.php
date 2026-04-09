<?php

declare(strict_types=1);

namespace Bin\Facade;

use Bin\Cookie\CookieManager;

/**
 * Cookie Facade - 静态代理 Cookie 管理器
 *
 * @method static bool set(string $name, string $value, int $minutes = 0, string $path = '/', ?string $domain = null, bool $secure = false, bool $httpOnly = true, ?string $sameSite = null)
 * @method static ?string get(string $name, ?string $default = null)
 * @method static bool has(string $name)
 * @method static bool forget(string $name)
 * @method static bool forever(string $name, string $value)
 * @method static array all()
 * @method static void flush()
 */
class Cookie extends Facade
{
    protected function getClassName(): string
    {
        return CookieManager::class;
    }

    protected static function getInstance(): object
    {
        // CookieManager 是纯静态类
        static $instance;
        return $instance ??= new class {
            public function __call(string $method, array $args): mixed
            {
                return CookieManager::{$method}(...$args);
            }
        };
    }
}
