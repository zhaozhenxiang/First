<?php

declare(strict_types=1);

namespace Bin\Contracts;

use Bin\Container\ContextualBindingBuilder;

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

    /**
     * 绑定服务到容器
     */
    public function bind(string $abstract, callable|string|null $concrete = null, bool $shared = false): void;

    /**
     * 绑定单例
     */
    public function singleton(string $abstract, callable|string|null $concrete = null): void;

    /**
     * 绑定实例
     */
    public function instance(string $abstract, object $instance): void;

    /**
     * 绑定别名
     */
    public function alias(string $abstract, string $alias): void;

    /**
     * 检查是否已绑定
     */
    public function bound(string $abstract): bool;

    /**
     * 检查容器中是否有某个服务
     */
    public function has(string $abstract): bool;

    /**
     * 扩展服务
     */
    public function extend(string $abstract, \Closure $callback): void;

    /**
     * 条件绑定流畅接口
     */
    public function when(string|array $concrete): ContextualBindingBuilder;
}
