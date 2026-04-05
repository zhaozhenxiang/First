<?php

declare(strict_types=1);

if (!function_exists('flash')) {
    /**
     * 设置 Flash 消息
     */
    function flash(string $key, mixed $value = null): mixed
    {
        $manager = session_manager();

        if (func_num_args() === 1) {
            return $manager->getFlash($key);
        }

        $manager->flash($key, $value);
        return null;
    }
}

if (!function_exists('flash_get')) {
    /**
     * 获取 Flash 消息
     */
    function flash_get(string $key, mixed $default = null): mixed
    {
        return session_manager()->getFlash($key, $default);
    }
}

if (!function_exists('flash_pull')) {
    /**
     * 获取并删除 Flash 消息
     */
    function flash_pull(string $key, mixed $default = null): mixed
    {
        return session_manager()->pullFlash($key, $default);
    }
}

if (!function_exists('flash_has')) {
    /**
     * 检查 Flash 消息是否存在
     */
    function flash_has(string $key): bool
    {
        return session_manager()->hasFlash($key);
    }
}

if (!function_exists('flash_all')) {
    /**
     * 获取所有 Flash 消息
     */
    function flash_all(): array
    {
        return session_manager()->getAllFlash();
    }
}

if (!function_exists('flash_clear')) {
    /**
     * 清除所有 Flash 消息
     */
    function flash_clear(): void
    {
        session_manager()->clearFlash();
    }
}

if (!function_exists('flash_reflash')) {
    /**
     * 重新 Flash 数据（保留到下次请求）
     */
    function flash_reflash(array|string $keys = []): void
    {
        session_manager()->reflash($keys);
    }
}

if (!function_exists('old')) {
    /**
     * 获取旧的输入值（用于表单重新填充）
     */
    function old(string $key, mixed $default = ''): mixed
    {
        return $_SESSION['_old_input'][$key] ?? $default;
    }
}
