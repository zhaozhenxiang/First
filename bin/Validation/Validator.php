<?php

declare(strict_types=1);

namespace Bin\Validation;

use Bin\Request\Request;

/**
 * 输入验证器 - 提供安全的输入验证和转义
 */
class Validator
{
    /**
     * 转义 HTML 特殊字符（XSS 防护）
     */
    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * 验证并转义字符串输入
     */
    public static function string(?string $value, int $maxLength = 1000): string
    {
        if ($value === null) {
            return '';
        }

        $value = trim($value);

        if (strlen($value) > $maxLength) {
            throw new \InvalidArgumentException("Input exceeds maximum length of {$maxLength}");
        }

        return self::escape($value);
    }

    /**
     * 验证整数输入
     */
    public static function int(mixed $value, int $min = null, int $max = null): int
    {
        if (!is_numeric($value)) {
            throw new \InvalidArgumentException('Input must be numeric');
        }

        $intValue = (int) $value;

        if ($min !== null && $intValue < $min) {
            throw new \InvalidArgumentException("Input must be at least {$min}");
        }

        if ($max !== null && $intValue > $max) {
            throw new \InvalidArgumentException("Input must be at most {$max}");
        }

        return $intValue;
    }

    /**
     * 验证电子邮件格式
     */
    public static function email(string $value): string
    {
        $value = trim($value);

        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email format');
        }

        return $value;
    }

    /**
     * 验证 URL 格式
     */
    public static function url(string $value): string
    {
        $value = trim($value);

        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('Invalid URL format');
        }

        return $value;
    }

    /**
     * 验证数组输入
     */
    public static function array(mixed $value, int $maxItems = 100): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException('Input must be an array');
        }

        if (count($value) > $maxItems) {
            throw new \InvalidArgumentException("Array exceeds maximum items of {$maxItems}");
        }

        return $value;
    }

    /**
     * 验证并清理 SQL LIKE 模式（防止 SQL 注入）
     */
    public static function likePattern(string $value): string
    {
        // 移除危险的通配符
        $value = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
        return self::escape($value);
    }

    /**
     * 从 Request 中获取并验证字符串
     */
    public static function fromRequest(string $key, int $maxLength = 1000): string
    {
        /** @var Request $request */
        $request = app(Request::class);
        $value = $request->input($key);

        return self::string($value ?? '', $maxLength);
    }

    /**
     * 批量验证数组中的所有字符串值
     */
    public static function escapeArray(array $data): array
    {
        $escaped = [];

        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $escaped[$key] = self::escape($value);
            } elseif (is_array($value)) {
                $escaped[$key] = self::escapeArray($value);
            } else {
                $escaped[$key] = $value;
            }
        }

        return $escaped;
    }
}
