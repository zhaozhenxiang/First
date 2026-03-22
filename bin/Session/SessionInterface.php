<?php

declare(strict_types=1);

namespace Bin\Session;

/**
 * Session 会话接口
 */
interface SessionInterface
{
    /**
     * 获取 session 值
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * 设置 session 值
     */
    public function set(string $key, mixed $value): void;

    /**
     * 检查 key 是否存在
     */
    public function has(string $key): bool;

    /**
     * 删除 session 值
     */
    public function remove(string $key): void;

    /**
     * 获取并删除 session 值
     */
    public function pull(string $key, mixed $default = null): mixed;

    /**
     * 清空所有 session 数据
     */
    public function clear(): void;

    /**
     * 获取所有 session 数据
     */
    public function all(): array;

    /**
     * 设置 Flash 消息（仅在下次请求可用）
     */
    public function flash(string $key, mixed $value): void;

    /**
     * 获取 Flash 消息
     */
    public function getFlash(string $key, mixed $default = null): mixed;

    /**
     * 获取并删除 Flash 消息
     */
    public function pullFlash(string $key, mixed $default = null): mixed;

    /**
     * 检查 Flash 消息是否存在
     */
    public function hasFlash(string $key): bool;

    /**
     * 获取所有 Flash 消息
     */
    public function getAllFlash(): array;

    /**
     * 重新 Flash 数据（保留到下次请求）
     */
    public function reflash(array|string $keys = []): void;

    /**
     * 清除 Flash 消息
     */
    public function clearFlash(): void;

    /**
     * 获取 Session ID
     */
    public function getId(): string;

    /**
     * 设置 Session ID
     */
    public function setId(string $id): void;

    /**
     * 获取 Session 名称
     */
    public function getName(): string;

    /**
     * 启动 Session
     */
    public function start(): bool;

    /**
     * 保存 Session 数据
     */
    public function save(): void;

    /**
     * 销毁 Session
     */
    public function destroy(): void;

    /**
     * 检查 Session 是否已启动
     */
    public function isStarted(): bool;

    /**
     * 设置过期时间（秒）
     */
    public function setLifetime(int $minutes): void;

    /**
     * 获取过期时间（秒）
     */
    public function getLifetime(): int;

    /**
     * 设置闪存数据键名
     */
    public function setFlashKey(string $key): void;

    /**
     * 获取闪存数据键名
     */
    public function getFlashKey(): string;

    /**
     * 注册自定义存储处理器
     */
    public function setHandler(\SessionHandlerInterface $handler): void;
}
