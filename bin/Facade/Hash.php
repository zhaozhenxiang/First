<?php

declare(strict_types=1);

namespace Bin\Facade;

use Bin\Auth\HashManager;

/**
 * Hash Facade - 静态代理密码哈希管理器
 *
 * @method static string make(string $value, array $options = [])
 * @method static bool check(string $value, string $hashedValue)
 * @method static bool needsRehash(string $hashedValue, array $options = [])
 * @method static string bcrypt(string $value, int $rounds = 10)
 */
class Hash extends Facade
{
    protected function getClassName(): string
    {
        return HashManager::class;
    }

    protected static function getInstance(): object
    {
        // HashManager 是纯静态类，返回自身实例用于 __callStatic
        static $instance;
        return $instance ??= new class {
            public function __call(string $method, array $args): mixed
            {
                return HashManager::{$method}(...$args);
            }
        };
    }
}
