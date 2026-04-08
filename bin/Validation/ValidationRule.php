<?php

declare(strict_types=1);

namespace Bin\Validation;

/**
 * 自定义验证规则基类
 *
 * 用法：
 *   class UppercaseRule extends ValidationRule
 *   {
 *       public function passes(string $attribute, mixed $value): bool
 *       {
 *           return strtoupper((string) $value) === (string) $value;
 *       }
 *
 *       public function message(): string
 *       {
 *           return ':attribute 必须是大写字母';
 *       }
 *   }
 *
 *   ValidationManager::make($data, ['name' => [new UppercaseRule()]]);
 */
abstract class ValidationRule
{
    /**
     * 判断验证是否通过
     */
    abstract public function passes(string $attribute, mixed $value): bool;

    /**
     * 获取验证失败消息
     */
    abstract public function message(): string;

    /**
     * 转换为字符串表示（用于规则显示）
     */
    public function __toString(): string
    {
        return static::class;
    }
}
