<?php

declare(strict_types=1);

namespace Bin\Console\Commands;

use Bin\Console\Command;
use Bin\Queue\Drivers\DatabaseQueue;
use Bin\Queue\QueueManager;
use Throwable;

class QueueFlushCommand extends Command
{
    protected string $signature = 'queue:flush {connection?} {--force}';

    protected string $description = 'Flush all failed queue jobs';

    public function execute(): int
    {
        if (!$this->hasOption('force')) {
            $this->error('Use --force to flush failed jobs.');
            return 1;
        }

        $connection = (string) ($this->argument('connection') ?: $this->defaultConnection());
        $queue = $this->databaseQueue($connection);
        if (!$queue instanceof DatabaseQueue) {
            return 1;
        }

        $count = $queue->flushFailedJobs();
        $this->info("Flushed {$count} failed jobs.");

        return 0;
    }

    private function databaseQueue(string $connection): ?DatabaseQueue
    {
        try {
            $queue = QueueManager::getInstance()->connection($connection);
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            return null;
        }

        if (!$queue instanceof DatabaseQueue) {
            $this->error("Queue connection [{$connection}] does not support failed jobs.");
            return null;
        }

        return $queue;
    }

    private function defaultConnection(): string
    {
        return function_exists('config') ? (string) (config('queue.default') ?? 'sync') : 'sync';
    }
}
