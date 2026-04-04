<?php

declare(strict_types=1);

namespace Bin\Container\Exceptions;

use RuntimeException;

/**
 * 绑定解析异常
 *
 * 当容器无法解析服务时抛出
 */
class BindingResolutionException extends RuntimeException
{
    /**
     * 无法解析的抽象名
     */
    protected string $abstract;

    /**
     * 创建异常实例
     */
    public function __construct(string $abstract, string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        $this->abstract = $abstract;

        $message = $message ?: "Unable to resolve binding [{$abstract}]";

        parent::__construct($message, $code, $previous);
    }

    /**
     * 获取抽象名
     */
    public function getAbstract(): string
    {
        return $this->abstract;
    }

    /**
     * 创建依赖解析失败的异常
     */
    public static function dependencyFailed(string $abstract, string $dependency, ?\Throwable $previous = null): self
    {
        return new self(
            $abstract,
            "Unable to resolve [{$abstract}] - dependency [{$dependency}] could not be resolved",
            0,
            $previous
        );
    }
}
