<?php

declare(strict_types=1);

if (!function_exists('auth')) {
    /**
     * 获取认证管理器或当前用户
     */
    function auth(): ?object
    {
        return \Bin\Auth\AuthManager::user();
    }
}

if (!function_exists('auth_check')) {
    /**
     * 检查用户是否已认证
     */
    function auth_check(): bool
    {
        return \Bin\Auth\AuthManager::check();
    }
}

if (!function_exists('auth_guest')) {
    /**
     * 检查用户是否是访客
     */
    function auth_guest(): bool
    {
        return \Bin\Auth\AuthManager::guest();
    }
}

if (!function_exists('auth_id')) {
    /**
     * 获取当前用户 ID
     */
    function auth_id(): mixed
    {
        return \Bin\Auth\AuthManager::id();
    }
}

if (!function_exists('auth_attempt')) {
    /**
     * 尝试登录用户
     */
    function auth_attempt(array $credentials, bool $remember = false): bool
    {
        return \Bin\Auth\AuthManager::attempt($credentials, $remember);
    }
}

if (!function_exists('auth_login')) {
    /**
     * 登录用户
     */
    function auth_login(object $user, bool $remember = false): void
    {
        \Bin\Auth\AuthManager::login($user, $remember);
    }
}

if (!function_exists('auth_logout')) {
    /**
     * 登出用户
     */
    function auth_logout(): void
    {
        \Bin\Auth\AuthManager::logout();
    }
}

if (!function_exists('csrf_token')) {
    /**
     * 获取 CSRF token
     */
    function csrf_token(): string
    {
        return \Bin\Middleware\CsrfMiddleware::generateToken();
    }
}

if (!function_exists('csrf_field')) {
    /**
     * 生成 CSRF 隐藏字段
     */
    function csrf_field(): string
    {
        return \Bin\Middleware\CsrfMiddleware::field();
    }
}
