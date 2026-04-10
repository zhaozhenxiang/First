<?php

declare(strict_types=1);

namespace Bin\Localization;

/**
 * 复数形式选择器
 *
 * 解析翻译字符串中的复数形式并选择正确的变体。
 *
 * 格式：`{0}no items|{1}one item|[2,*]many items`
 * - `{count}` — 精确匹配
 * - `{min,max}` — 范围匹配
 * - `{count,*}` — count 及以上
 * - 无括号 — 默认/回退
 */
class MessageSelector
{
    /**
     * 根据数量选择正确的复数形式
     */
    public function choose(string $line, int $number, string $locale = 'en'): string
    {
        $segments = explode('|', $line);

        if (count($segments) === 1) {
            return $this->replaceCount($segments[0], $number);
        }

        foreach ($segments as $segment) {
            $segment = trim($segment);

            if (preg_match('/^[\[{](\d+),\s*(\d+|\*)[\]}]\s*(.*)/', $segment, $matches)) {
                $min = (int) $matches[1];
                $max = $matches[2] === '*' ? PHP_INT_MAX : (int) $matches[2];

                if ($number >= $min && $number <= $max) {
                    return $this->replaceCount($matches[3], $number);
                }
            } elseif (preg_match('/^[\[{](\d+)[\]}]\s*(.*)/', $segment, $matches)) {
                if ($number === (int) $matches[1]) {
                    return $this->replaceCount($matches[2], $number);
                }
            } else {
                // 默认/回退形式（无括号前缀）
                // 仅在没有精确匹配时使用
                $default = $segment;
            }
        }

        // 回退到最后一个段或默认段
        return $this->replaceCount($default ?? $segments[count($segments) - 1], $number);
    }

    /**
     * 替换 :count 占位符
     */
    protected function replaceCount(string $line, int $number): string
    {
        return str_replace(':count', (string) $number, $line);
    }
}
