<?php

declare(strict_types=1);

namespace Bin\Queue;

use Bin\Queue\Drivers\DatabaseQueue;
use RuntimeException;

/**
 * 队列 Worker
 *
 * 从队列中取出任务并执行。
 *
 * 用法：
 *   $worker = new Worker($manager);
 *   $worker->daemon('default', 'default');
 */
class Worker
{
    protected bool $shouldQuit = false;
    protected int $processed = 0;
    protected int $failed = 0;

    public function __construct(
        protected QueueManager $manager
    ) {
        // 注册信号处理
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, [$this, 'handleSignal']);
            pcntl_signal(SIGINT, [$this, 'handleSignal']);
        }
    }

    /**
     * 启动 daemon 模式
     */
    public function daemon(string $connection, string $queue = 'default', int $tries = 3, int $sleep = 1): void
    {
        while (!$this->shouldQuit) {
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }

            $job = $this->manager->connection($connection)->pop($queue);

            if ($job === null) {
                sleep($sleep);
                continue;
            }

            $this->process($job, $connection, $queue, $tries);
            $this->processed++;
        }
    }

    /**
     * 处理单个任务
     */
    public function process(Job $job, string $connection, string $queue, int $tries = 3): void
    {
        try {
            $job->handle();
            $this->manager->connection($connection)->delete($job);
        } catch (\Throwable $e) {
            $this->handleFailure($job, $connection, $queue, $e, $tries);
        }
    }

    /**
     * 处理失败任务
     */
    protected function handleFailure(Job $job, string $connection, string $queue, \Throwable $exception, int $maxTries): void
    {
        $job->failed($exception);

        if ($job->getAttempts() >= $maxTries || $job->hasExceededMaxTries()) {
            // 超过最大重试，记录失败
            $this->logFailedJob($connection, $queue, $job, $exception);
            $this->manager->connection($connection)->delete($job);
            $this->failed++;
        } else {
            // 重新入队
            $this->manager->connection($connection)->release($job, $job->retryAfter);
        }
    }

    /**
     * 记录失败任务
     */
    protected function logFailedJob(string $connection, string $queue, Job $job, \Throwable $exception): void
    {
        $queueDriver = $this->manager->connection($connection);

        if ($queueDriver instanceof DatabaseQueue) {
            $queueDriver->logFailedJob($connection, $queue, $job, $exception);
        }
    }

    /**
     * 信号处理
     */
    public function handleSignal(int $signal): void
    {
        $this->shouldQuit = true;
    }

    /**
     * 停止 worker
     */
    public function stop(): void
    {
        $this->shouldQuit = true;
    }

    /**
     * 获取已处理数
     */
    public function getProcessed(): int
    {
        return $this->processed;
    }

    /**
     * 获取失败数
     */
    public function getFailed(): int
    {
        return $this->failed;
    }
}
