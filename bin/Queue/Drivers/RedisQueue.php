<?php

declare(strict_types=1);

namespace Bin\Queue\Drivers;

use Bin\Queue\Contracts\QueueInterface;
use Bin\Queue\InvalidPayloadException;
use Bin\Queue\Job;
use InvalidArgumentException;
use RuntimeException;

/**
 * Redis 队列驱动
 *
 * 存储模型：
 * - 就绪任务：LIST {prefix}{queue}（rpush 入队 / lpop 出队，FIFO）
 * - 延迟任务：ZSET {prefix}delayed:{queue}（score = 可用时间戳，pop 时迁移到期任务）
 *
 * 连接注入：构造函数接受已建立的连接对象（测试可用内存 fake）；
 * 未注入时要求 ext-redis 并按配置建立连接（与 Cache\RedisStore 同一策略）。
 */
class RedisQueue implements QueueInterface
{
    /**
     * @param object|null $redis 已建立的 Redis 连接（phpredis 或测试 fake）
     * @param array{host?: string, port?: int, password?: ?string, database?: int} $config
     */
    public function __construct(
        protected ?object $redis = null,
        protected string $prefix = 'queues:',
        protected array $config = []
    ) {
        if ($this->redis === null) {
            if (!class_exists(\Redis::class)) {
                throw new RuntimeException('Redis queue driver requires the redis extension.');
            }

            $redis = new \Redis();
            $redis->connect(
                $this->config['host'] ?? '127.0.0.1',
                (int) ($this->config['port'] ?? 6379)
            );

            $password = $this->config['password'] ?? null;
            if ($password !== null && $password !== '') {
                $redis->auth($password);
            }

            $database = (int) ($this->config['database'] ?? 0);
            if ($database !== 0) {
                $redis->select($database);
            }

            $this->redis = $redis;
        }
    }

    public function push(mixed $job, string $queue = 'default'): mixed
    {
        $payload = $this->createPayload($job);
        $this->redis->rpush($this->readyKey($queue), $payload);

        return $payload;
    }

    public function pushRaw(string $payload, string $queue = 'default'): mixed
    {
        $this->redis->rpush($this->readyKey($queue), $payload);

        return $payload;
    }

    public function later(int $delay, mixed $job, string $queue = 'default'): mixed
    {
        $payload = $this->createPayload($job);

        $this->redis->zadd(
            $this->delayedKey($queue),
            max(0, time() + $delay),
            $payload
        );

        return $payload;
    }

    public function pop(string $queue = 'default'): ?Job
    {
        $this->migrateDueDelayed($queue);

        $payload = $this->redis->lpop($this->readyKey($queue));

        if ($payload === null || $payload === false) {
            return null;
        }

        return $this->hydrateJob($payload, $queue);
    }

    public function delete(mixed $job): bool
    {
        // 任务在 pop 时已从 Redis 移除
        return true;
    }

    public function release(mixed $job, int $delay = 0): bool
    {
        if (!$job instanceof Job) {
            throw new InvalidArgumentException('Redis queue can only release ' . Job::class . ' instances.');
        }

        $payload = $this->refreshPayload($job);

        if ($delay > 0) {
            $this->redis->zadd($this->delayedKey($job->getQueue()), time() + $delay, $payload);

            return true;
        }

        $this->redis->rpush($this->readyKey($job->getQueue()), $payload);

        return true;
    }

    public function size(string $queue = 'default'): int
    {
        return (int) $this->redis->llen($this->readyKey($queue))
            + (int) $this->redis->zcard($this->delayedKey($queue));
    }

    // ================================================================
    // 内部
    // ================================================================

    protected function readyKey(string $queue): string
    {
        return $this->prefix . $queue;
    }

    protected function delayedKey(string $queue): string
    {
        return $this->prefix . 'delayed:' . $queue;
    }

    protected function createPayload(mixed $job): string
    {
        if (!$job instanceof Job) {
            throw new InvalidArgumentException('Redis queue payloads must be instances of ' . Job::class . '.');
        }

        $data = $job->toArray();
        $data['id'] = $job->getJobId() !== '' ? $job->getJobId() : uniqid('redis_', true);

        return (json_encode($data) ?: '{}');
    }

    /**
     * 重新序列化已还原的任务（保留尝试次数与任务标识）
     */
    protected function refreshPayload(Job $job): string
    {
        $data = $job->toArray();
        $data['id'] = $job->getJobId() !== '' ? $job->getJobId() : uniqid('redis_', true);

        return (json_encode($data) ?: '{}');
    }

    /**
     * 将到期的延迟任务迁移到就绪列表
     */
    protected function migrateDueDelayed(string $queue): void
    {
        $delayedKey = $this->delayedKey($queue);

        $due = $this->redis->zrangebyscore($delayedKey, '-inf', (string) time());

        foreach ((array) $due as $payload) {
            if ((int) $this->redis->zrem($delayedKey, $payload) === 1) {
                $this->redis->rpush($this->readyKey($queue), $payload);
            }
        }
    }

    /**
     * 从 payload 还原 Job（尝试次数 +1）
     */
    protected function hydrateJob(string $payload, string $queue): Job
    {
        $data = json_decode($payload, true);

        if (!is_array($data) || !isset($data['job'])) {
            throw new InvalidPayloadException(0, $queue, $payload);
        }

        $job = @unserialize($data['job'], ['allowed_classes' => true]);

        if (!$job instanceof Job) {
            throw new InvalidPayloadException(0, $queue, $payload);
        }

        $job->setJobId((string) ($data['id'] ?? ''));
        $job->setAttempts((int) ($data['attempts'] ?? 0) + 1);

        return $job;
    }
}
