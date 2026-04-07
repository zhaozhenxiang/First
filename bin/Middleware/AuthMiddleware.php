<?php

declare(strict_types=1);

namespace Bin\Middleware;

use Bin\Auth\AuthManager;

/**
 * 认证中间件
 *
 * 确保用户已登录才能访问受保护的路由
 */
class AuthMiddleware extends Middleware
{
    /**
     * 处理请求
     */
    public function handle(mixed $request, \Closure $next): mixed
    {
        // 检查用户是否已认证
        if (!AuthManager::check()) {
            return $this->unauthenticated($request);
        }

        return $next($request);
    }

    /**
     * 未认证响应
     */
    protected function unauthenticated(mixed $request): mixed
    {
        if (is_ajax()) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode([
                'error' => 'Unauthenticated',
                'message' => 'You must be logged in to access this resource.',
            ]);
            exit;
        }

        $redirectUrl = $this->options[0] ?? '/login';
        header("Location: {$redirectUrl}");
        http_response_code(302);
        exit;
    }
}

/**
 * 访客中间件
 *
 * 确保用户未登录才能访问（如登录、注册页面）
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
            header("Location: {$redirectUrl}");
            http_response_code(302);
            exit;
        }

        return $next($request);
    }
}
