<?php

declare(strict_types=1);

namespace Bin\Container\Exceptions;

use Bin\Psr\Container\ContainerExceptionInterface;

/**
 * PSR-11 容器异常
 *
 * 条目已知（已绑定或可构建）但解析失败时抛出；
 * 未知的标识符请使用 NotFoundException
 */
class ContainerException extends BindingResolutionException implements ContainerExceptionInterface
{
}
