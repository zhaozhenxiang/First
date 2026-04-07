<?php

declare(strict_types=1);

namespace Bin\Middleware;

/**
 * 中间件名称解析器
 *
 * 职责：
 * 1. 从 "throttle:60,1" 格式解析出 [类名, [60, 1]]
 * 2. 通过别名映射表将短名解析为完整类名
 */
class MiddlewareNameResolver
{
    /**
     * 解析中间件为 [类名, 参数] 格式
     *
     * 支持格式：
 *   - "auth"              → [AuthMiddleware::class, []]
     *   - "throttle:60,1"    → [RateLimitMiddleware::class, ['max_attempts' => 60, 'decay_seconds' => 1]]
     *   - AuthMiddleware::class → [AuthMiddleware::class, []]
     *
     * @param  string        $middleware  中间件名（可能带参数）
     * @param  array<string, class-string<Middleware>>  $aliasMap  别名映射表
     * @return array{0: class-string<Middleware>, 1: array}
     */
    public static function resolve(string $middleware, array $aliasMap = []): array
    {
        // 分离名称和参数
        [$name, $parameters] = static::parseMiddlewareString($middleware);

        // 先查别名表
        $class = $aliasMap[$name] ?? $name;

        return [$class, $parameters];
    }

    /**
     * 解析中间件字符串为 [name, parameters]
     *
     * "throttle:60,1" → ["throttle", [60, 1]]
     */
    public static function parseMiddlewareString(string $middleware): array
    {
        $segments = explode(':', $middleware, 2);
        $name = $segments[0];
        $parameters = [];

        if (isset($segments[1])) {
            $parameters = array_map('trim', explode(',', $segments[1]));
        }

        return [$name, $parameters];
    }

    /**
     * 批量解析中间件列表
     *
     * @param  array<string>  $middlewares
     * @param  array<string, class-string<Middleware>>  $aliasMap
     * @return array<array{0: class-string<Middleware>, 1: array}>
     */
    public static function resolveAll(array $middlewares, array $aliasMap = []): array
    {
        return array_map(
            fn(string $m) => static::resolve($m, $aliasMap),
            $middlewares
        );
    }
}
