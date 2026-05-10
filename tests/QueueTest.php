<?php

declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/QueueTestHelpers.php';

use Bin\App\App;
use Bin\Queue\Drivers\DatabaseQueue;
use Bin\Queue\Drivers\SyncQueue;
use Bin\Queue\InvalidPayloadException;
use Bin\Queue\Job;
use Bin\Queue\QueueManager;
use Bin\Queue\Worker;
use Bin\Testing\TestCase;
use PDO;
use PDOStatement;

/**
 * 队列系统测试
 */
class QueueTest extends TestCase
{
    private ?PDO $pdo = null;

    protected function setUp(): void
    {
        parent::setUp();
        QueueManager::resetInstance();
        \QueueTest_TestJob::resetState();
        \QueueTest_FailingJob::resetState();
        \QueueTest_DispatchableJob::resetState();
        \QueueTest_FinallyFailingJob::resetState();
        \QueueTest_FailedCallbackThrowingJob::resetState();
        \QueueTest_InjectedJob::resetState();

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->createJobsTable();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        QueueManager::resetInstance();
    }

    private function createJobsTable(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS jobs (
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
            CREATE TABLE IF NOT EXISTS failed_jobs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                connection VARCHAR(255) NOT NULL,
                queue VARCHAR(255) NOT NULL,
                payload TEXT NOT NULL,
                exception TEXT NOT NULL,
                failed_at INTEGER NOT NULL
            )
        ");
    }

    // ─── SyncQueue 测试 ───

    public function testSyncQueuePushExecutesImmediately(): void
    {
        $queue = new SyncQueue();
        $queue->push(new \QueueTest_TestJob('sync executed'));

        $this->assertEquals('sync executed', \QueueTest_TestJob::$lastResult);
    }

    public function testSyncQueueLaterExecutesImmediately(): void
    {
        $queue = new SyncQueue();
        $queue->later(60, new \QueueTest_TestJob('later executed'));

        $this->assertEquals('later executed', \QueueTest_TestJob::$lastResult);
    }

    public function testSyncQueuePopReturnsNull(): void
    {
        $queue = new SyncQueue();
        $this->assertNull($queue->pop());
    }

    public function testSyncQueueSizeIsZero(): void
    {
        $queue = new SyncQueue();
        $this->assertEquals(0, $queue->size());
    }

    public function testSyncQueueDeleteReturnsTrue(): void
    {
        $queue = new SyncQueue();
        $this->assertTrue($queue->delete('anything'));
    }

    public function testSyncQueueReleaseReturnsTrue(): void
    {
        $queue = new SyncQueue();
        $this->assertTrue($queue->release('anything'));
    }

    public function testSyncQueuePushRawExecutesJob(): void
    {
        $queue = new SyncQueue();
        $job = new \QueueTest_TestJob('raw executed');
        $queue->pushRaw($job->toJson());

        $this->assertEquals('raw executed', \QueueTest_TestJob::$lastResult);
    }

    // ─── DatabaseQueue 测试 ───

    public function testDatabaseQueuePush(): void
    {
        $queue = new DatabaseQueue('default', $this->pdo);
        $id = $queue->push(new \QueueTest_TestJob('db job'), 'emails');

        $this->assertGreaterThan(0, $id);

        $stmt = $this->pdo->query("SELECT * FROM jobs WHERE id = {$id}");
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertEquals('emails', $record['queue']);
        $this->assertEquals(0, (int) $record['attempts']);
        $this->assertNull($record['reserved_at']);
    }

    public function testDatabaseQueueLater(): void
    {
        $queue = new DatabaseQueue('default', $this->pdo);

        $beforeTime = time() + 55;
        $id = $queue->later(60, new \QueueTest_TestJob('delayed'), 'delayed');
        $afterTime = time() + 65;

        $stmt = $this->pdo->query("SELECT available_at FROM jobs WHERE id = {$id}");
        $availableAt = (int) $stmt->fetchColumn();

        $this->assertTrue($availableAt >= $beforeTime && $availableAt <= $afterTime);
    }

    public function testDatabaseQueuePop(): void
    {
        $queue = new DatabaseQueue('default', $this->pdo);
        $queue->push(new \QueueTest_TestJob('popped'), 'default');

        $popped = $queue->pop('default');

        $this->assertNotNull($popped);
        $this->assertInstanceOf(Job::class, $popped);
        $this->assertEquals(1, $popped->getAttempts());
    }

    public function testDatabaseQueuePopReturnsNullWhenEmpty(): void
    {
        $queue = new DatabaseQueue('default', $this->pdo);
        $this->assertNull($queue->pop('default'));
    }

    public function testDatabaseQueuePopSkipsDelayedJobs(): void
    {
        $queue = new DatabaseQueue('default', $this->pdo);
        $queue->later(3600, new \QueueTest_TestJob('delayed'), 'default');

        $this->assertNull($queue->pop('default'));
    }

