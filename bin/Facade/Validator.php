<?php

declare(strict_types=1);

namespace Bin\Facade;

use Bin\Validation\ValidationManager;

/**
 * Validator Facade - 静态代理验证管理器
 *
 * @method static \Bin\Validation\ValidationManager make(array $data, array $rules)
 * @method static array validate(array $data, array $rules, array $messages = [])
 * @method static bool check(array $data, array $rules)
 * @method static void extend(string $rule, \Closure $callback)
 */
class Validator extends Facade
{
    protected function getClassName(): string
    {
        return ValidationManager::class;
    }

    /**
     * 创建验证器实例
     */
    public static function make(array $data, array $rules): ValidationManager
    {
        return ValidationManager::make($data, $rules);
    }
}
