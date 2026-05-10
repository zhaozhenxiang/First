<?php

declare(strict_types=1);

namespace Bin\Queue\Drivers;

use Bin\App\App;
use Bin\Queue\Contracts\QueueInterface;
use Bin\Queue\Job;

/**
 * 同步队列驱动
 *
 * 立即执行任务，不入队。适用于开发/测试环境。
 */
class SyncQueue implements QueueInterface
{
    public function push(mixed $job, string $queue = 'default'): mixed
    {
        if ($job instanceof Job) {
            $job->setAttempts($job->getAttempts() + 1);
            App::getInstance()->getContainer()->call([$job, 'handle']);
        }

        return true;
    }

    public function pushRaw(string $payload, string $queue = 'default'): mixed
    {
        $job = $this->unserializeJob($payload);
        if ($job !== null) {
            return $this->push($job, $queue);
        }
        return true;
    }

    public function later(int $delay, mixed $job, string $queue = 'default'): mixed
    {
        // sync 模式忽略延迟
        return $this->push($job, $queue);
    }

    public function pop(string $queue = 'default'): ?Job
    {
        // sync 没有存储的任务
        return null;
    }

    public function delete(mixed $job): bool
    {
        return true;
    }

    public function release(mixed $job, int $delay = 0): bool
    {
        return true;
    }

    public function size(string $queue = 'default'): int
    {
        return 0;
    }

    /**
     * 反序列化 Job
     */
    protected function unserializeJob(string $payload): ?Job
    {
        $data = json_decode($payload, true);
        if ($data === null || !isset($data['job'])) {
            return null;
        }

        $job = @unserialize($data['job'], ['allowed_classes' => true]);
        return $job instanceof Job ? $job : null;
    }
}
