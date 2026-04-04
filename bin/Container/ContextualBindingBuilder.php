<?php

declare(strict_types=1);

namespace Bin\Container;

/**
 * 上下文绑定构建器
 *
 * 提供流畅接口：when(ClassA::class)->needs(InterfaceB::class)->give(ConcreteB::class)
 */
class ContextualBindingBuilder
{
    /**
     * 容器实例
     */
    protected Container $container;

    /**
     * 需要上下文绑定的具体类
     * @var string[]
     */
    protected array $concretes;

    /**
     * 需要绑定的抽象名
     */
    protected ?string $needs = null;

    /**
     * @param string[] $concretes
     */
    public function __construct(Container $container, array $concretes)
    {
        $this->container = $container;
        $this->concretes = $concretes;
    }

    /**
     * 指定需要的抽象名
     */
    public function needs(string $abstract): self
    {
        $this->needs = $abstract;

        return $this;
    }

    /**
     * 提供实现
     */
    public function give(callable|string|int $implementation): void
    {
        foreach ($this->concretes as $concrete) {
            $this->container->addContextualBinding($concrete, $this->needs, $implementation);
        }
    }

    /**
     * 提供标签下的所有服务
     */
    public function giveTagged(string $tag): void
    {
        $this->give(function () use ($tag) {
            return $this->container->tagged($tag);
        });
    }
}
