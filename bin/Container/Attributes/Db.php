<?php

declare(strict_types=1);

namespace Bin\Container\Attributes;

use Attribute;
use Bin\Container\Container;
use Bin\Contracts\ContextualAttribute;
use Bin\Database\ConnectionManager;
use ReflectionParameter;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class Db implements ContextualAttribute
{
    public function __construct(private ?string $connection = null)
    {
    }

    public function resolve(Container $container, ReflectionParameter $parameter): mixed
    {
        // 容器显式绑定的默认连接优先（保持既有注入语义）
        if ($this->connection === null || $this->connection === 'default') {
            if ($container->bound('db.connection')) {
                return $container->make('db.connection');
            }

            return ConnectionManager::getConnection();
        }

        return ConnectionManager::getConnection($this->connection);
    }
}
