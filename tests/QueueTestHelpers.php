<?php

declare(strict_types=1);

use Bin\Queue\Dispatchable;
use Bin\Queue\Job;

class QueueTest_TestJob extends Job
{
    use Dispatchable;

    public static string $lastResult = '';
    public static int $handleCount = 0;

    public function __construct(public string $result = 'handled')
    {
    }

    public function handle(): void
    {
        self::$lastResult = $this->result;
        self::$handleCount++;
    }

    public static function resetState(): void
    {
        self::$lastResult = '';
        self::$handleCount = 0;
    }
}

class QueueTest_FailingJob extends Job
{
    public static int $failCount = 0;

    public function handle(): void
    {
        throw new \RuntimeException('Job failed');
    }

    public function failed(\Throwable $e): void
    {
        self::$failCount++;
    }

    public static function resetState(): void
    {
        self::$failCount = 0;
    }
}

class QueueTest_DispatchableJob extends Job
{
    use Dispatchable;

    public static bool $dispatched = false;

    public function handle(): void
    {
        self::$dispatched = true;
    }

    public static function resetState(): void
    {
        self::$dispatched = false;
    }
}
