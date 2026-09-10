<?php

declare(strict_types=1);

namespace Bin\Queue;

use Bin\App\App;
use Bin\Queue\Drivers\DatabaseQueue;
use Bin\Queue\InvalidPayloadException;
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
    /** @var int 默认任务超时秒数 */
    public const int DEFAULT_TIMEOUT = 60;

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

            // 异步信号投递：SIGALRM 可中断阻塞中的任务
            if (function_exists('pcntl_async_signals')) {
                pcntl_async_signals(true);
            }
        }
    }

    /**
     * 运行 Worker 循环
     */
    public function run(
        string $connection,
        array $queues = ['default'],
        int $tries = 3,
        int $sleep = 1,
        bool $once = false,
        int $timeout = self::DEFAULT_TIMEOUT
    ): int {
        while (!$this->shouldQuit) {
            $processed = $this->runNextJob($connection, $queues, $tries, $timeout);

            if ($once) {
                return $processed ? 0 : 1;
            }

            if (!$processed) {
                sleep($sleep);
            }

            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }
        }

        return 0;
    }

    /**
     * 按优先级队列处理下一个任务
     */
    public function runNextJob(
        string $connection,
        array $queues = ['default'],
        int $tries = 3,
        int $timeout = self::DEFAULT_TIMEOUT
    ): bool {
        foreach ($queues as $queue) {
            $queueName = trim((string) $queue);
            if ($queueName === '') {
                continue;
            }

            try {
                $job = $this->manager->connection($connection)->pop($queueName);
            } catch (InvalidPayloadException) {
                $this->failed++;
                return true;
            }

            if ($job === null) {
                continue;
            }

            $this->process($job, $connection, $queueName, $tries, $timeout);
            $this->processed++;

            return true;
        }

        return false;
    }

    /**
     * 启动 daemon 模式
     */
    public function daemon(
        string $connection,
        string $queue = 'default',
        int $tries = 3,
        int $sleep = 1,
        int $timeout = self::DEFAULT_TIMEOUT
    ): void {
        $queues = array_map('trim', explode(',', $queue));
        $this->run($connection, $queues, $tries, $sleep, false, $timeout);
    }

    /**
     * 处理单个任务
     */
    public function process(Job $job, string $connection, string $queue, int $tries = 3, int $timeout = self::DEFAULT_TIMEOUT): void
    {
        $container = App::getInstance()->getContainer();
        $container->resetScope();

        $alarmSet = $this->startTimeoutAlarm($job, $timeout);

        try {
            $container->call([$job, 'handle']);
            $this->manager->connection($connection)->delete($job);
        } catch (\Throwable $e) {
            $this->handleFailure($job, $connection, $queue, $e, $tries);
        } finally {
            if ($alarmSet) {
                pcntl_alarm(0);
            }
            $container->resetScope();
        }
    }

    /**
     * 为任务注册超时闹钟（pcntl 不可用时超时不强制）
     *
     * @return bool 是否已设置闹钟
     */
    protected function startTimeoutAlarm(Job $job, int $workerTimeout): bool
    {
        if (!function_exists('pcntl_alarm') || !function_exists('pcntl_signal')) {
            return false;
        }

        $effective = $this->effectiveTimeout($job, $workerTimeout);

        if ($effective <= 0) {
            return false;
        }

        pcntl_signal(SIGALRM, static function (): never {
            throw new QueueTimeoutException('Queue job timed out.');
        });

        pcntl_alarm($effective);

        return true;
    }

    /**
     * 计算生效超时：worker 与任务取较小值，任务 timeout <= 0 时沿用 worker 值
     */
    protected function effectiveTimeout(Job $job, int $workerTimeout): int
    {
        $workerTimeout = max(0, $workerTimeout);

        if ($job->timeout <= 0) {
            return $workerTimeout;
        }

        return $workerTimeout > 0 ? min($workerTimeout, $job->timeout) : $job->timeout;
    }

    /**
     * 处理失败任务
     */
    protected function handleFailure(Job $job, string $connection, string $queue, \Throwable $exception, int $maxTries): void
    {
        $effectiveMaxTries = $maxTries > 0 ? min($maxTries, $job->maxTries) : $job->maxTries;

        if ($job->getAttempts() >= $effectiveMaxTries || $job->hasExceededMaxTries()) {
            $queueDriver = $this->manager->connection($connection);
            if ($queueDriver instanceof DatabaseQueue) {
                if (!$queueDriver->failJob($connection, $queue, $job, $exception)) {
                    return;
                }
            } else {
                $queueDriver->delete($job);
            }

            try {
                $job->failed($exception);
            } catch (\Throwable) {
                // User failure callbacks must not undo durable failure handling.
            }

            $this->failed++;

            return;
        }

        $this->manager->connection($connection)->release($job, $this->calculateBackoff($job));
    }

    /**
     * 计算释放延迟
     *
     * backoff 为 0 时沿用 retryAfter；正整数固定间隔；
     * 数组按尝试次数取值（第 1 次失败取 backoff[0]），超出取末值
     */
    protected function calculateBackoff(Job $job): int
    {
        $backoff = $job->backoff;

        if (is_int($backoff)) {
            return $backoff > 0 ? $backoff : $job->retryAfter;
        }

        if ($backoff === []) {
            return $job->retryAfter;
        }

        $index = max(0, $job->getAttempts() - 1);

        return $backoff[min($index, count($backoff) - 1)];
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
