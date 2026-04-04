<?php

declare(strict_types=1);

namespace Bin\Container\Exceptions;

use Bin\Psr\Container\NotFoundExceptionInterface;

/**
 * PSR-11 未找到条目异常
 */
class NotFoundException extends BindingResolutionException implements NotFoundExceptionInterface
{
}
