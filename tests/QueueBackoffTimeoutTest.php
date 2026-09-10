<?php

declare(strict_types=1);

namespace Tests;

use Bin\Queue\Drivers\DatabaseQueue;
use Bin\Queue\Job;
use Bin\Queue\QueueManager;
use Bin\Queue\QueueTimeoutException;
use Bin\Queue\Worker;
use Bin\Testing\TestCase;
use PDO;

/**
 * Track E Worker 硬化测试 — backoff 释放延迟与超时强制
 */
class QueueBackoffTimeoutTest extends TestCase
{
    protected ?PDO $pdo = null;

    protected function setUp(): void
    {
        parent::setUp();
        QueueManager::resetInstance();
        QueueBackoff_SleeperJob::resetState();

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec("
            CREATE TABLE jobs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                queue VARCHAR(255) NOT NULL,
                payload TEXT NOT NULL,
                attempts INTEGER DEFAULT 0,
                reserved_at INTEGER DEFAULT NULL,
                available_at INTEGER NOT NULL,
                created_at INTEGER NOT NULL
            )
        ");
        $this->pdo->exec("
            CREATE TABLE failed_jobs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                connection VARCHAR(255) NOT NULL,
                queue VARCHAR(255) NOT NULL,
                payload TEXT NOT NULL,
                exception TEXT NOT NULL,
                failed_at INTEGER NOT NULL
            )
        ");
    }

