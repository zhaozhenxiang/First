<?php

declare(strict_types=1);

namespace Bin\Contracts;

use Bin\Route\Route;

/**
 * 路由器接口
 */
interface RouterInterface
{
    /**
     * 获取匹配到的路由
     */
    public static function getRoute(): ?Route;

    /**
     * 添加 GET 路由
     */
    public static function get(string $path, mixed $action): Route;

    /**
     * 添加 POST 路由
     */
    public static function post(string $path, mixed $action): Route;

    /**
     * 批量添加 GET 路由
     */
    public static function getArray(array $routes): void;

    /**
     * 中间件组
     */
    public static function middle(array $param, \Closure $callback): void;
}
