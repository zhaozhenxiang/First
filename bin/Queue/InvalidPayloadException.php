<?php

declare(strict_types=1);

namespace Bin\Queue;

use RuntimeException;

class InvalidPayloadException extends RuntimeException
{
    public function __construct(
        private int $jobId,
        private string $queue,
        private string $payload
    ) {
        parent::__construct("Unable to hydrate queued job [{$jobId}] on queue [{$queue}].");
    }

    public function getJobId(): int
    {
        return $this->jobId;
    }

    public function getQueue(): string
    {
        return $this->queue;
    }

    public function getPayload(): string
    {
        return $this->payload;
    }
}
