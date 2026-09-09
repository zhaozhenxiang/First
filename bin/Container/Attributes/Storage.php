<?php

declare(strict_types=1);

namespace Bin\Container\Attributes;

use Attribute;
use Bin\Container\Container;
use Bin\Contracts\ContextualAttribute;
use Bin\Filesystem\StorageManager;
use ReflectionParameter;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class Storage implements ContextualAttribute
{
    public function __construct(private ?string $disk = null)
    {
    }

    public function resolve(Container $container, ReflectionParameter $parameter): mixed
    {
        return StorageManager::getInstance()->disk($this->disk);
    }
}
