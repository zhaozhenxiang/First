<?php

declare(strict_types=1);

namespace Bin\Queue;

/**
 * Job 基类
 *
 * 用户继承此类，实现 handle() 方法定义任务逻辑。
 */
abstract class Job
{
    /** @var int 最大重试次数 */
    public int $maxTries = 3;

    /** @var int 超时秒数 */
    public int $timeout = 60;

    /** @var int 重试间隔秒数 */
    public int $retryAfter = 90;

    /** @var string 目标队列名 */
    public string $queue = 'default';

    /** @var int 延迟秒数 */
    public int $delay = 0;

    /** @var int 当前尝试次数 */
    protected int $attempts = 0;

    /** @var string 任务标识 */
    protected string $jobId = '';

    /**
     * 执行任务
     */
    abstract public function handle(): void;

    /**
     * 任务显示名称
     */
    public function displayName(): string
    {
        return static::class;
    }

    /**
     * 获取当前尝试次数
     */
    public function getAttempts(): int
    {
        return $this->attempts;
    }

    /**
     * 设置尝试次数
     */
    public function setAttempts(int $attempts): static
    {
        $this->attempts = $attempts;
        return $this;
    }

    /**
     * 获取任务 ID
     */
    public function getJobId(): string
    {
        return $this->jobId;
    }

    /**
     * 设置任务 ID
     */
    public function setJobId(string $id): static
    {
        $this->jobId = $id;
        return $this;
    }

    /**
     * 获取目标队列名
     */
    public function getQueue(): string
    {
        return $this->queue;
    }

    /**
     * 设置目标队列
     */
    public function onQueue(string $queue): static
    {
        $this->queue = $queue;
        return $this;
    }

    /**
     * 任务失败回调
     */
    public function failed(\Throwable $e): void
    {
        // 子类可重写
    }

    /**
     * 判断是否已超最大重试
     */
    public function hasExceededMaxTries(): bool
    {
        return $this->attempts >= $this->maxTries;
    }

    /**
     * 序列化为数组
     */
    public function toArray(): array
    {
        return [
            'displayName' => $this->displayName(),
            'job' => serialize($this),
            'maxTries' => $this->maxTries,
            'timeout' => $this->timeout,
            'retryAfter' => $this->retryAfter,
            'queue' => $this->queue,
            'delay' => $this->delay,
            'attempts' => $this->attempts,
        ];
    }

    /**
     * 编码为 JSON payload
     */
    public function toJson(): string
    {
        return json_encode($this->toArray()) ?: '{}';
    }
}
