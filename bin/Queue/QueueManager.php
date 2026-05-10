<?php

declare(strict_types=1);

namespace Bin\Queue;

use Bin\Queue\Contracts\QueueInterface;
use Bin\Queue\Drivers\DatabaseQueue;
use Bin\Queue\Drivers\SyncQueue;
use RuntimeException;

/**
 * 队列管理器
 *
 * 管理多个队列连接（connection），通过配置文件定义驱动。
 *
 * 用法：
 *   QueueManager::getInstance()->push(new SendEmailJob('user@example.com'));
 *   QueueManager::getInstance()->later(60, new ProcessJob());
 */
class QueueManager
{
    /** @var array<string, QueueInterface> 已解析的连接实例 */
    protected array $connections = [];

    /** @var string 默认连接名 */
    protected string $defaultConnection = 'sync';

    /** @var array 连接配置 */
    protected array $config = [];

    /** @var self|null 单例 */
    private static ?self $instance = null;

    public function __construct()
    {
        if ($this->config === [] && function_exists('config')) {
            $this->config = config('queue.connections') ?? [];
            $this->defaultConnection = config('queue.default') ?? 'sync';
        }
    }

    /**
     * 获取单例
     */
    public static function getInstance(): static
    {
        return self::$instance ??= new static();
    }

    /**
     * 重置单例（测试用）
     */
    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    /**
     * 设置配置
     */
    public function setConfig(array $config): static
    {
        $this->config = $config;
        return $this;
    }

    /**
     * 设置默认连接
     */
    public function setDefaultConnection(string $name): static
    {
        $this->defaultConnection = $name;
        return $this;
    }

    public function setConnection(string $name, QueueInterface $connection): static
    {
        $this->connections[$name] = $connection;

        return $this;
    }

    /**
     * 获取连接实例
     */
    public function connection(?string $name = null): QueueInterface
    {
        $name = $name ?? $this->defaultConnection;

        if (!isset($this->connections[$name])) {
            $this->connections[$name] = $this->resolveConnection($name);
        }

        return $this->connections[$name];
    }

    /**
     * 解析连接配置为驱动实例
     */
    protected function resolveConnection(string $name): QueueInterface
    {
        $config = $this->config[$name] ?? [];

        if ($config === []) {
            throw new RuntimeException("Queue connection [{$name}] is not configured.");
        }

        $driver = $config['driver'] ?? 'sync';

        return match ($driver) {
            'sync' => $this->createSyncDriver($config),
            'database' => $this->createDatabaseDriver($config),
            default => throw new RuntimeException("Unsupported queue driver: {$driver}"),
        };
    }

    /**
     * 创建 sync 驱动
     */
    protected function createSyncDriver(array $config): SyncQueue
    {
        return new SyncQueue();
    }

    /**
     * 创建 database 驱动
     */
    protected function createDatabaseDriver(array $config): DatabaseQueue
    {
        $connectionName = $config['connection'] ?? 'default';
        $queue = new DatabaseQueue($connectionName, null, (int) ($config['retry_after'] ?? 90));

        if (isset($config['table'])) {
            $queue->setTable($config['table']);
        }
        if (isset($config['failed_table'])) {
            $queue->setFailedTable($config['failed_table']);
        }

        return $queue;
    }

    /**
     * 清除已解析的连接
     */
    public function flush(): void
    {
        $this->connections = [];
    }

    // ─── 代理方法（直接操作默认连接）───

    public function push(mixed $job, string $queue = 'default'): mixed
    {
        return $this->connection()->push($job, $queue);
    }

    public function pushRaw(string $payload, string $queue = 'default'): mixed
    {
        return $this->connection()->pushRaw($payload, $queue);
    }

    public function later(int $delay, mixed $job, string $queue = 'default'): mixed
    {
        return $this->connection()->later($delay, $job, $queue);
    }

    public function pop(string $queue = 'default'): ?Job
    {
        return $this->connection()->pop($queue);
    }

    public function delete(mixed $job): bool
    {
        return $this->connection()->delete($job);
    }

    public function release(mixed $job, int $delay = 0): bool
    {
        return $this->connection()->release($job, $delay);
    }

    public function size(string $queue = 'default'): int
    {
        return $this->connection()->size($queue);
    }
}
