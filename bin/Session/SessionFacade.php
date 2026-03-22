<?php

declare(strict_types=1);

namespace Bin\Session;

use Bin\Facade\Facade;

/**
 * Session Facade
 */
class SessionFacade extends Facade
{
    /**
     * 获取服务的实例标识
     */
    protected static function getAccessor(): string
    {
        return 'session';
    }

    /**
     * 获取 session 值
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return static::getInstance()->get($key, $default);
    }

    /**
     * 设置 session 值
     */
    public static function set(string $key, mixed $value): void
    {
        static::getInstance()->set($key, $value);
    }

    /**
     * 检查 key 是否存在
     */
    public static function has(string $key): bool
    {
        return static::getInstance()->has($key);
    }

    /**
     * 删除 session 值
     */
    public static function remove(string $key): void
    {
        static::getInstance()->remove($key);
    }

    /**
     * 获取并删除 session 值
     */
    public static function pull(string $key, mixed $default = null): mixed
    {
        return static::getInstance()->pull($key, $default);
    }

    /**
     * 清空所有 session 数据
     */
    public static function clear(): void
    {
        static::getInstance()->clear();
    }

    /**
     * 设置 Flash 消息
     */
    public static function flash(string $key, mixed $value): void
    {
        static::getInstance()->flash($key, $value);
    }

    /**
     * 获取 Flash 消息
     */
    public static function getFlash(string $key, mixed $default = null): mixed
    {
        return static::getInstance()->getFlash($key, $default);
    }

    /**
     * 获取并删除 Flash 消息
     */
    public static function pullFlash(string $key, mixed $default = null): mixed
    {
        return static::getInstance()->pullFlash($key, $default);
    }

    /**
     * 检查 Flash 消息是否存在
     */
    public static function hasFlash(string $key): bool
    {
        return static::getInstance()->hasFlash($key);
    }

    /**
     * 获取所有 Flash 消息
     */
    public static function getAllFlash(): array
    {
        return static::getInstance()->getAllFlash();
    }

    /**
     * 重新 Flash 数据
     */
    public static function reflash(array|string $keys = []): void
    {
        static::getInstance()->reflash($keys);
    }

    /**
     * 清除 Flash 消息
     */
    public static function clearFlash(): void
    {
        static::getInstance()->clearFlash();
    }

    /**
     * 获取 Session ID
     */
    public static function getId(): string
    {
        return static::getInstance()->getId();
    }

    /**
     * 启动 Session
     */
    public static function start(): bool
    {
        return static::getInstance()->start();
    }

    /**
     * 销毁 Session
     */
    public static function destroy(): void
    {
        static::getInstance()->destroy();
    }

    /**
     * 获取 CSRF Token
     */
    public static function csrfToken(): string
    {
        $token = static::getInstance()->getCsrfToken();

        if ($token === null) {
            $token = static::getInstance()->putCsrfToken();
        }

        return $token;
    }

    /**
     * 验证 CSRF Token
     */
    public static function verifyCsrfToken(string $token): bool
    {
        return static::getInstance()->verifyCsrfToken($token);
    }

    /**
     * 获取上一个请求的输入值
     */
    public static function getOldInput(string $key = null, mixed $default = null): mixed
    {
        return static::getInstance()->getOldInput($key, $default);
    }

    /**
     * 保存当前输入值
     */
    public static function flashInput(array $input): void
    {
        static::getInstance()->flashInput($input);
    }
}
