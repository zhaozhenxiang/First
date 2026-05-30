<?php

declare(strict_types=1);

namespace Bin\Facade;

use Bin\Validation\ValidationManager;

/**
 * Validator Facade - 静态代理验证管理器
 *
 * @method static \Bin\Validation\ValidationManager make(array $data, array $rules, array $messages = [], array $attributes = [])
 * @method static array validate(array $data, array $rules, array $messages = [], array $attributes = [])
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
    public static function make(array $data, array $rules, array $messages = [], array $attributes = []): ValidationManager
    {
        $validator = ValidationManager::make($data, $rules);

        if ($messages !== []) {
            $validator->setCustomMessages($messages);
        }

        if ($attributes !== []) {
            $validator->setAliases($attributes);
        }

        return $validator;
    }

    /**
     * 验证数据并在失败时抛出验证异常
     */
    public static function validate(array $data, array $rules, array $messages = [], array $attributes = []): array
    {
        return self::make($data, $rules, $messages, $attributes)->validateOrFail();
    }
}
