<?php

declare(strict_types=1);

namespace Bin\Console\Commands;

use Bin\Console\Command;
use Bin\Queue\Drivers\DatabaseQueue;
use Bin\Queue\QueueManager;

class QueueRetryCommand extends Command
{
    protected string $signature = 'queue:retry {id} {connection?}';

    protected string $description = 'Retry a failed queue job';

    public function execute(): int
    {
        $connection = (string) ($this->argument('connection') ?: $this->defaultConnection());
        $queue = QueueManager::getInstance()->connection($connection);

        if (!$queue instanceof DatabaseQueue) {
            $this->error("Queue connection [{$connection}] does not support failed jobs.");
            return 1;
        }

        $id = (int) $this->argument('id');
        if (!$queue->retryFailedJob($id)) {
            $this->error("Failed job [{$id}] not found.");
            return 1;
        }

        $this->info("Failed job [{$id}] has been pushed back onto the queue.");

        return 0;
    }

    private function defaultConnection(): string
    {
        return function_exists('config') ? (string) config('queue.default', 'sync') : 'sync';
    }
}
