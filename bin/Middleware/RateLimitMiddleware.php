<?php

declare(strict_types=1);

namespace Bin\Middleware;

use Bin\Auth\Throttle;

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
            return $this->limitExceeded($identifier, $keyPrefix, $maxAttempts, $decaySeconds);
        }

        // 添加速率限制响应头
        $this->addRateLimitHeaders($identifier, $keyPrefix, $maxAttempts, $decaySeconds);

        return $next($request);
    }

    /**
     * 限制超出响应
     */
    protected function limitExceeded(
        string $identifier,
        string $keyPrefix,
        int $maxAttempts,
        int $decaySeconds
    ): mixed {
        $remaining = Throttle::remaining($identifier, $keyPrefix, $maxAttempts, $decaySeconds);
        $availableIn = Throttle::availableIn($identifier, $keyPrefix, $decaySeconds);

        if (is_ajax()) {
            header('Content-Type: application/json');
            http_response_code(429);
            echo json_encode([
                'error' => 'Too Many Requests',
                'message' => 'Rate limit exceeded. Please try again later.',
                'retry_after' => $availableIn,
                'limit' => $maxAttempts,
                'remaining' => $remaining,
            ]);
            exit;
        }

        http_response_code(429);
        $retryAfter = ceil($availableIn);
        echo "<!DOCTYPE html>
<html>
<head>
    <title>Too Many Requests</title>
    <style>
        body { font-family: -apple-system, sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; background: #f5f5f5; }
        .container { text-align: center; padding: 40px; background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #e74c3c; margin-bottom: 20px; }
        p { color: #555; line-height: 1.6; }
        .retry-after { background: #e74c3c; color: white; padding: 10px 20px; border-radius: 4px; display: inline-block; margin-top: 20px; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>429 - Too Many Requests</h1>
        <p>You've made too many requests. Please slow down and try again later.</p>
        <div class='retry-after'>Please wait {$retryAfter} seconds before trying again.</div>
    </div>
</body>
</html>";
        exit;
    }

    /**
     * 添加速率限制响应头
     */
    protected function addRateLimitHeaders(
        string $identifier,
        string $keyPrefix,
        int $maxAttempts,
        int $decaySeconds
    ): void {
        $remaining = Throttle::remaining($identifier, $keyPrefix, $maxAttempts, $decaySeconds);
        $availableIn = Throttle::availableIn($identifier, $keyPrefix, $decaySeconds);

        header("X-RateLimit-Limit: {$maxAttempts}");
        header("X-RateLimit-Remaining: {$remaining}");
        header("X-RateLimit-Reset: " . (time() + $availableIn));

        if ($remaining === 0) {
            header("Retry-After: {$availableIn}");
        }
    }
}