    public function testDatabaseQueuePopCanRetryExpiredReservedJob(): void
    {
        $queue = new DatabaseQueue('default', $this->pdo, 1);
        $queue->push(new \QueueTest_TestJob('reserved'), 'default');

        $first = $queue->pop('default');
        $this->assertNotNull($first);
        $this->assertEquals(1, $first->getAttempts());

        $this->assertNull($queue->pop('default'));

        $this->pdo->exec('UPDATE jobs SET reserved_at = ' . (time() - 2));

        $second = $queue->pop('default');
        $this->assertNotNull($second);
        $this->assertEquals($first->getJobId(), $second->getJobId());
        $this->assertEquals(2, $second->getAttempts());
    }

    public function testDatabaseQueueDoesNotReturnStaleSelectedJobWhenReservationLost(): void
    {
        $this->pdo = new QueueTest_StaleReservationPdo('sqlite::memory:');
        $this->createJobsTable();

        $queue = new DatabaseQueue('default', $this->pdo);
        $queue->push(new \QueueTest_TestJob('stale'), 'default');

        $this->pdo->simulateStaleReservation = true;

        $this->assertNull($queue->pop('default'));
        $this->assertTrue($this->pdo->didSimulateStaleReservation);
    }

    public function testDatabaseQueueDelete(): void
    {
        $queue = new DatabaseQueue('default', $this->pdo);
        $queue->push(new \QueueTest_TestJob('deleted'), 'default');

        $popped = $queue->pop('default');
        $this->assertNotNull($popped);

        $this->assertTrue($queue->delete($popped));

        $stmt = $this->pdo->query("SELECT COUNT(*) FROM jobs");
        $this->assertEquals(0, (int) $stmt->fetchColumn());
    }

    public function testDatabaseQueueRelease(): void
    {
        $queue = new DatabaseQueue('default', $this->pdo);
        $queue->push(new \QueueTest_TestJob('released'), 'default');

        $popped = $queue->pop('default');
        $this->assertNotNull($popped);

        $this->assertTrue($queue->release($popped, 0));

        $rePopped = $queue->pop('default');
        $this->assertNotNull($rePopped);
    }

    public function testDatabaseQueueReleaseDoesNotIncrementAttemptsUntilNextPop(): void
    {
        $queue = new DatabaseQueue('default', $this->pdo);
        $queue->push(new \QueueTest_TestJob('released once'), 'default');

        $job = $queue->pop('default');
        $this->assertNotNull($job);
        $this->assertEquals(1, $job->getAttempts());

        $this->assertTrue($queue->release($job, 0));

        $stmt = $this->pdo->query('SELECT attempts, reserved_at FROM jobs LIMIT 1');
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertEquals(1, (int) $record['attempts']);
        $this->assertNull($record['reserved_at']);

        $rePopped = $queue->pop('default');
        $this->assertNotNull($rePopped);
        $this->assertEquals(2, $rePopped->getAttempts());
    }

    public function testDatabaseQueueStaleOwnerDeleteCannotDeleteNewerReservation(): void
    {
        $queue = new DatabaseQueue('default', $this->pdo, 1);
        $queue->push(new \QueueTest_TestJob('stale delete'), 'default');

        $first = $queue->pop('default');
        $this->assertNotNull($first);

        $this->pdo->exec('UPDATE jobs SET reserved_at = ' . (time() - 2));

        $second = $queue->pop('default');
        $this->assertNotNull($second);
        $this->assertEquals(2, $second->getAttempts());

        $this->assertFalse($queue->delete($first));

        $stmt = $this->pdo->query('SELECT attempts, reserved_at FROM jobs LIMIT 1');
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertTrue($record !== false);
        $this->assertEquals(2, (int) $record['attempts']);
        $this->assertNotNull($record['reserved_at']);
    }

    public function testDatabaseQueueStaleOwnerReleaseCannotReleaseNewerReservation(): void
    {
        $queue = new DatabaseQueue('default', $this->pdo, 1);
        $queue->push(new \QueueTest_TestJob('stale release'), 'default');

        $first = $queue->pop('default');
        $this->assertNotNull($first);

        $this->pdo->exec('UPDATE jobs SET reserved_at = ' . (time() - 2));

        $second = $queue->pop('default');
        $this->assertNotNull($second);
        $this->assertEquals(2, $second->getAttempts());

        $this->assertFalse($queue->release($first, 0));

        $stmt = $this->pdo->query('SELECT attempts, reserved_at FROM jobs LIMIT 1');
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertTrue($record !== false);
        $this->assertEquals(2, (int) $record['attempts']);
        $this->assertNotNull($record['reserved_at']);
    }

