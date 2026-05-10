<?php

declare(strict_types=1);

namespace Bin\Console\Commands;

use Bin\Console\Command;
use Bin\Queue\Drivers\DatabaseQueue;
use Bin\Queue\QueueManager;
use DateTimeImmutable;
use Throwable;

class QueueFailedCommand extends Command
{
    protected string $signature = 'queue:failed {connection?}';

    protected string $description = 'List failed queue jobs';

    public function execute(): int
    {
        $queue = $this->databaseQueue();
        if (!$queue instanceof DatabaseQueue) {
            return 1;
        }

        $rows = [];
        foreach ($queue->getFailedJobs() as $job) {
            $rows[] = [
                $job['id'] ?? '',
                $job['connection'] ?? '',
                $job['queue'] ?? '',
                $this->displayName((string) ($job['payload'] ?? '')),
                $this->formatFailedAt((int) ($job['failed_at'] ?? 0)),
            ];
        }

        if ($rows === []) {
            $this->info('No failed jobs.');
            return 0;
        }

        $this->table(['ID', 'Connection', 'Queue', 'Job', 'Failed At'], $rows);

        return 0;
    }

    private function databaseQueue(): ?DatabaseQueue
    {
        $connection = (string) ($this->argument('connection') ?: $this->defaultConnection());
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

    private function displayName(string $payload): string
    {
        $data = json_decode($payload, true);
        if (is_array($data) && isset($data['displayName'])) {
            $displayName = trim((string) $data['displayName']);
            if ($displayName !== '') {
                return $displayName;
            }
        }

        return 'raw payload';
    }

    private function formatFailedAt(int $timestamp): string
    {
        if ($timestamp <= 0) {
            return '';
        }

        return (new DateTimeImmutable('@' . $timestamp))->format('Y-m-d H:i:s');
    }

    private function defaultConnection(): string
    {
        return function_exists('config') ? (string) (config('queue.default') ?? 'sync') : 'sync';
    }
}
