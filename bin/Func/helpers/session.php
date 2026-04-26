<?php

declare(strict_types=1);

if (!function_exists('session')) {
    /**
     * 获取/设置 Session 值
     */
    function session(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }
}

if (!function_exists('session_manager')) {
    /**
     * 获取 Session 管理器实例
     */
    function session_manager(): \Bin\Session\SessionManager
    {
        return \Bin\App\App::getInstance()->make(\Bin\Session\SessionManager::class);
    }
}

if (!function_exists('session_get')) {
    /**
     * 获取 Session 值
     */
    function session_get(string $key, mixed $default = null): mixed
    {
        return session_manager()->get($key, $default);
    }
}

if (!function_exists('session_set')) {
    /**
     * 设置 Session 值
     */
    function session_set(string $key, mixed $value): void
    {
        session_manager()->set($key, $value);
    }
}

if (!function_exists('session_has')) {
    /**
     * 检查 Session key 是否存在
     */
    function session_has(string $key): bool
    {
        return session_manager()->has($key);
    }
}

if (!function_exists('session_forget')) {
    /**
     * 删除 Session 值
     */
    function session_forget(string $key): void
    {
        session_manager()->remove($key);
    }
}

if (!function_exists('session_pull')) {
    /**
     * 获取并删除 Session 值
     */
    function session_pull(string $key, mixed $default = null): mixed
    {
        return session_manager()->pull($key, $default);
    }
}

if (!function_exists('session_clear')) {
    /**
     * 清空所有 Session 数据
     */
    function session_clear(): void
    {
        session_manager()->clear();
    }
}

if (!function_exists('session_id')) {
    /**
     * 获取/设置 Session ID
     */
    function session_id(?string $id = null): string
    {
        $manager = session_manager();

        if ($id === null) {
            return $manager->getId();
        }

        $manager->setId($id);
        return $id;
    }
}

if (!function_exists('session_regenerate')) {
    /**
     * 重新生成 Session ID
     */
    function session_regenerate(bool $destroy = false): string
    {
        return session_manager()->regenerate($destroy);
    }
}

if (!function_exists('session_destroy')) {
    /**
     * 销毁 Session
     */
    function session_destroy(): void
    {
        session_manager()->destroy();
    }
}

if (!function_exists('with_old_input')) {
    /**
     * 保存当前输入值到 Flash 数据
     */
    function with_old_input(array $input): void
    {
        session_manager()->flashInput($input);
    }
}
