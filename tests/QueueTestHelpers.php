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

class QueueTest_FinallyFailingJob extends Job
{
    public static int $handleCount = 0;
    public static int $failedCount = 0;
    public int $retryAfter = 0;

    public function handle(): void
    {
        self::$handleCount++;
        throw new \RuntimeException('Final failure');
    }

    public function failed(\Throwable $e): void
    {
        self::$failedCount++;
    }

    public static function resetState(): void
    {
        self::$handleCount = 0;
        self::$failedCount = 0;
    }
}

class QueueTest_InjectedDependency
{
    public function __construct(public string $value = 'injected')
    {
    }
}

class QueueTest_InjectedJob extends Job
{
    public static string $value = '';

    public function handle(?QueueTest_InjectedDependency $dependency = null): void
    {
        if ($dependency === null) {
            throw new \RuntimeException('Dependency was not injected');
        }

        self::$value = $dependency->value;
    }

    public static function resetState(): void
    {
        self::$value = '';
    }
}
