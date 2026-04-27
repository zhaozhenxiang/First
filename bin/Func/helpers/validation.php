<?php

declare(strict_types=1);

if (!function_exists('validate')) {
    /**
     * 验证输入（委托给 ValidationManager）
     */
    function validate(array $data, array $rules): array
    {
        return \Bin\Validation\ValidationManager::make($data, $rules)
            ->validateOrFail();
    }
}

if (!function_exists('escape')) {
    /**
     * 转义 HTML 特殊字符
     */
    function escape(string $value): string
    {
        return \Bin\Validation\Validator::escape($value);
    }
}

if (!function_exists('now')) {
    /**
     * 获取当前时间
     */
    function now(): \DateTime
    {
        return new \DateTime();
    }
}
