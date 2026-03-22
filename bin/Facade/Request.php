<?php

declare(strict_types=1);

namespace Bin\Facade;

class Request extends Facade
{
    protected function getClassName(): string
    {
        return \Bin\Request\Request::class;
    }

    /**
     * 获取请求路径
     */
    public static function path(): string
    {
        return static::getInstance()->getPath();
    }

    /**
     * 获取请求方法
     */
    public static function method(): string
    {
        return static::getInstance()->getMethod();
    }

    /**
     * 获取所有输入数据
     */
    public static function all(): array
    {
        return static::getInstance()->getData();
    }

    /**
     * 获取指定输入值
     */
    public static function input(string $key, mixed $default = null): mixed
    {
        return static::getInstance()[$key] ?? $default;
    }

    /**
     * 检查是否是 POST 请求
     */
    public static function isPost(): bool
    {
        return static::method() === 'POST';
    }

    /**
     * 检查是否是 GET 请求
     */
    public static function isGet(): bool
    {
        return static::method() === 'GET';
    }

    /**
     * 检查是否是 AJAX 请求
     */
    public static function isAjax(): bool
    {
        return static::getInstance()->isAjax();
    }
}