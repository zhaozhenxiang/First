<?php

declare(strict_types=1);

namespace Bin\Container\Attributes;

use Attribute;
use Bin\Container\Container;
use Bin\Container\Exceptions\BindingResolutionException;
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
        if ($this->connection !== null && $this->connection !== 'default') {
            throw new BindingResolutionException(
                $parameter->getName(),
                "Database connection [{$this->connection}] is not available; First currently exposes the active default connection"
            );
        }

        if ($container->bound('db.connection')) {
            return $container->make('db.connection');
        }

        return ConnectionManager::getConnection();
    }
}
