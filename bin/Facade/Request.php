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
        return static::getInstance()->all();
    }

    /**
     * 获取指定输入值
     */
    public static function input(string $key, mixed $default = null): mixed
    {
        return static::getInstance()->input($key, $default);
    }

    /**
     * 获取查询字符串参数
     */
    public static function query(?string $key = null, mixed $default = null): mixed
    {
        return static::getInstance()->query($key, $default);
    }

    /**
     * 获取 POST 数据
     */
    public static function post(?string $key = null, mixed $default = null): mixed
    {
        return static::getInstance()->post($key, $default);
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

    /**
     * 获取上传文件
     */
    public static function file(?string $key = null): mixed
    {
        return static::getInstance()->file($key);
    }

    /**
     * 获取上一次请求的闪存输入
     */
    public static function old(?string $key = null, mixed $default = null): mixed
    {
        return static::getInstance()->old($key, $default);
    }

    /**
     * 获取完整 URL
     */
    public static function url(): string
    {
        return static::getInstance()->url();
    }

    /**
     * 获取完整 URL 含 query string
     */
    public static function fullUrl(): string
    {
        return static::getInstance()->fullUrl();
    }

    /**
     * 检查路径是否匹配模式
     */
    public static function is(string ...$patterns): bool
    {
        return static::getInstance()->is(...$patterns);
    }
}
