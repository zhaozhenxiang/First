<?php

declare(strict_types=1);

use Bin\Localization\Translator;

if (!function_exists('__')) {
    /**
     * 翻译辅助函数
     */
    function __(string $key, array $replace = [], ?string $locale = null): string
    {
        return Translator::getInstance()->get($key, $replace, $locale);
    }
}

if (!function_exists('trans')) {
    /**
     * 翻译辅助函数（别名）
     */
    function trans(string $key, array $replace = [], ?string $locale = null): string
    {
        return Translator::getInstance()->get($key, $replace, $locale);
    }
}

if (!function_exists('trans_choice')) {
    /**
     * 复数形式翻译辅助函数
     */
    function trans_choice(string $key, int $number, array $replace = [], ?string $locale = null): string
    {
        return Translator::getInstance()->choice($key, $number, $replace, $locale);
    }
}