    public function testDatabaseQueueStaleOwnerFinalFailCannotDeleteNewerReservation(): void
    {
        $queue = new DatabaseQueue('default', $this->pdo, 1);
        $queue->push(new \QueueTest_TestJob('stale fail'), 'default');

        $first = $queue->pop('default');
        $this->assertNotNull($first);

        $this->pdo->exec('UPDATE jobs SET reserved_at = ' . (time() - 2));

        $second = $queue->pop('default');
        $this->assertNotNull($second);
        $this->assertEquals(2, $second->getAttempts());

        $this->assertFalse($queue->failJob('database', 'default', $first, new \RuntimeException('stale')));

        $stmt = $this->pdo->query('SELECT COUNT(*) FROM failed_jobs');
        $this->assertEquals(0, (int) $stmt->fetchColumn());

        $stmt = $this->pdo->query('SELECT attempts, reserved_at FROM jobs LIMIT 1');
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertTrue($record !== false);
        $this->assertEquals(2, (int) $record['attempts']);
        $this->assertNotNull($record['reserved_at']);
    }

    public function testDatabaseQueueCanForgetFailedJob(): void
    {
        $queue = new DatabaseQueue('default', $this->pdo);
        $queue->logFailedJob('database', 'default', new \QueueTest_TestJob('failed'), new \RuntimeException('boom'));

        $failed = $queue->getFailedJobs();
        $this->assertCount(1, $failed);

        $this->assertTrue($queue->forgetFailedJob((int) $failed[0]['id']));
        $this->assertSame([], $queue->getFailedJobs());
    }

    public function testDatabaseQueueCanFlushFailedJobs(): void
    {
        $queue = new DatabaseQueue('default', $this->pdo);
        $queue->logFailedJob('database', 'default', new \QueueTest_TestJob('failed one'), new \RuntimeException('one'));
        $queue->logFailedJob('database', 'default', new \QueueTest_TestJob('failed two'), new \RuntimeException('two'));

        $this->assertCount(2, $queue->getFailedJobs());
        $this->assertEquals(2, $queue->flushFailedJobs());
        $this->assertSame([], $queue->getFailedJobs());
    }

    public function testDatabaseQueueCanFindFailedJob(): void
    {
        $queue = new DatabaseQueue('default', $this->pdo);
        $queue->logFailedJob('database', 'default', new \QueueTest_TestJob('failed'), new \RuntimeException('boom'));

        $failed = $queue->getFailedJobs();
        $this->assertCount(1, $failed);

        $found = $queue->findFailedJob((int) $failed[0]['id']);
        $this->assertNotNull($found);
        $this->assertEquals($failed[0]['id'], $found['id']);
        $this->assertNull($queue->findFailedJob(999));
    }

    public function testDatabaseQueueMovesInvalidPayloadToFailedJobsAndContinues(): void
    {
        $queue = new DatabaseQueue('default', $this->pdo);
        $queue->pushRaw('not-json', 'default');
        $queue->push(new \QueueTest_TestJob('valid after invalid'), 'default');

        $thrown = false;
        try {
            $queue->pop('default');
        } catch (InvalidPayloadException $e) {
            $thrown = true;
            $this->assertEquals('not-json', $e->getPayload());
        }

        $this->assertTrue($thrown, 'Expected InvalidPayloadException');

        $stmt = $this->pdo->query("SELECT COUNT(*) FROM jobs WHERE payload = 'not-json'");
        $this->assertEquals(0, (int) $stmt->fetchColumn());

        $stmt = $this->pdo->query('SELECT * FROM failed_jobs');
        $failed = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(1, $failed);
        $this->assertEquals('not-json', $failed[0]['payload']);

        $job = $queue->pop('default');
        $this->assertNotNull($job);
        $this->assertEquals('valid after invalid', $job->result);
    }

    public function testDatabaseQueueRemovesInvalidPayloadFromJobsTable(): void
    {
        $queue = new DatabaseQueue('default', $this->pdo);
        $queue->pushRaw('not-json', 'default');

        $thrown = false;
        try {
            $queue->pop('default');
        } catch (InvalidPayloadException $e) {
            $thrown = true;
            $this->assertEquals('not-json', $e->getPayload());
        }

        $this->assertTrue($thrown, 'Expected InvalidPayloadException');

        $stmt = $this->pdo->query('SELECT COUNT(*) FROM jobs');
        $this->assertEquals(0, (int) $stmt->fetchColumn());

        $stmt = $this->pdo->query('SELECT * FROM failed_jobs');
        $failed = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(1, $failed);
        $this->assertEquals('not-json', $failed[0]['payload']);
    }

    public function testDatabaseQueueRollsBackInvalidPayloadReservationWhenFailureLoggingFails(): void
    {
        $queue = new DatabaseQueue('default', $this->pdo);
        $queue->pushRaw('not-json', 'default');
        $this->pdo->exec('DROP TABLE failed_jobs');

        $thrown = false;
        try {
            $queue->pop('default');
        } catch (\Throwable $e) {
            $thrown = true;
        }

        $this->assertTrue($thrown, 'Expected failed job logging to throw');

        $stmt = $this->pdo->query('SELECT attempts, reserved_at FROM jobs LIMIT 1');
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertEquals(0, (int) $record['attempts']);
        $this->assertNull($record['reserved_at']);
    }

