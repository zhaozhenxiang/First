<?php

declare(strict_types=1);

namespace Bin\Container\Attributes;

use Attribute;
use Bin\Cache\CacheManager;
use Bin\Container\Container;
use Bin\Contracts\ContextualAttribute;
use ReflectionParameter;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class Cache implements ContextualAttribute
{
    public function __construct(private ?string $store = null)
    {
    }

    public function resolve(Container $container, ReflectionParameter $parameter): mixed
    {
        return CacheManager::getInstance()->storeFor($this->store);
    }
}
