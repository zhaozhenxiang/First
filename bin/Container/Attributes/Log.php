<?php

declare(strict_types=1);

namespace Bin\Container\Attributes;

use Attribute;
use Bin\Container\Container;
use Bin\Contracts\ContextualAttribute;
use Bin\Log\LogManager;
use ReflectionParameter;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class Log implements ContextualAttribute
{
    public function __construct(private ?string $channel = null)
    {
    }

    public function resolve(Container $container, ReflectionParameter $parameter): mixed
    {
        return LogManager::getInstance()->channelFor($this->channel);
    }
}
