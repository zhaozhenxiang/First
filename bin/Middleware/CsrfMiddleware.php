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

    /**
     * 中间件处理逻辑
     */
    protected function handle(array $param): mixed
    {
        /** @var Request $request */
        $request = app(Request::class);

        // 跳过安全方法的验证
        $method = $request->getMethod();
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'])) {
            return true;
        }

        // 获取 token
        $token = $request->input(self::$tokenName)
            ?? ($request->header('X-CSRF-Token')
            ?? $request->header('X-XSRF-Token'));

        if (!self::validateToken($token)) {
            http_response_code(403);
            return 'CSRF token validation failed';
        }

        return true;
    }
}
