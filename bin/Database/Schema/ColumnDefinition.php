<?php

declare(strict_types=1);

namespace Bin\Database\Schema;

/**
 * 列定义
 */
class ColumnDefinition
{
    protected string $type;

    public ?string $name = null;

    public int|null $length = null;

    public int|null $precision = null;

    public int|null $scale = null;

    public bool $unsigned = false;

    public bool $nullable = false;

    public mixed $default = null;

    public bool $autoIncrement = false;

    public bool $primary = false;

    public bool $unique = false;

    public bool $useCurrent = false;

    public bool $useCurrentOnUpdate = false;

    public ?string $comment = null;

    public bool $first = false;

    public ?string $after = null;

    public array $allowed = [];

    public ?string $charset = null;

    public ?string $collation = null;

    public function __construct(string $type, ?string $name = null)
    {
        $this->type = $type;
        $this->name = $name;
    }

    /**
     * 设置长度
     */
    public function length(int $length): self
    {
        $this->length = $length;
        return $this;
    }

    /**
     * 设置精度
     */
    public function precision(int $precision): self
    {
        $this->precision = $precision;
        return $this;
    }

    /**
     * 设置小数位
     */
    public function places(int $places): self
    {
        $this->scale = $places;
        return $this;
    }

    /**
     * 设置总数（decimal/float）
     */
    public function total(int $total): self
    {
        $this->precision = $total;
        return $this;
    }

    /**
     * 无符号
     */
    public function unsigned(bool $unsigned = true): self
    {
        $this->unsigned = $unsigned;
        return $this;
    }

    /**
     * 可为空
     */
    public function nullable(bool $nullable = true): self
    {
        $this->nullable = $nullable;
        return $this;
    }

    /**
     * 默认值
     */
    public function default(mixed $value): self
    {
        $this->default = $value;
        return $this;
    }

    /**
     * 自增
     */
    public function autoIncrement(bool $autoIncrement = true): self
    {
        $this->autoIncrement = $autoIncrement;
        return $this;
    }

    /**
     * 主键
     */
    public function primary(bool $primary = true): self
    {
        $this->primary = $primary;
        return $this;
    }

    /**
     * 唯一
     */
    public function unique(bool $unique = true): self
    {
        $this->unique = $unique;
        return $this;
    }

    /**
     * 使用当前时间
     */
    public function useCurrent(bool $useCurrent = true): self
    {
        $this->useCurrent = $useCurrent;
        return $this;
    }

    /**
     * 更新时使用当前时间
     */
    public function useCurrentOnUpdate(bool $useCurrentOnUpdate = true): self
    {
        $this->useCurrentOnUpdate = $useCurrentOnUpdate;
        return $this;
    }

    /**
     * 注释
     */
    public function comment(string $comment): self
    {
        $this->comment = $comment;
        return $this;
    }

    /**
     * 放在第一位
     */
    public function first(): self
    {
        $this->first = true;
        return $this;
    }

    /**
     * 放在某列之后
     */
    public function after(string $column): self
    {
        $this->after = $column;
        return $this;
    }

    /**
     * 允许的值（enum/set）
     */
    public function allowed(array $allowed): self
    {
        $this->allowed = $allowed;
        return $this;
    }

    /**
     * 字符集
     */
    public function charset(string $charset): self
    {
        $this->charset = $charset;
        return $this;
    }

    /**
     * 排序规则
     */
    public function collation(string $collation): self
    {
        $this->collation = $collation;
        return $this;
    }

    /**
     * 获取类型
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * 获取名称
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'name' => $this->name,
            'length' => $this->length,
            'precision' => $this->precision,
            'scale' => $this->scale,
            'unsigned' => $this->unsigned,
            'nullable' => $this->nullable,
            'default' => $this->default,
            'autoIncrement' => $this->autoIncrement,
            'primary' => $this->primary,
            'unique' => $this->unique,
            'useCurrent' => $this->useCurrent,
            'useCurrentOnUpdate' => $this->useCurrentOnUpdate,
            'comment' => $this->comment,
            'first' => $this->first,
            'after' => $this->after,
            'allowed' => $this->allowed,
            'charset' => $this->charset,
            'collation' => $this->collation,
        ];
    }
}
