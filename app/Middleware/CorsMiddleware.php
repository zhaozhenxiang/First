<?php

declare(strict_types=1);

namespace App\Middleware;

use Bin\Middleware\Middleware;
use Closure;

/**
 * CorsMiddleware 中间件
 */
class CorsMiddleware extends Middleware
{
    /**
     * 处理请求
     *
     * 前置逻辑写在 $next($request) 之前，后置逻辑写在之后。
     */
    public function handle(mixed $request, Closure $next): mixed
    {
        // 在这里编写中间件逻辑

        $response = $next($request);

        return $response;
    }
}
