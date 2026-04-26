<?php

declare(strict_types=1);

namespace Bin\Middleware;

use Bin\App\App;
use Bin\Exception\HttpException;
use Bin\Request\Request;
use Bin\Session\SessionManager;

/**
 * CSRF 防护中间件
 */
class CsrfMiddleware extends Middleware
{
    private static string $tokenName = '_csrf_token';

    /**
     * 处理请求
     */
    public function handle(mixed $request, \Closure $next): mixed
    {
        $method = $request instanceof Request
            ? $request->getMethod()
            : strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        // 安全方法跳过验证
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
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
            throw new HttpException(403, 'CSRF token validation failed');
        }

        return $next($request);
    }

    /**
     * 生成或获取 CSRF token
     */
    public static function generateToken(): string
    {
        $session = self::session();
        $token = $session->getCsrfToken();

        if ($token === null) {
            $token = $session->putCsrfToken();
        }

        return $token;
    }

    /**
     * 验证 CSRF token
     */
    public static function validateToken(?string $token): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        return self::session()->verifyCsrfToken($token);
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

    private static function session(): SessionManager
    {
        /** @var SessionManager $session */
        $session = App::getInstance()->make(SessionManager::class);

        return $session;
    }
}
