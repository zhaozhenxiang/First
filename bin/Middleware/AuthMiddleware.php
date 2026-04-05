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
    protected function handle(array $param = []): mixed
    {
        // 检查用户是否已认证
        if (!AuthManager::check()) {
            // 未认证 - 返回 401 或重定向
            if ($this->isAjax()) {
                // AJAX 请求返回 JSON
                header('Content-Type: application/json');
                http_response_code(401);
                echo json_encode([
                    'error' => 'Unauthenticated',
                    'message' => 'You must be logged in to access this resource.'
                ]);
                exit;
            } else {
                // 普通请求重定向到登录页
                $loginUrl = $param['redirect'] ?? '/login';
                header("Location: {$loginUrl}");
                http_response_code(302);
                exit;
            }
        }

        return true;
    }

    /**
     * 检查是否是 AJAX 请求
     */
    private function isAjax(): bool
    {
        return is_ajax();
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
    protected function handle(array $param = []): mixed
    {
        // 如果已认证，重定向到首页
        if (AuthManager::check()) {
            $redirectUrl = $param['redirect'] ?? '/';
            header("Location: {$redirectUrl}");
            http_response_code(302);
            exit;
        }

        return true;
    }
}