    public function testDatabaseQueueRollsBackInvalidPayloadReservationWhenFailureLoggingReturnsFalse(): void
    {
        $this->pdo = new QueueTest_FailingStatementPdo('sqlite::memory:');
        $this->createJobsTable();

        $queue = new DatabaseQueue('default', $this->pdo);
        $queue->pushRaw('not-json', 'default');
        $this->pdo->failExecuteFor('INSERT INTO failed_jobs');

        $thrown = false;
        try {
            $queue->pop('default');
        } catch (\RuntimeException $e) {
            $thrown = true;
            $this->assertStringContainsString('failed_jobs', $e->getMessage());
        }

        $this->assertTrue($thrown, 'Expected failed payload logging to throw');

        $stmt = $this->pdo->query('SELECT attempts, reserved_at FROM jobs LIMIT 1');
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertEquals(0, (int) $record['attempts']);
        $this->assertNull($record['reserved_at']);
    }

    public function testQueueManagerCanInjectResolvedConnectionForTests(): void
    {
        $dbQueue = new DatabaseQueue('default', $this->pdo);

        $manager = new QueueManager();
        $manager->setConfig([
            'database' => ['driver' => 'database', 'connection' => 'default'],
        ]);

        $returned = $manager->setConnection('database', $dbQueue);

        $this->assertSame($manager, $returned);
        $this->assertSame($dbQueue, $manager->connection('database'));
    }

    public function testDatabaseQueueSize(): void
    {
        $queue = new DatabaseQueue('default', $this->pdo);

        $this->assertEquals(0, $queue->size('default'));

        $queue->push(new \QueueTest_TestJob(), 'default');
        $this->assertEquals(1, $queue->size('default'));

        $queue->push(new \QueueTest_TestJob(), 'default');
        $this->assertEquals(2, $queue->size('default'));
    }

    public function testDatabaseQueueSizeIncludesExpiredReservedJobsButNotFreshReservedJobs(): void
    {
        $queue = new DatabaseQueue('default', $this->pdo, 1);
        $queue->push(new \QueueTest_TestJob('size'), 'default');

        $fresh = $queue->pop('default');
        $this->assertNotNull($fresh);
        $this->assertEquals(0, $queue->size('default'));

        $this->pdo->exec('UPDATE jobs SET reserved_at = ' . (time() - 2));

        $this->assertEquals(1, $queue->size('default'));

        $expired = $queue->pop('default');
        $this->assertNotNull($expired);
        $this->assertEquals(0, $queue->size('default'));
    }

    public function testDatabaseQueueDifferentQueues(): void
    {
        $queue = new DatabaseQueue('default', $this->pdo);

        $queue->push(new \QueueTest_TestJob(), 'emails');
        $queue->push(new \QueueTest_TestJob(), 'reports');
        $queue->push(new \QueueTest_TestJob(), 'emails');

        $this->assertEquals(2, $queue->size('emails'));
        $this->assertEquals(1, $queue->size('reports'));
        $this->assertEquals(0, $queue->size('default'));
    }

    public function testDatabaseQueueLogFailedJob(): void
    {
        $queue = new DatabaseQueue('default', $this->pdo);
        $exception = new \RuntimeException('test error');

        $queue->logFailedJob('database', 'default', new \QueueTest_TestJob('failed'), $exception);

        $stmt = $this->pdo->query("SELECT * FROM failed_jobs");
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->assertCount(1, $records);
        $this->assertEquals('database', $records[0]['connection']);
        $this->assertStringContainsString('test error', $records[0]['exception']);
    }

    public function testDatabaseQueueRetryFailedJobKeepsFailedRecordWhenRequeueReturnsFalse(): void
    {
        $this->pdo = new QueueTest_FailingStatementPdo('sqlite::memory:');
        $this->createJobsTable();

        $queue = new DatabaseQueue('default', $this->pdo);
        $queue->logFailedJob('database', 'default', new \QueueTest_TestJob('retry'), new \RuntimeException('boom'));
        $failed = $queue->getFailedJobs();

        $this->pdo->failExecuteFor('INSERT INTO jobs');

        $thrown = false;
        try {
            $queue->retryFailedJob((int) $failed[0]['id']);
        } catch (\RuntimeException $e) {
            $thrown = true;
            $this->assertStringContainsString('jobs', $e->getMessage());
        }

        $this->assertTrue($thrown, 'Expected retry requeue failure to throw');
        $this->assertCount(1, $queue->getFailedJobs());

        $stmt = $this->pdo->query('SELECT COUNT(*) FROM jobs');
        $this->assertEquals(0, (int) $stmt->fetchColumn());
    }

