<?php

declare(strict_types=1);

namespace Bin\Validation;

/**
 * 流式验证规则构建器
 *
 * 用法：
 *   Rule::required()->string()->max(255)
 *   Rule::required()->email()
 *   Rule::nullable()->integer()->min(0)->max(100)
 *
 * Rule 本身不执行验证，它编译为规则字符串数组，交由 ValidationManager 处理。
 */
class Rule
{
    /** @var array<string> 已添加的规则 */
    protected array $rules = [];

    protected function __construct()
    {
    }

    /**
     * 创建新的规则构建器
     */
    public static function make(): static
    {
        return new static();
    }

    // ==================== 字段存在性 ====================

    public static function required(): static
    {
        return static::make()->addRule('required');
    }

    public static function filled(): static
    {
        return static::make()->addRule('filled');
    }

    public static function nullable(): static
    {
        return static::make()->addRule('nullable');
    }

    public static function prohibited(): static
    {
        return static::make()->addRule('prohibited');
    }

    public static function exclude(): static
    {
        return static::make()->addRule('exclude');
    }

    public static function accepted(): static
    {
        return static::make()->addRule('accepted');
    }

    public static function requiredIf(string $field, string|int|float ...$values): static
    {
        return static::make()->addRule('required_if:' . implode(',', [$field, ...$values]));
    }

    public static function requiredWith(string ...$fields): static
    {
        return static::make()->addRule('required_with:' . implode(',', $fields));
    }

    public static function unique(string $table, ?string $column = null, string|int|null $except = null, ?string $idColumn = null): static
    {
        $params = [$table];
        if ($column !== null || $except !== null || $idColumn !== null) {
            $params[] = $column ?? '';
            if ($except !== null || $idColumn !== null) {
                $params[] = $except ?? '';
                if ($idColumn !== null) {
                    $params[] = $idColumn;
                }
            }
        }

        return static::make()->addRule('unique:' . implode(',', $params));
    }

    public static function exists(string $table, ?string $column = null): static
    {
        return static::make()->addRule('exists:' . implode(',', $column === null ? [$table] : [$table, $column]));
    }

    // ==================== 类型 ====================

    public function string(): static
    {
        return $this->addRule('string');
    }

    public function integer(): static
    {
        return $this->addRule('integer');
    }

    public function numeric(): static
    {
        return $this->addRule('numeric');
    }

    public function boolean(): static
    {
        return $this->addRule('boolean');
    }

    public function array(): static
    {
        return $this->addRule('array');
    }

    public function date(): static
    {
        return $this->addRule('date');
    }

    public function email(): static
    {
        return $this->addRule('email');
    }

    public function url(): static
    {
        return $this->addRule('url');
    }

    public function ip(): static
    {
        return $this->addRule('ip');
    }

    public function json(): static
    {
        return $this->addRule('json');
    }

    public function uuid(): static
    {
        return $this->addRule('uuid');
    }

    public function macAddress(): static
    {
        return $this->addRule('mac_address');
    }

    public function timezone(): static
    {
        return $this->addRule('timezone');
    }

    // ==================== 字符集 ====================

    public function alpha(): static
    {
        return $this->addRule('alpha');
    }

    public function alphaNum(): static
    {
        return $this->addRule('alpha_num');
    }

    public function alphaDash(): static
    {
        return $this->addRule('alpha_dash');
    }

    // ==================== 大小约束 ====================

    public function min(int|float $value): static
    {
        return $this->addRule("min:{$value}");
    }

    public function max(int|float $value): static
    {
        return $this->addRule("max:{$value}");
    }

    public function size(int|float $value): static
    {
        return $this->addRule("size:{$value}");
    }

    public function between(int|float $min, int|float $max): static
    {
        return $this->addRule("between:{$min},{$max}");
    }

    public function gt(string $field): static
    {
        return $this->addRule("gt:{$field}");
    }

    public function lt(string $field): static
    {
        return $this->addRule("lt:{$field}");
    }

    public function gte(string $field): static
    {
        return $this->addRule("gte:{$field}");
    }

    public function lte(string $field): static
    {
        return $this->addRule("lte:{$field}");
    }

    // ==================== 值约束 ====================

    public function in(array $values): static
    {
        return $this->addRule('in:' . implode(',', $values));
    }

    public function notIn(array $values): static
    {
        return $this->addRule('not_in:' . implode(',', $values));
    }

    public function same(string $field): static
    {
        return $this->addRule("same:{$field}");
    }

    public function different(string $field): static
    {
        return $this->addRule("different:{$field}");
    }

    public function confirmed(): static
    {
        return $this->addRule('confirmed');
    }

    public function regex(string $pattern): static
    {
        return $this->addRule("regex:{$pattern}");
    }

    public function startsWith(string ...$values): static
    {
        return $this->addRule('starts_with:' . implode(',', $values));
    }

    public function endsWith(string ...$values): static
    {
        return $this->addRule('ends_with:' . implode(',', $values));
    }

    public function distinct(): static
    {
        return $this->addRule('distinct');
    }

    // ==================== 编译 ====================

    /**
     * 添加规则到列表
     */
    protected function addRule(string $rule): static
    {
        $this->rules[] = $rule;

        return $this;
    }

    /**
     * 编译为规则字符串数组
     *
     * @return array<string>
     */
    public function compile(): array
    {
        return $this->rules;
    }

    /**
     * 魔术方法：直接用作规则时返回编译结果
     */
    public function __toString(): string
    {
        return implode('|', $this->rules);
    }
}
