<?php

declare(strict_types=1);

namespace Bin\Queue\Contracts;

use Bin\Queue\Job;

/**
 * 队列驱动接口
 */
interface QueueInterface
{
    /**
     * 入队任务
     */
    public function push(mixed $job, string $queue = 'default'): mixed;

    /**
     * 原始数据入队
     */
    public function pushRaw(string $payload, string $queue = 'default'): mixed;

    /**
     * 延迟入队
     */
    public function later(int $delay, mixed $job, string $queue = 'default'): mixed;

    /**
     * 出队任务
     */
    public function pop(string $queue = 'default'): ?Job;

    /**
     * 确认完成
     */
    public function delete(mixed $job): bool;

    /**
     * 重新入队
     */
    public function release(mixed $job, int $delay = 0): bool;

    /**
     * 队列长度
     */
    public function size(string $queue = 'default'): int;
}
