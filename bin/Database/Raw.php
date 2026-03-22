<?php

declare(strict_types=1);

namespace Bin\Database;

/**
 * 原始 SQL 表达式
 */
class Raw
{
    protected string $value;

    public function __construct(string $value)
    {
        $this->value = $value;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * 创建原始表达式
     */
    public static function expression(string $value): self
    {
        return new self($value);
    }
}
