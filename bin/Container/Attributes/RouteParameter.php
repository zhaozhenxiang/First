<?php

declare(strict_types=1);

namespace Bin\Container\Attributes;

use Attribute;
use Bin\Container\Container;
use Bin\Container\Exceptions\BindingResolutionException;
use Bin\Contracts\ContextualAttribute;
use ReflectionParameter;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class RouteParameter implements ContextualAttribute
{
    public function __construct(private string $name)
    {
    }

    public function resolve(Container $container, ReflectionParameter $parameter): mixed
    {
        if ($container->hasParameterContextValue($this->name)) {
            return $container->getParameterContextValue($this->name);
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        if ($parameter->allowsNull()) {
            return null;
        }

        throw new BindingResolutionException(
            $parameter->getName(),
            "Unable to resolve route parameter [{$this->name}] for parameter [{$parameter->getName()}]"
        );
    }
}