    public function testDatabaseQueuePopAndExecute(): void
    {
        $queue = new DatabaseQueue('default', $this->pdo);
        $queue->push(new \QueueTest_TestJob('executed'), 'default');

        $job = $queue->pop('default');
        $this->assertNotNull($job);

        $job->handle();
        $queue->delete($job);

        $this->assertEquals('executed', \QueueTest_TestJob::$lastResult);

        $stmt = $this->pdo->query("SELECT COUNT(*) FROM jobs");
        $this->assertEquals(0, (int) $stmt->fetchColumn());
    }

    // ─── Job 基类测试 ───

    public function testJobDisplayName(): void
    {
        $job = new \QueueTest_TestJob();
        $this->assertEquals(\QueueTest_TestJob::class, $job->displayName());
    }

    public function testJobAttempts(): void
    {
        $job = new \QueueTest_TestJob();

        $this->assertEquals(0, $job->getAttempts());

        $job->setAttempts(2);
        $this->assertEquals(2, $job->getAttempts());
    }

    public function testJobMaxTries(): void
    {
        $job = new \QueueTest_TestJob();
        $job->maxTries = 5;

        $this->assertEquals(5, $job->maxTries);
    }

    public function testJobHasExceededMaxTries(): void
    {
        $job = new \QueueTest_TestJob();
        $job->maxTries = 3;

        $this->assertFalse($job->hasExceededMaxTries());

        $job->setAttempts(3);
        $this->assertTrue($job->hasExceededMaxTries());
    }

    public function testJobOnQueue(): void
    {
        $job = new \QueueTest_TestJob();

        $this->assertEquals('default', $job->getQueue());

        $job->onQueue('emails');
        $this->assertEquals('emails', $job->getQueue());
    }

    public function testJobSerialization(): void
    {
        $job = new \QueueTest_TestJob('serialized');
        $job->setAttempts(2);

        $array = $job->toArray();

        $this->assertArrayHasKey('displayName', $array);
        $this->assertArrayHasKey('job', $array);
        $this->assertArrayHasKey('maxTries', $array);
        $this->assertEquals(2, $array['attempts']);

        $json = $job->toJson();
        $decoded = json_decode($json, true);
        $this->assertNotNull($decoded);
        $this->assertEquals(\QueueTest_TestJob::class, $decoded['displayName']);
    }

    public function testJobSerializationRoundTrip(): void
    {
        $original = new \QueueTest_TestJob('round trip');
        $original->setAttempts(1);
        $original->onQueue('emails');

        $payload = $original->toJson();
        $data = json_decode($payload, true);
        $restored = unserialize($data['job']);

        $this->assertInstanceOf(\QueueTest_TestJob::class, $restored);
        $this->assertEquals('round trip', $restored->result);
    }

    // ─── QueueManager 测试 ───

    public function testQueueManagerSingleton(): void
    {
        $a = QueueManager::getInstance();
        $b = QueueManager::getInstance();

        $this->assertSame($a, $b);
    }

    public function testQueueManagerResetInstance(): void
    {
        $a = QueueManager::getInstance();
        QueueManager::resetInstance();
        $b = QueueManager::getInstance();

        $this->assertNotSame($a, $b);
    }

    public function testQueueManagerSyncConnection(): void
    {
        $manager = new QueueManager();
        $manager->setConfig([
            'sync' => ['driver' => 'sync'],
        ]);

        $connection = $manager->connection('sync');
        $this->assertInstanceOf(SyncQueue::class, $connection);
    }

    public function testQueueManagerConnectionCached(): void
    {
        $manager = new QueueManager();
        $manager->setConfig([
            'sync' => ['driver' => 'sync'],
        ]);

        $a = $manager->connection('sync');
        $b = $manager->connection('sync');

        $this->assertSame($a, $b);
    }

    public function testQueueManagerFlush(): void
    {
        $manager = new QueueManager();
        $manager->setConfig([
            'sync' => ['driver' => 'sync'],
        ]);

        $a = $manager->connection('sync');
        $manager->flush();
        $b = $manager->connection('sync');

        $this->assertNotSame($a, $b);
    }

    public function testQueueManagerUnconfiguredThrows(): void
    {
        $manager = new QueueManager();
        $manager->setConfig([]);

        $thrown = false;
        try {
            $manager->connection('missing');
        } catch (\RuntimeException $e) {
            $thrown = true;
            $this->assertStringContainsString('not configured', $e->getMessage());
        }
        $this->assertTrue($thrown, 'Expected RuntimeException');
    }

    public function testQueueManagerUnsupportedDriverThrows(): void
    {
        $manager = new QueueManager();
        $manager->setConfig([
            'sqs' => ['driver' => 'sqs'],
        ]);

        $thrown = false;
        try {
            $manager->connection('sqs');
        } catch (\RuntimeException $e) {
            $thrown = true;
            $this->assertStringContainsString('Unsupported', $e->getMessage());
        }
        $this->assertTrue($thrown, 'Expected RuntimeException');
    }

