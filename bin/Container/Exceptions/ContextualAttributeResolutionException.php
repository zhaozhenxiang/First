<?php

declare(strict_types=1);

namespace Bin\Container\Exceptions;

/**
 * 上下文属性解析异常
 *
 * 构造函数/方法的参数上的上下文属性（#[Give]、#[Config] 等）解析失败时抛出
 */
class ContextualAttributeResolutionException extends BindingResolutionException
{
}
