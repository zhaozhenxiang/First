<?php

declare(strict_types=1);

namespace Bin\Console\Commands;

use Bin\Console\Command;
use Bin\Queue\Drivers\DatabaseQueue;
use Bin\Queue\QueueManager;
use Throwable;

class QueueForgetCommand extends Command
{
    protected string $signature = 'queue:forget {id} {connection?}';

    protected string $description = 'Delete a failed queue job';

    public function execute(): int
    {
        $connection = (string) ($this->argument('connection') ?: $this->defaultConnection());
        $queue = $this->databaseQueue($connection);
        if (!$queue instanceof DatabaseQueue) {
            return 1;
        }

        $id = (int) $this->argument('id');
        if (!$queue->forgetFailedJob($id)) {
            $this->error("Failed job [{$id}] not found.");
            return 1;
        }

        $this->info("Failed job [{$id}] has been deleted.");

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
        return function_exists('config') ? (string) config('queue.default', 'sync') : 'sync';
    }
}
