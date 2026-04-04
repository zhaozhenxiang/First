<?php

declare(strict_types=1);

namespace Bin\Psr\Container;

/**
 * PSR-11 未找到异常接口
 *
 * 当容器中找不到条目时抛出的异常应实现此接口
 */
interface NotFoundExceptionInterface extends ContainerExceptionInterface
{
}
