<?php

declare(strict_types=1);

namespace Bin\Container\Exceptions;

/**
 * 循环依赖异常
 *
 * 当检测到循环依赖链时抛出
 */
class CircularDependencyException extends BindingResolutionException
{
    /**
     * 循环路径
     */
    protected array $path;

    /**
     * 创建异常实例
     */
    public function __construct(string $abstract, array $path = [])
    {
        $this->path = $path;

        $cycle = implode(' -> ', [...$path, $abstract]);
        parent::__construct($abstract, "Circular dependency detected: {$cycle}");
    }

    /**
     * 获取循环路径
     */
    public function getPath(): array
    {
        return $this->path;
    }
}
