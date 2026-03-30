<?php

declare(strict_types=1);

namespace Bin\Database;

/**
 * Attribute 值对象 - 用于定义模型属性的访问器和修改器
 */
class Attribute
{
    public ?\Closure $get;
    public ?\Closure $set;

    public function __construct(?callable $get = null, ?callable $set = null)
    {
        $this->get = $get !== null ? \Closure::fromCallable($get) : null;
        $this->set = $set !== null ? \Closure::fromCallable($set) : null;
    }

    /**
     * 创建一个带 get 和 set 的 Attribute
     */
    public static function make(?callable $get = null, ?callable $set = null): self
    {
        return new self($get, $set);
    }

    /**
     * 仅创建 get 访问器
     */
    public static function get(callable $get): self
    {
        return new self(get: $get);
    }

    /**
     * 仅创建 set 修改器
     */
    public static function set(callable $set): self
    {
        return new self(set: $set);
    }
}
