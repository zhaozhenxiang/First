<?php

declare(strict_types=1);

namespace Bin\Queue;

/**
 * 待分发的任务
 *
 * 析构时触发实际的队列分发。
 */
class PendingDispatch
{
    protected Job $job;
    protected ?string $connection = null;
    protected bool $dispatched = false;

    public function __construct(Job $job)
    {
        $this->job = $job;
    }

    /**
     * 设置目标队列
     */
    public function onQueue(string $queue): static
    {
        $this->job->onQueue($queue);
        return $this;
    }

    /**
     * 设置目标连接
     */
    public function onConnection(string $connection): static
    {
        $this->connection = $connection;
        return $this;
    }

    /**
     * 设置延迟
     */
    public function delay(int $seconds): static
    {
        $this->job->delay = $seconds;
        return $this;
    }

    /**
     * 析构时执行分发
     */
    public function __destruct()
    {
        $this->dispatch();
    }

    /**
     * 执行分发
     */
    public function dispatch(): static
    {
        if ($this->dispatched) {
            return $this;
        }

        $this->dispatched = true;

        $connection = QueueManager::getInstance()->connection($this->connection);

        if ($this->job->delay > 0) {
            $connection->later($this->job->delay, $this->job, $this->job->getQueue());
        } else {
            $connection->push($this->job, $this->job->getQueue());
        }

        return $this;
    }
}
