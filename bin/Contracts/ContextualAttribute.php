<?php

declare(strict_types=1);

namespace Bin\Contracts;

use Bin\Container\Container;
use ReflectionParameter;

interface ContextualAttribute
{
    public function resolve(Container $container, ReflectionParameter $parameter): mixed;
}
