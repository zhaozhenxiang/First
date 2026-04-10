<?php

declare(strict_types=1);

namespace App\Middleware;

use Bin\Middleware\Middleware;

/**
 * CorsMiddleware 中间件
 */
class CorsMiddleware extends Middleware
{
    /**
     * 处理请求
     */
    public function handle(mixed $request): bool
    {
        // 在这里编写中间件逻辑
        // 返回 true 继续执行，返回 false 中断请求

        return true;
    }
}
