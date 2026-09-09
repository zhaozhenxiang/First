<?php

declare(strict_types=1);

namespace Bin\Container\Attributes;

use Attribute;
use Bin\Auth\AuthManager;
use Bin\Container\Container;
use Bin\Container\Exceptions\BindingResolutionException;
use Bin\Contracts\ContextualAttribute;
use ReflectionParameter;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class Auth implements ContextualAttribute
{
    public function __construct(private ?string $guard = null)
    {
    }

    public function resolve(Container $container, ReflectionParameter $parameter): mixed
    {
        if ($this->guard !== null && $this->guard !== 'default') {
            throw new BindingResolutionException(
                $parameter->getName(),
                "Auth guard [{$this->guard}] is not available; First currently exposes the default AuthManager"
            );
        }

        return AuthManager::getInstance();
    }
}