    public function testQueueManagerProxyPush(): void
    {
        $manager = new QueueManager();
        $manager->setConfig([
            'sync' => ['driver' => 'sync'],
        ]);

        $manager->push(new \QueueTest_TestJob('proxy push'));

        $this->assertEquals('proxy push', \QueueTest_TestJob::$lastResult);
    }

    public function testQueueManagerSetDefaultConnection(): void
    {
        $manager = new QueueManager();
        $manager->setConfig([
            'sync' => ['driver' => 'sync'],
        ]);
        $manager->setDefaultConnection('sync');

        $connection = $manager->connection();
        $this->assertInstanceOf(SyncQueue::class, $connection);
    }

    // ─── Worker 测试 ───

    public function testWorkerProcessSuccessfulJob(): void
    {
        $manager = new QueueManager();
        $manager->setConfig([
            'sync' => ['driver' => 'sync'],
        ]);

        $worker = new Worker($manager);
        $worker->process(new \QueueTest_TestJob('worker ok'), 'sync', 'default', 3);

        $this->assertEquals('worker ok', \QueueTest_TestJob::$lastResult);
    }

    public function testWorkerProcessFailedJobIncrementsFailedCount(): void
    {
        $manager = new QueueManager();
        $manager->setConfig([
            'sync' => ['driver' => 'sync'],
        ]);

        $worker = new Worker($manager);
        $job = new \QueueTest_FailingJob();
        $job->setAttempts(3);

        $worker->process($job, 'sync', 'default', 3);

        $this->assertEquals(1, $worker->getFailed());
        $this->assertEquals(1, \QueueTest_FailingJob::$failCount);
    }

    public function testWorkerProcessFailedJobReleasesWhenUnderMaxTries(): void
    {
        $dbQueue = new DatabaseQueue('default', $this->pdo);

        $manager = new QueueManager();
        $manager->setConfig([
            'database' => ['driver' => 'database', 'connection' => 'default'],
        ]);

        $manager->setConnection('database', $dbQueue);

        $dbQueue->push(new \QueueTest_FailingJob(), 'default');

        $worker = new Worker($manager);
        $job = $dbQueue->pop('default');
        $this->assertNotNull($job);

        $worker->process($job, 'database', 'default', 3);

        $this->assertEquals(0, $worker->getFailed());
    }

    public function testWorkerProcessThrowsAndKeepsLiveJobWhenFinalFailureLoggingReturnsFalse(): void
    {
        $this->pdo = new QueueTest_FailingStatementPdo('sqlite::memory:');
        $this->createJobsTable();

        $dbQueue = new DatabaseQueue('default', $this->pdo);
        $manager = new QueueManager();
        $manager->setConfig([
            'database' => ['driver' => 'database', 'connection' => 'default'],
        ]);
        $manager->setConnection('database', $dbQueue);

        $dbQueue->push(new \QueueTest_FinallyFailingJob(), 'default');
        $job = $dbQueue->pop('default');
        $this->assertNotNull($job);
        $this->pdo->failExecuteFor('INSERT INTO failed_jobs');

        $worker = new Worker($manager);
        $thrown = false;
        try {
            $worker->process($job, 'database', 'default', 1);
        } catch (\RuntimeException $e) {
            $thrown = true;
            $this->assertStringContainsString('failed_jobs', $e->getMessage());
        }

        $this->assertTrue($thrown, 'Expected final failure logging to throw');

        $stmt = $this->pdo->query('SELECT COUNT(*) FROM jobs');
        $this->assertEquals(1, (int) $stmt->fetchColumn());
    }

    public function testWorkerPersistsFinalFailureWhenFailedCallbackThrows(): void
    {
        $dbQueue = new DatabaseQueue('default', $this->pdo);
        $manager = new QueueManager();
        $manager->setConfig([
            'database' => ['driver' => 'database', 'connection' => 'default'],
        ]);
        $manager->setConnection('database', $dbQueue);

        $dbQueue->push(new \QueueTest_FailedCallbackThrowingJob(), 'default');
        $job = $dbQueue->pop('default');
        $this->assertNotNull($job);

        $worker = new Worker($manager);
        $worker->process($job, 'database', 'default', 1);

        $stmt = $this->pdo->query('SELECT COUNT(*) FROM failed_jobs');
        $this->assertEquals(1, (int) $stmt->fetchColumn());

        $stmt = $this->pdo->query('SELECT COUNT(*) FROM jobs');
        $this->assertEquals(0, (int) $stmt->fetchColumn());

        $this->assertEquals(1, $worker->getFailed());
        $this->assertEquals(1, \QueueTest_FailedCallbackThrowingJob::$failedCount);
    }

