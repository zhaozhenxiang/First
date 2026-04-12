<?php

declare(strict_types=1);

namespace Bin\Middleware;

use Bin\Auth\Throttle;
use Bin\Exception\RateLimitExceededException;

/**
 * 速率限制中间件
 *
 * 支持参数格式：throttle:60,1
 *   - 60 = max_attempts
 *   - 1  = decay_minutes
 */
class RateLimitMiddleware extends Middleware
{
    /**
     * 处理请求
     */
    public function handle(mixed $request, \Closure $next): mixed
    {
        // 从 options 解析参数（throttle:60,1 → [60, 1]）
        $maxAttempts = (int) ($this->options[0] ?? 60);
        $decaySeconds = (int) ($this->options[1] ?? 60) * 60; // 分钟转秒
        $keyPrefix = $this->options[2] ?? 'default';

        $identifier = Throttle::ip();

        // 检查速率限制
        $passed = Throttle::custom($identifier, $keyPrefix, $maxAttempts, $decaySeconds);

        if (!$passed) {
            $this->throwLimitExceeded($identifier, $keyPrefix, $maxAttempts, $decaySeconds);
        }

        return $next($request);
    }

    /**
     * 限制超出时抛出异常
     *
     * 由 ExceptionHandler 统一渲染 429 响应。
     */
    protected function throwLimitExceeded(
        string $identifier,
        string $keyPrefix,
        int $maxAttempts,
        int $decaySeconds
    ): never {
        $availableIn = Throttle::availableIn($identifier, $keyPrefix, $decaySeconds);

        throw new RateLimitExceededException(
            'Rate limit exceeded. Please try again later.',
            null,
            ['Retry-After' => (string) ceil($availableIn)]
        );
    }
}
