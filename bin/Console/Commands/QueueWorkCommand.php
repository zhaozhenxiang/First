<?php

declare(strict_types=1);

namespace Bin\Console\Commands;

use Bin\Console\Command;
use Bin\Queue\QueueManager;
use Bin\Queue\Worker;
use Throwable;

class QueueWorkCommand extends Command
{
    protected string $signature = 'queue:work {connection?} {--queue=default} {--tries=3} {--sleep=1} {--timeout=60} {--once}';

    protected string $description = 'Start processing jobs on the queue';

    public function execute(): int
    {
        $connection = (string) ($this->argument('connection') ?: $this->defaultConnection());
        $queues = array_map('trim', explode(',', (string) $this->option('queue', 'default')));
        $tries = (int) $this->option('tries', 3);
        $sleep = (int) $this->option('sleep', 1);
        $timeout = (int) $this->option('timeout', 60);
        $once = $this->hasOption('once');

        $worker = new Worker(QueueManager::getInstance());

        try {
            $exitCode = $worker->run($connection, $queues, $tries, $sleep, $once, $timeout);
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            return 1;
        }

        $this->info(sprintf(
            'Processed %d jobs; failed %d jobs.',
            $worker->getProcessed(),
            $worker->getFailed()
        ));

        return $exitCode;
    }

    private function defaultConnection(): string
    {
        return function_exists('config') ? (string) (config('queue.default') ?? 'sync') : 'sync';
    }
}