    public function testWorkerOnlyCallsFailedCallbackOnFinalFailure(): void
    {
        $dbQueue = new DatabaseQueue('default', $this->pdo);
        $manager = new QueueManager();
        $manager->setConfig([
            'database' => ['driver' => 'database', 'connection' => 'default'],
        ]);
        $manager->setConnection('database', $dbQueue);

        $dbQueue->push(new \QueueTest_FinallyFailingJob(), 'default');

        $worker = new Worker($manager);
        $firstJob = $dbQueue->pop('default');
        $this->assertNotNull($firstJob);

        $worker->process($firstJob, 'database', 'default', 3);

        $this->assertEquals(0, \QueueTest_FinallyFailingJob::$failedCount);
        $this->assertEquals(0, $worker->getFailed());

        $secondJob = $dbQueue->pop('default');
        $this->assertNotNull($secondJob);
        $worker->process($secondJob, 'database', 'default', 3);

        $thirdJob = $dbQueue->pop('default');
        $this->assertNotNull($thirdJob);
        $worker->process($thirdJob, 'database', 'default', 3);

        $this->assertEquals(1, \QueueTest_FinallyFailingJob::$failedCount);
        $this->assertEquals(1, $worker->getFailed());
        $this->assertCount(1, $dbQueue->getFailedJobs());
    }

    public function testWorkerProcessesQueuesByPriorityOrder(): void
    {
        $dbQueue = new DatabaseQueue('default', $this->pdo);
        $manager = new QueueManager();
        $manager->setConfig([
            'database' => ['driver' => 'database', 'connection' => 'default'],
        ]);
        $manager->setConnection('database', $dbQueue);

        $dbQueue->push(new \QueueTest_TestJob('default first'), 'default');
        $dbQueue->push(new \QueueTest_TestJob('high first'), 'high');

        $worker = new Worker($manager);
        $processed = $worker->runNextJob('database', ['high', 'default'], 3);

        $this->assertTrue($processed);
        $this->assertEquals('high first', \QueueTest_TestJob::$lastResult);
    }

    public function testWorkerRunDoesNotProcessJobWhenAlreadyStopped(): void
    {
        $dbQueue = new DatabaseQueue('default', $this->pdo);
        $manager = new QueueManager();
        $manager->setConfig([
            'database' => ['driver' => 'database', 'connection' => 'default'],
        ]);
        $manager->setConnection('database', $dbQueue);

        $dbQueue->push(new \QueueTest_TestJob('should not run'), 'default');

        $worker = new Worker($manager);
        $worker->stop();

        $exitCode = $worker->run('database', ['default'], 3, 1, true);

        $this->assertEquals(0, $exitCode);
        $this->assertEquals('', \QueueTest_TestJob::$lastResult);
        $this->assertEquals(1, $dbQueue->size('default'));
    }

    public function testBadPayloadIsMovedToFailedJobsAndRemovedFromQueue(): void
    {
        $payload = '{"job":"not a serialized job"}';
        $dbQueue = new DatabaseQueue('default', $this->pdo);
        $manager = new QueueManager();
        $manager->setConfig([
            'database' => ['driver' => 'database', 'connection' => 'default'],
        ]);
        $manager->setConnection('database', $dbQueue);

        $dbQueue->pushRaw($payload, 'default');

        $worker = new Worker($manager);
        $processed = $worker->runNextJob('database', ['default'], 3);

        $this->assertTrue($processed);
        $this->assertEquals(1, $worker->getFailed());

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM jobs WHERE queue = :queue');
        $stmt->execute([':queue' => 'default']);
        $this->assertEquals(0, (int) $stmt->fetchColumn());

        $failedJobs = $dbQueue->getFailedJobs();
        $this->assertCount(1, $failedJobs);
        $this->assertEquals($payload, $failedJobs[0]['payload']);
    }

    public function testWorkerInvokesJobHandleThroughContainer(): void
    {
        App::getInstance()->instance(\QueueTest_InjectedDependency::class, new \QueueTest_InjectedDependency('from container'));

        $manager = new QueueManager();
        $manager->setConfig([
            'sync' => ['driver' => 'sync'],
        ]);

        $worker = new Worker($manager);
        $worker->process(new \QueueTest_InjectedJob(), 'sync', 'default', 3);

        $this->assertEquals('from container', \QueueTest_InjectedJob::$value);

        App::getInstance()->getContainer()->forget(\QueueTest_InjectedDependency::class);
    }

    public function testWorkerStop(): void
    {
        $manager = new QueueManager();
        $worker = new Worker($manager);

        $worker->stop();

        $ref = new \ReflectionProperty($worker, 'shouldQuit');
        $ref->setAccessible(true);
        $this->assertTrue($ref->getValue($worker));
    }

    // ─── Dispatchable trait 测试 ───

    public function testDispatchableDispatchSync(): void
    {
        \QueueTest_DispatchableJob::dispatchSync();

        $this->assertTrue(\QueueTest_DispatchableJob::$dispatched);
    }

