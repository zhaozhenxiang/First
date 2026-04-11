<?php

declare(strict_types=1);

namespace Bin\Middleware;

use Closure;

/**
 * 洋葱模型中间件管道
 *
 * 每个中间件收到 $next 闭包，调用 $next($request) 传递给下一层。
 * $next 返回后可处理后置逻辑（如 CORS header、日志记录）。
 *
 * 用法：
 *   (new Pipeline)
 *       ->send($request)
 *       ->through([MiddlewareA::class, MiddlewareB::class])
 *       ->then(fn($req) => 'controller result');
 *
 * terminate 支持：
 *   $response = $pipeline->then(...);
 *   $pipeline->terminate($request, $response);  // 按逆序调用 terminate
 */
class Pipeline
{
    /** 待处理的中间件列表 */
    protected array $pipes = [];

    /** 被传递的对象（通常是 Request） */
    protected mixed $passable = '';

    /** 中间件解析回调 */
    protected ?Closure $resolver = null;

    /** 中间件方法名 */
    protected string $method = 'handle';

    /** @var Middleware[] 已解析的中间件实例（用于 terminate） */
    protected array $resolvedInstances = [];

    /**
     * 设置被传递的对象
     */
    public function send(mixed $passable): static
    {
        $this->passable = $passable;

        return $this;
    }

    /**
     * 设置要经过的中间件列表
     *
     * @param  array  $pipes  中间件类名、闭包或实例
     */
    public function through(array $pipes): static
    {
        $this->pipes = $pipes;

        return $this;
    }

    /**
     * 设置中间件解析器（用于从容器解析中间件实例）
     */
    public function resolver(Closure $resolver): static
    {
        $this->resolver = $resolver;

        return $this;
    }

    /**
     * 设置中间件方法名（默认 handle）
     */
    public function via(string $method): static
    {
        $this->method = $method;

        return $this;
    }

    /**
     * 执行管道，最终调用给定的目标闭包
     */
    public function then(Closure $destination): mixed
    {
        $pipeline = array_reduce(
            array_reverse($this->pipes),
            $this->carry(),
            $this->prepareDestination($destination)
        );

        return $pipeline($this->passable);
    }

    /**
     * 执行管道，最终返回 passable 本身（无目标闭包）
     */
    public function thenReturn(): mixed
    {
        return $this->then(fn(mixed $passable) => $passable);
    }

    /**
     * 准备最终目标闭包
     */
    protected function prepareDestination(Closure $destination): Closure
    {
        return function (mixed $passable) use ($destination): mixed {
            return $destination($passable);
        };
    }

    /**
     * 获取洋葱模型的每一层包装器
     *
     * 这是 Pipeline 的核心：
     * 返回一个闭包，该闭包接收下一层的 $next，
     * 解析当前中间件并调用其 handle($passable, $next)。
     */
    protected function carry(): Closure
    {
        return function (Closure $stack, mixed $pipe): Closure {
            return function (mixed $passable) use ($stack, $pipe): mixed {
                // 闭包直接调用
                if ($pipe instanceof Closure) {
                    return $pipe($passable, $stack);
                }

                // 解析中间件实例
                $instance = $this->resolveMiddleware($pipe);

                // 调用中间件的 handle 方法
                return $instance->{$this->method}($passable, $stack);
            };
        };
    }

    /**
     * 解析中间件实例
     */
    protected function resolveMiddleware(mixed $pipe): Middleware
    {
        // 已经是实例
        if ($pipe instanceof Middleware) {
            $this->resolvedInstances[] = $pipe;
            return $pipe;
        }

        // 字符串类名：优先用 resolver（容器），否则直接 new
        if (is_string($pipe)) {
            if ($this->resolver !== null) {
                $resolved = ($this->resolver)($pipe);
                if ($resolved instanceof Middleware) {
                    $this->resolvedInstances[] = $resolved;
                    return $resolved;
                }
            }

            $instance = new $pipe();
            $this->resolvedInstances[] = $instance;
            return $instance;
        }

        throw new \RuntimeException(
            sprintf('Invalid middleware type: %s', get_debug_type($pipe))
        );
    }

    /**
     * 在响应发送后调用所有中间件的 terminate 方法
     *
     * 按管道的逆序调用，确保最内层中间件先 terminate。
     *
     * @param mixed $request  请求对象
     * @param mixed $response 响应对象
     */
    public function terminate(mixed $request, mixed $response): void
    {
        // 逆序调用 terminate（最内层先执行）
        foreach (array_reverse($this->resolvedInstances) as $middleware) {
            $middleware->terminate($request, $response);
        }
    }
}