    protected function tearDown(): void
    {
        QueueManager::resetInstance();

        // 还原 SIGALRM 处理器，避免影响后续测试
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGALRM, SIG_DFL);
        }

        parent::tearDown();
    }

    private function makeManager(): array
    {
        $queue = new DatabaseQueue('default', $this->pdo);
        $manager = QueueManager::getInstance();
        $manager->setDefaultConnection('database');
        $manager->setConnection('database', $queue);

        return [$manager, $queue];
    }

    // ================================================================
    // calculateBackoff 数学
    // ================================================================

    public function testCalculateBackoffFallsBackToRetryWhenZero(): void
    {
        [$manager] = $this->makeManager();
        $worker = new Worker($manager);

        $job = new QueueBackoff_FailingJob();
        $job->retryAfter = 42;
        $job->backoff = 0;
        $job->setAttempts(1);

        $this->assertSame(42, $this->invokeCalculateBackoff($worker, $job));
    }

    public function testCalculateBackoffFixedInteger(): void
    {
        [$manager] = $this->makeManager();
        $worker = new Worker($manager);

        $job = new QueueBackoff_FailingJob();
        $job->backoff = 17;
        $job->setAttempts(1);

        $this->assertSame(17, $this->invokeCalculateBackoff($worker, $job));
    }

    public function testCalculateBackoffArrayIndexedByAttempt(): void
    {
        [$manager] = $this->makeManager();
        $worker = new Worker($manager);

        $job = new QueueBackoff_FailingJob();
        $job->backoff = [5, 15, 45];
        $job->setAttempts(1);
        $this->assertSame(5, $this->invokeCalculateBackoff($worker, $job));

        $job->setAttempts(2);
        $this->assertSame(15, $this->invokeCalculateBackoff($worker, $job));

        $job->setAttempts(3);
        $this->assertSame(45, $this->invokeCalculateBackoff($worker, $job));

        // 超出取末值
        $job->setAttempts(7);
        $this->assertSame(45, $this->invokeCalculateBackoff($worker, $job));
    }

    public function testCalculateBackoffEmptyArrayFallsBackToRetryAfter(): void
    {
        [$manager] = $this->makeManager();
        $worker = new Worker($manager);

        $job = new QueueBackoff_FailingJob();
        $job->retryAfter = 33;
        $job->backoff = [];
        $job->setAttempts(1);

        $this->assertSame(33, $this->invokeCalculateBackoff($worker, $job));
    }

    private function invokeCalculateBackoff(Worker $worker, Job $job): int
    {
        $method = new \ReflectionMethod(Worker::class, 'calculateBackoff');

        return $method->invoke($worker, $job);
    }

    // ================================================================
    // effectiveTimeout 语义
    // ================================================================

    public function testEffectiveTimeoutTakesMinimumOfWorkerAndJob(): void
    {
        [$manager] = $this->makeManager();
        $worker = new Worker($manager);

        $job = new QueueBackoff_FailingJob();
        $job->timeout = 10;

        $method = new \ReflectionMethod(Worker::class, 'effectiveTimeout');

        $this->assertSame(10, $method->invoke($worker, $job, 60));
        $this->assertSame(5, $method->invoke($worker, $job, 5));

        // 任务 timeout <= 0 沿用 worker 值
        $job->timeout = 0;
        $this->assertSame(60, $method->invoke($worker, $job, 60));
    }

    // ================================================================
    // 行为：释放延迟按 backoff 写入
    // ================================================================

    public function testFailedJobReleasedWithBackoffDelay(): void
    {
        [$manager] = $this->makeManager();

        $job = new QueueBackoff_FailingJob();
        $job->backoff = [5, 15];
        $job->maxTries = 3;
        $job->retryAfter = 90;

        $manager->connection('database')->push($job);

        $worker = new Worker($manager);
        $this->assertTrue($worker->runNextJob('database', ['default'], 3));

        $row = $this->pdo->query('SELECT * FROM jobs')->fetch(PDO::FETCH_ASSOC);

        $this->assertNotSame(false, $row, '任务应按 backoff 释放回队列');
        $delay = (int) $row['available_at'] - time();
        $this->assertGreaterThanOrEqual(3, $delay);
        $this->assertLessThanOrEqual(5, $delay);
        $this->assertEquals(1, (int) $row['attempts']);
    }

    // ================================================================
    // 行为：超时强制中断
    // ================================================================

    public function testTimeoutAlarmInterruptsLongRunningJob(): void
    {
        if (!function_exists('pcntl_alarm')) {
            $this->markTestSkipped('pcntl extension not loaded');
        }

        [$manager] = $this->makeManager();

        $job = new QueueBackoff_SleeperJob();
        $job->seconds = 3;
        $job->timeout = 1;
        $job->maxTries = 1;

        $manager->connection('database')->push($job);

        $worker = new Worker($manager);

        $start = microtime(true);
        $worker->runNextJob('database', ['default'], 1, 10);
        $elapsed = microtime(true) - $start;

        // 任务应被 SIGALRM 中断（远早于 3 秒），并因 maxTries=1 记为失败
        $this->assertLessThan(2.5, $elapsed, '超时应中断 sleep');
        $this->assertSame(1, $worker->getFailed());
        $this->assertSame(0, QueueBackoff_SleeperJob::$completedCount);

        $failed = $this->pdo->query('SELECT COUNT(*) FROM failed_jobs')->fetchColumn();
        $this->assertEquals(1, (int) $failed);
    }

    public function testTimeoutExceptionCountsTowardMaxTries(): void
    {
        if (!function_exists('pcntl_alarm')) {
            $this->markTestSkipped('pcntl extension not loaded');
        }

        [$manager] = $this->makeManager();

        $job = new QueueBackoff_SleeperJob();
        $job->seconds = 3;
        $job->timeout = 1;
        $job->maxTries = 2;
        $job->backoff = 30;

        $manager->connection('database')->push($job);

        $worker = new Worker($manager);
        $worker->runNextJob('database', ['default'], 2, 10);

        // 第一次超时 → 尝试 1 < 2 → 按 backoff 30 秒释放
        $row = $this->pdo->query('SELECT * FROM jobs')->fetch(PDO::FETCH_ASSOC);
        $this->assertNotSame(false, $row);
        $this->assertEquals(1, (int) $row['attempts']);
        $delay = (int) $row['available_at'] - time();
        $this->assertGreaterThanOrEqual(28, $delay);
        $this->assertLessThanOrEqual(30, $delay);

        $this->assertSame(0, $worker->getFailed());
    }

    public function testShortJobCompletesWithinTimeout(): void
    {
        [$manager] = $this->makeManager();

        $job = new QueueBackoff_SleeperJob();
        $job->seconds = 0;
        $job->timeout = 10;
        $job->maxTries = 1;

        $manager->connection('database')->push($job);

        $worker = new Worker($manager);
        $worker->runNextJob('database', ['default'], 1, 10);

        $this->assertSame(1, QueueBackoff_SleeperJob::$completedCount);
        $this->assertSame(0, $worker->getFailed());

        $remaining = $this->pdo->query('SELECT COUNT(*) FROM jobs')->fetchColumn();
        $this->assertEquals(0, (int) $remaining);
    }
}

class QueueBackoff_FailingJob extends Job
{
    public function handle(): void
    {
        throw new \RuntimeException('boom');
    }
}

class QueueBackoff_SleeperJob extends Job
{
    public static int $completedCount = 0;

    public int $seconds = 1;

    public function handle(): void
    {
        if ($this->seconds > 0) {
            sleep($this->seconds);
        }

        self::$completedCount++;
    }

    public static function resetState(): void
    {
        self::$completedCount = 0;
    }
}
