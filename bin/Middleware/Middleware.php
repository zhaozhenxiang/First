<?php

declare(strict_types=1);

namespace Bin\Middleware;

use Closure;

/**
 * 中间件基类
 *
 * 适配 Pipeline 洋葱模型：
 * - handle($request, Closure $next): 进入请求时的处理
 * - terminate($request, $response): 响应发送后的后置处理
 *
 * 用法：
 *   class MyMiddleware extends Middleware
 *   {
 *       public function handle($request, Closure $next): mixed
 *       {
 *           // 前置逻辑
 *           $response = $next($request);  // 传递给下一层
 *           // 后置逻辑
 *           return $response;
 *       }
 *   }
 */
abstract class Middleware
{
    /**
     * 中间件参数（从 throttle:60,1 解析而来）
     */
    protected array $options = [];

    /**
     * 设置中间件参数
     */
    public function setOptions(array $options): static
    {
        $this->options = $options;

        return $this;
    }

    /**
     * 处理请求
     *
     * @param  mixed     $request  请求对象
     * @param  Closure   $next     传递给下一层中间件的闭包
     * @return mixed     处理结果或响应
     */
    public function handle(mixed $request, Closure $next): mixed
    {
        return $next($request);
    }

    /**
     * 响应发送后的后置处理（终止中间件）
     *
     * 在响应发送给客户端后调用，用于清理、日志记录等。
     * 中间件不需要实现此方法，除非有后置需求。
     *
     * @param  mixed   $request   请求对象
     * @param  mixed   $response  响应对象
     */
    public function terminate(mixed $request, mixed $response): void
    {
        // 默认空实现，子类可覆盖
    }
}
