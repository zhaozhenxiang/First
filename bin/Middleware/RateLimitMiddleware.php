<?php

declare(strict_types=1);

namespace Bin\Middleware;

use Bin\Auth\Throttle;

/**
 * 速率限制中间件
 */
class RateLimitMiddleware extends Middleware
{
    /**
     * 处理请求
     *
     * @param array $param 配置参数:
     *   - max_attempts: 最大尝试次数
     *   - decay_seconds: 时间窗口（秒）
     *   - key_prefix: 键名前缀
     *   - identifier: 标识符类型 (ip, user, both)
     */
    protected function handle(array $param = []): mixed
    {
        $maxAttempts = $param['max_attempts'] ?? 60;
        $decaySeconds = $param['decay_seconds'] ?? 60;
        $keyPrefix = $param['key_prefix'] ?? 'default';
        $identifierType = $param['identifier'] ?? 'ip';

        // 确定标识符
        $identifier = $this->getIdentifier($identifierType);

        // 检查速率限制
        $passed = Throttle::custom($identifier, $keyPrefix, $maxAttempts, $decaySeconds);

        if (!$passed) {
            return $this->limitExceeded($identifier, $keyPrefix, $maxAttempts, $decaySeconds);
        }

        // 添加速率限制响应头
        $this->addRateLimitHeaders($identifier, $keyPrefix, $maxAttempts, $decaySeconds);

        return true;
    }

    /**
     * 获取标识符
     */
    protected function getIdentifier(string $type): string
    {
        return match ($type) {
            'ip' => Throttle::ip(),
            'user' => Throttle::userId() ?? Throttle::ip(),
            'both' => Throttle::userId() ? Throttle::userId() : Throttle::ip(),
            default => Throttle::ip(),
        };
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

        // AJAX 请求返回 JSON
        if ($this->isAjax()) {
            header('Content-Type: application/json');
            http_response_code(429); // Too Many Requests

            echo json_encode([
                'error' => 'Too Many Requests',
                'message' => 'Rate limit exceeded. Please try again later.',
                'retry_after' => $availableIn,
                'limit' => $maxAttempts,
                'remaining' => $remaining,
            ]);
            exit;
        }

        // 普通请求返回 HTML 页面
        http_response_code(429);

        $retryAfter = ceil($availableIn);

        echo "<!DOCTYPE html>
<html>
<head>
    <title>Too Many Requests</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background: #f5f5f5;
        }
        .container {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 { color: #e74c3c; margin-bottom: 20px; }
        p { color: #555; line-height: 1.6; }
        .retry-after {
            background: #e74c3c;
            color: white;
            padding: 10px 20px;
            border-radius: 4px;
            display: inline-block;
            margin-top: 20px;
        }
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

    /**
     * 检查是否是 AJAX 请求
     */
    private function isAjax(): bool
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}
