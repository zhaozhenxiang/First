<?php

declare(strict_types=1);

namespace Bin\Middleware;

use Bin\Auth\AuthManager;
use Bin\Exception\AuthenticationException;

/**
 * 认证中间件
 *
 * 确保用户已登录才能访问受保护的路由。
 * 未认证时抛出 AuthenticationException，由 ExceptionHandler 统一渲染。
 */
class AuthMiddleware extends Middleware
{
    /**
     * 处理请求
     */
    public function handle(mixed $request, \Closure $next): mixed
    {
        if (!AuthManager::check()) {
            throw new AuthenticationException();
        }

        return $next($request);
    }
}

/**
 * 访客中间件
 *
 * 确保用户未登录才能访问（如登录、注册页面）。
 * 已认证时重定向。
 */
class GuestMiddleware extends Middleware
{
    /**
     * 处理请求
     */
    public function handle(mixed $request, \Closure $next): mixed
    {
        if (AuthManager::check()) {
            $redirectUrl = $this->options[0] ?? '/';
            return redirect($redirectUrl);
        }

        return $next($request);
    }
}
