<?php

declare(strict_types=1);

namespace Bin\Middleware;

use Bin\Request\Request;

/**
 * CSRF 防护中间件
 */
class CsrfMiddleware extends Middleware
{
    private static string $tokenName = '_csrf_token';
    private static ?string $token = null;

    /**
     * 处理请求
     */
    public function handle(mixed $request, \Closure $next): mixed
    {
        $method = $request instanceof Request
            ? $request->getMethod()
            : strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        // 安全方法跳过验证
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'])) {
            return $next($request);
        }

        // 获取 token
        $token = null;
        if ($request instanceof Request) {
            $token = $request->input(self::$tokenName)
                ?? $request->header('X-CSRF-Token')
                ?? $request->header('X-XSRF-Token');
        }

        if (!self::validateToken($token)) {
            http_response_code(403);
            return 'CSRF token validation failed';
        }

        return $next($request);
    }

    /**
     * 生成或获取 CSRF token
     */
    public static function generateToken(): string
    {
        if (self::$token === null) {
            if (isset($_SESSION[self::$tokenName])) {
                self::$token = $_SESSION[self::$tokenName];
            } else {
                self::$token = bin2hex(random_bytes(32));
                $_SESSION[self::$tokenName] = self::$token;
            }
        }

        return self::$token;
    }

    /**
     * 验证 CSRF token
     */
    public static function validateToken(?string $token): bool
    {
        $storedToken = $_SESSION[self::$tokenName] ?? '';

        return hash_equals($storedToken, $token ?? '');
    }

    /**
     * 获取隐藏的 input HTML
     */
    public static function field(): string
    {
        return sprintf(
            '<input type="hidden" name="%s" value="%s">',
            self::$tokenName,
            self::generateToken()
        );
    }
}
