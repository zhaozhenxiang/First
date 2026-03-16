<?php

declare(strict_types=1);

namespace Bin\Contracts;

/**
 * IoC 容器接口
 */
interface ContainerInterface
{
    /**
     * 从容器中解析一个实例
     */
    public function make(string $class): object;

    /**
     * 注册一个 facade
     */
    public function facade(string $class): ?object;

    /**
     * 获取容器单例
     */
    public static function getInstance(): self;
}