    public function testPendingDispatchExplicitDispatchUsesSelectedConnectionQueueAndDelay(): void
    {
        $dbQueue = new DatabaseQueue('default', $this->pdo);
        $manager = QueueManager::getInstance();
        $manager->setConfig([
            'sync' => ['driver' => 'sync'],
            'database' => ['driver' => 'database', 'connection' => 'default'],
        ]);
        $manager->setDefaultConnection('sync');
        $manager->setConnection('database', $dbQueue);

        $beforeTime = time() + 55;
        $pending = \QueueTest_DispatchableJob::dispatch()
            ->onConnection('database')
            ->onQueue('emails')
            ->delay(60);

        $returned = $pending->dispatch();
        $afterTime = time() + 65;

        $this->assertSame($pending, $returned);
        $this->assertFalse(\QueueTest_DispatchableJob::$dispatched);

        $stmt = $this->pdo->query("SELECT queue, available_at FROM jobs");
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertEquals('emails', $record['queue']);
        $this->assertTrue(
            (int) $record['available_at'] >= $beforeTime && (int) $record['available_at'] <= $afterTime
        );
    }

    public function testPendingDispatchExplicitDispatchThenDestructorDoesNotDispatchTwice(): void
    {
        $dbQueue = new DatabaseQueue('default', $this->pdo);
        $manager = QueueManager::getInstance();
        $manager->setConfig([
            'database' => ['driver' => 'database', 'connection' => 'default'],
        ]);
        $manager->setDefaultConnection('database');
        $manager->setConnection('database', $dbQueue);

        $pending = \QueueTest_DispatchableJob::dispatch()->onConnection('database');
        $pending->dispatch();
        unset($pending);

        $this->assertEquals(1, $dbQueue->size('default'));
    }

    public function testPendingDispatchDoesNotRetryInDestructorWhenExplicitSyncDispatchThrows(): void
    {
        $manager = QueueManager::getInstance();
        $manager->setConfig([
            'sync' => ['driver' => 'sync'],
        ]);
        $manager->setDefaultConnection('sync');

        $pending = new \Bin\Queue\PendingDispatch(new \QueueTest_FinallyFailingJob());

        $thrown = false;
        try {
            $pending->dispatch();
        } catch (\RuntimeException $e) {
            $thrown = true;
        }

        $this->assertTrue($thrown, 'Expected sync dispatch to throw');

        unset($pending);

        $this->assertEquals(1, \QueueTest_FinallyFailingJob::$handleCount);
    }
}

class QueueTest_StaleReservationPdo extends PDO
{
    public bool $simulateStaleReservation = false;
    public bool $didSimulateStaleReservation = false;

    public function __construct(string $dsn)
    {
        parent::__construct($dsn);

        $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->setAttribute(PDO::ATTR_STATEMENT_CLASS, [QueueTest_StaleReservationStatement::class, [$this]]);
    }
}

class QueueTest_StaleReservationStatement extends PDOStatement
{
    protected function __construct(private QueueTest_StaleReservationPdo $pdo)
    {
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        $record = parent::fetch($mode, $cursorOrientation, $cursorOffset);

        if (
            $record !== false
            && $this->pdo->simulateStaleReservation
            && !$this->pdo->didSimulateStaleReservation
            && is_array($record)
            && array_key_exists('payload', $record)
        ) {
            $this->pdo->didSimulateStaleReservation = true;
            $stmt = $this->pdo->prepare('UPDATE jobs SET reserved_at = :reserved WHERE id = :id');
            $stmt->execute([':reserved' => time(), ':id' => $record['id']]);
        }

        return $record;
    }
}

class QueueTest_FailingStatementPdo extends PDO
{
    /** @var list<string> */
    private array $failingExecutePatterns = [];

    public function __construct(string $dsn)
    {
        parent::__construct($dsn);

        $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->setAttribute(PDO::ATTR_STATEMENT_CLASS, [QueueTest_FailingStatement::class, [$this]]);
    }

    public function failExecuteFor(string $sqlPattern): void
    {
        $this->failingExecutePatterns[] = $this->normalizeSql($sqlPattern);
    }

    public function shouldFailExecute(string $sql): bool
    {
        $normalizedSql = $this->normalizeSql($sql);

        foreach ($this->failingExecutePatterns as $pattern) {
            if (str_contains($normalizedSql, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeSql(string $sql): string
    {
        return trim((string) preg_replace('/\s+/', ' ', str_replace('`', '', $sql)));
    }
}

class QueueTest_FailingStatement extends PDOStatement
{
    protected function __construct(private QueueTest_FailingStatementPdo $pdo)
    {
    }

    public function execute(?array $params = null): bool
    {
        if ($this->pdo->shouldFailExecute($this->queryString)) {
            return false;
        }

        return parent::execute($params);
    }
}
