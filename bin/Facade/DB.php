<?php

declare(strict_types=1);

namespace Bin\Facade;

use Bin\Database\ConnectionManager;

/**
 * DB Facade - 静态代理数据库连接管理器
 *
 * @method static \PDO getConnection()
 * @method static void setConnection(?\PDO $connection)
 * @method static void reset()
 */
class DB extends Facade
{
    protected function getClassName(): string
    {
        return ConnectionManager::class;
    }

    protected static function getInstance(): object
    {
        // ConnectionManager 是纯静态类
        static $instance;
        return $instance ??= new class {
            public function __call(string $method, array $args): mixed
            {
                return ConnectionManager::{$method}(...$args);
            }
        };
    }
}
