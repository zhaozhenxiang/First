<?php

declare(strict_types=1);

namespace Bin\Container\Attributes;

use Attribute;
use Bin\Container\Container;
use Bin\Contracts\ContextualAttribute;
use ReflectionParameter;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class Tag implements ContextualAttribute
{
    public function __construct(private string $tag)
    {
    }

    public function resolve(Container $container, ReflectionParameter $parameter): mixed
    {
        return $container->tagged($this->tag);
    }
}
