<?php

declare(strict_types=1);

if (!function_exists('data_get')) {
    /**
     * 使用点号语法从嵌套数组中获取值
     */
    function data_get(array $data, ?string $key, mixed $default = null): mixed
    {
        if ($key === null || $key === '') {
            return $data;
        }

        $segments = explode('.', $key);
        $value = $data;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}

if (!function_exists('data_set')) {
    /**
     * 使用点号语法设置嵌套数组的值
     */
    function data_set(array &$data, string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $current = &$data;

        foreach ($segments as $i => $segment) {
            if ($i === count($segments) - 1) {
                $current[$segment] = $value;
            } else {
                if (!isset($current[$segment]) || !is_array($current[$segment])) {
                    $current[$segment] = [];
                }
                $current = &$current[$segment];
            }
        }
    }
}

if (!function_exists('data_has')) {
    /**
     * 使用点号语法检查嵌套数组中键是否存在
     */
    function data_has(array $data, string $key): bool
    {
        $segments = explode('.', $key);
        $current = $data;

        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return false;
            }
            $current = $current[$segment];
        }

        return true;
    }
}
