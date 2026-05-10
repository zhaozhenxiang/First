# Queue Worker Production Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make First's existing database-backed Queue / Job / Worker lifecycle reliable enough for later Mail, Notification, and Scheduling work.

**Architecture:** Keep the current `QueueManager`, `DatabaseQueue`, `SyncQueue`, `Job`, `PendingDispatch`, and `Worker` structure. Tighten storage semantics in `DatabaseQueue`, centralize execution and retry decisions in `Worker`, and keep CLI commands as thin adapters over those APIs.

**Tech Stack:** PHP 8.3+, custom Bin Console, custom Queue subsystem, PDO SQLite test fixtures, existing `php test` runner.

---

## Context and Constraints

- Spec: `docs/superpowers/specs/2026-05-10-queue-worker-production-design.md`
- Existing queue implementation:
  - `bin/Queue/QueueManager.php`
  - `bin/Queue/Job.php`
  - `bin/Queue/PendingDispatch.php`
  - `bin/Queue/Dispatchable.php`
  - `bin/Queue/ShouldQueue.php`
  - `bin/Queue/Drivers/SyncQueue.php`
  - `bin/Queue/Drivers/DatabaseQueue.php`
  - `bin/Queue/Worker.php`
  - `bin/Queue/Contracts/QueueInterface.php`
- Existing tests:
  - `tests/QueueTest.php`
  - `tests/QueueTestHelpers.php`
  - `tests/ConsoleTest.php`
  - `tests/ConsoleArtisanParityTest.php`
- Keep scope focused on database-backed queue production behavior.
- Do not add Redis/SQS/Beanstalkd, batching, unique jobs, encrypted jobs, job middleware, queue failover, Scheduler, Notification, or Broadcasting.
- Do not rewrite Console. Add normal command classes under `bin/Console/Commands`.
- Use TDD. Write failing tests first, verify failure, then implement.

## File Structure

- Create: `bin/Queue/InvalidPayloadException.php`
  Responsibility: typed exception for reserved jobs whose payload cannot hydrate into a `Job`.
- Modify: `bin/Queue/QueueManager.php`
  Responsibility: test-friendly resolved connection injection.
- Modify: `bin/Queue/Drivers/DatabaseQueue.php`
  Responsibility: corrected attempts / release semantics, invalid payload failure handling, failed-job retry / forget / flush APIs.
- Modify: `bin/Queue/Drivers/SyncQueue.php`
  Responsibility: execute jobs through the same container call path where practical.
- Modify: `bin/Queue/Worker.php`
  Responsibility: priority queue selection, once/daemon loops, container-backed job execution, retry/final-failure decisions.
- Modify: `bin/Queue/PendingDispatch.php`
  Responsibility: connection selection, explicit dispatch, duplicate dispatch guard.
- Create: `bin/Console/Commands/QueueWorkCommand.php`
  Responsibility: parse worker options and call `Worker`.
- Create: `bin/Console/Commands/QueueFailedCommand.php`
  Responsibility: list database-backed failed jobs.
- Create: `bin/Console/Commands/QueueRetryCommand.php`
  Responsibility: retry one failed job id.
- Create: `bin/Console/Commands/QueueForgetCommand.php`
  Responsibility: delete one failed job id.
- Create: `bin/Console/Commands/QueueFlushCommand.php`
  Responsibility: clear failed jobs when `--force` is supplied.
- Modify: `tests/QueueTestHelpers.php`
  Responsibility: add fixture jobs for final-failure and container-injection cases.
- Modify: `tests/QueueTest.php`
  Responsibility: low-level queue driver, dispatch, and worker behavior tests.
- Create: `tests/QueueCommandTest.php`
  Responsibility: command discovery, `queue:work --once`, failed job listing, retry, forget, and flush behavior.

---

### Task 1: DatabaseQueue Storage Semantics

**Files:**
- Create: `bin/Queue/InvalidPayloadException.php`
- Modify: `bin/Queue/Drivers/DatabaseQueue.php`
- Modify: `bin/Queue/QueueManager.php`
- Modify: `tests/QueueTest.php`

- [ ] **Step 1: Write failing storage semantics tests**

Add these methods to `tests/QueueTest.php` after `testDatabaseQueueRelease()`:

```php
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
```

- [ ] **Step 2: Run focused queue tests and verify failure**

Run:

```bash
php test tests/QueueTest.php
```

Expected: FAIL with missing methods:

```text
Call to undefined method Bin\Queue\Drivers\DatabaseQueue::forgetFailedJob()
Call to undefined method Bin\Queue\Drivers\DatabaseQueue::flushFailedJobs()
Call to undefined method Bin\Queue\QueueManager::setConnection()
```

- [ ] **Step 3: Add `InvalidPayloadException`**

Create `bin/Queue/InvalidPayloadException.php`:

```php
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
```

- [ ] **Step 4: Add connection injection to QueueManager**

In `bin/Queue/QueueManager.php`, add this import:

```php
use Bin\Queue\Contracts\QueueInterface;
```

The import already exists in the current file. If it is still present, do not duplicate it.

Add this method after `setDefaultConnection()`:

```php
public function setConnection(string $name, QueueInterface $connection): static
{
    $this->connections[$name] = $connection;

    return $this;
}
```

- [ ] **Step 5: Add failed-job APIs to DatabaseQueue**

In `bin/Queue/Drivers/DatabaseQueue.php`, add this import:

```php
use Bin\Queue\InvalidPayloadException;
```

Add these methods after `retryFailedJob()`:

```php
public function forgetFailedJob(int $id): bool
{
    $sql = "DELETE FROM `{$this->failedTable}` WHERE id = :id";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([':id' => $id]);

    return $stmt->rowCount() > 0;
}

public function flushFailedJobs(): int
{
    $sql = "DELETE FROM `{$this->failedTable}`";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute();

    return $stmt->rowCount();
}

public function findFailedJob(int $id): ?array
{
    $sql = "SELECT * FROM `{$this->failedTable}` WHERE id = :id";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([':id' => $id]);

    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    return $record !== false ? $record : null;
}
```

Add these protected helpers before `sanitizeIdentifier()`:

```php
protected function logFailedPayload(string $connection, string $queue, string $payload, \Throwable $exception): bool
{
    $sql = "INSERT INTO `{$this->failedTable}` (connection, queue, payload, exception, failed_at)
            VALUES (:connection, :queue, :payload, :exception, :failed_at)";

    $stmt = $this->pdo->prepare($sql);

    return $stmt->execute([
        ':connection' => $connection,
        ':queue' => $queue,
        ':payload' => $payload,
        ':exception' => (string) $exception,
        ':failed_at' => time(),
    ]);
}

protected function deleteById(int $id): bool
{
    $sql = "DELETE FROM `{$this->table}` WHERE id = :id";
    $stmt = $this->pdo->prepare($sql);

    return $stmt->execute([':id' => $id]);
}
```

- [ ] **Step 6: Fix invalid payload and attempts semantics**

In `bin/Queue/Drivers/DatabaseQueue.php`, replace the end of `pop()`:

```php
return $this->hydrateJob($record, true);
```

with:

```php
$job = $this->hydrateJob($record, true);

if ($job === null) {
    $exception = new InvalidPayloadException((int) $record['id'], $queue, (string) $record['payload']);
    $this->logFailedPayload($this->connectionName, $queue, (string) $record['payload'], $exception);
    $this->deleteById((int) $record['id']);

    throw $exception;
}

return $job;
```

Confirm `release()` only clears `reserved_at` and updates `available_at`. It should look like this:

```php
public function release(mixed $job, int $delay = 0): bool
{
    $id = $this->getJobDatabaseId($job);

    if ($id === null) {
        return false;
    }

    $availableAt = time() + $delay;

    $sql = "UPDATE `{$this->table}`
            SET reserved_at = NULL, available_at = :available
            WHERE id = :id";

    $stmt = $this->pdo->prepare($sql);

    return $stmt->execute([':available' => $availableAt, ':id' => $id]);
}
```

- [ ] **Step 7: Verify Task 1 passes**

Run:

```bash
php test tests/QueueTest.php
```

Expected: PASS for `QueueTest`.

- [ ] **Step 8: Commit Task 1**

Run:

```bash
git add bin/Queue/InvalidPayloadException.php bin/Queue/QueueManager.php bin/Queue/Drivers/DatabaseQueue.php tests/QueueTest.php
git commit -m "feat: stabilize database queue storage"
```

---

### Task 2: Worker Execution Lifecycle

**Files:**
- Modify: `tests/QueueTestHelpers.php`
- Modify: `tests/QueueTest.php`
- Modify: `bin/Queue/Worker.php`
- Modify: `bin/Queue/Drivers/SyncQueue.php`

- [ ] **Step 1: Add worker fixture classes**

Append these classes to `tests/QueueTestHelpers.php`:

```php
class QueueTest_FinallyFailingJob extends Job
{
    public static int $handleCount = 0;
    public static int $failedCount = 0;

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

    public function handle(QueueTest_InjectedDependency $dependency): void
    {
        self::$value = $dependency->value;
    }

    public static function resetState(): void
    {
        self::$value = '';
    }
}
```

In `tests/QueueTest.php::setUp()`, add:

```php
\QueueTest_FinallyFailingJob::resetState();
\QueueTest_InjectedJob::resetState();
```

- [ ] **Step 2: Add failing worker lifecycle tests**

Add `use Bin\App\App;` to `tests/QueueTest.php`.

Add these methods after `testWorkerProcessFailedJobReleasesWhenUnderMaxTries()`:

```php
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
}
```

- [ ] **Step 3: Run focused tests and verify failure**

Run:

```bash
php test tests/QueueTest.php
```

Expected: FAIL because `Worker::runNextJob()` does not exist and `Worker::process()` still calls `handle()` directly.

- [ ] **Step 4: Update Worker imports**

In `bin/Queue/Worker.php`, add:

```php
use Bin\App\App;
use Bin\Queue\InvalidPayloadException;
```

- [ ] **Step 5: Add priority and loop methods to Worker**

In `bin/Queue/Worker.php`, add these methods before `daemon()`:

```php
public function run(
    string $connection,
    array $queues = ['default'],
    int $tries = 3,
    int $sleep = 1,
    bool $once = false
): int {
    do {
        $processed = $this->runNextJob($connection, $queues, $tries);

        if ($once) {
            return $processed ? 0 : 1;
        }

        if (!$processed) {
            sleep($sleep);
        }

        if (function_exists('pcntl_signal_dispatch')) {
            pcntl_signal_dispatch();
        }
    } while (!$this->shouldQuit);

    return 0;
}

public function runNextJob(string $connection, array $queues = ['default'], int $tries = 3): bool
{
    foreach ($queues as $queue) {
        $queueName = trim((string) $queue);
        if ($queueName === '') {
            continue;
        }

        try {
            $job = $this->manager->connection($connection)->pop($queueName);
        } catch (InvalidPayloadException) {
            $this->failed++;
            return true;
        }

        if ($job === null) {
            continue;
        }

        $this->process($job, $connection, $queueName, $tries);
        $this->processed++;

        return true;
    }

    return false;
}
```

Replace `daemon()` with:

```php
public function daemon(string $connection, string $queue = 'default', int $tries = 3, int $sleep = 1): void
{
    $queues = array_map('trim', explode(',', $queue));
    $this->run($connection, $queues, $tries, $sleep, false);
}
```

- [ ] **Step 6: Execute jobs through the container**

In `bin/Queue/Worker.php`, replace the body of `process()` with:

```php
public function process(Job $job, string $connection, string $queue, int $tries = 3): void
{
    try {
        App::getInstance()->getContainer()->call([$job, 'handle']);
        $this->manager->connection($connection)->delete($job);
    } catch (\Throwable $e) {
        $this->handleFailure($job, $connection, $queue, $e, $tries);
    }
}
```

Replace `handleFailure()` with:

```php
protected function handleFailure(Job $job, string $connection, string $queue, \Throwable $exception, int $maxTries): void
{
    $effectiveMaxTries = $maxTries > 0 ? min($maxTries, $job->maxTries) : $job->maxTries;

    if ($job->getAttempts() >= $effectiveMaxTries || $job->hasExceededMaxTries()) {
        $job->failed($exception);
        $this->logFailedJob($connection, $queue, $job, $exception);
        $this->manager->connection($connection)->delete($job);
        $this->failed++;

        return;
    }

    $this->manager->connection($connection)->release($job, $job->retryAfter);
}
```

- [ ] **Step 7: Align SyncQueue execution path**

In `bin/Queue/Drivers/SyncQueue.php`, add:

```php
use Bin\App\App;
```

Replace the `Job` execution inside `push()`:

```php
$job->handle();
```

with:

```php
App::getInstance()->getContainer()->call([$job, 'handle']);
```

- [ ] **Step 8: Verify Task 2 passes**

Run:

```bash
php test tests/QueueTest.php
```

Expected: PASS for `QueueTest`.

- [ ] **Step 9: Commit Task 2**

Run:

```bash
git add bin/Queue/Worker.php bin/Queue/Drivers/SyncQueue.php tests/QueueTest.php tests/QueueTestHelpers.php
git commit -m "feat: stabilize queue worker lifecycle"
```

---

### Task 3: PendingDispatch Connection and Duplicate Guard

**Files:**
- Modify: `tests/QueueTest.php`
- Modify: `bin/Queue/PendingDispatch.php`

- [ ] **Step 1: Write failing dispatch tests**

Add these methods near the Dispatchable tests in `tests/QueueTest.php`:

```php
public function testPendingDispatchCanChooseConnectionQueueAndDelayExplicitly(): void
{
    $dbQueue = new DatabaseQueue('default', $this->pdo);
    $manager = QueueManager::getInstance();
    $manager->setConfig([
        'database' => ['driver' => 'database', 'connection' => 'default'],
    ]);
    $manager->setConnection('database', $dbQueue);
    $manager->setDefaultConnection('database');

    $pending = \QueueTest_TestJob::dispatch('queued explicitly')
        ->onConnection('database')
        ->onQueue('emails')
        ->delay(60);

    $this->assertSame($pending, $pending->dispatch());
    unset($pending);

    $this->assertEquals(1, $dbQueue->size('emails'));
}

public function testPendingDispatchExplicitDispatchDoesNotDispatchAgainInDestructor(): void
{
    $dbQueue = new DatabaseQueue('default', $this->pdo);
    $manager = QueueManager::getInstance();
    $manager->setConfig([
        'database' => ['driver' => 'database', 'connection' => 'default'],
    ]);
    $manager->setConnection('database', $dbQueue);
    $manager->setDefaultConnection('database');

    $pending = \QueueTest_TestJob::dispatch('single dispatch')->onConnection('database');
    $pending->dispatch();
    unset($pending);

    $this->assertEquals(1, $dbQueue->size('default'));
}
```

- [ ] **Step 2: Run focused tests and verify failure**

Run:

```bash
php test tests/QueueTest.php
```

Expected: FAIL because `PendingDispatch::onConnection()` and public `dispatch()` are not available.

- [ ] **Step 3: Update PendingDispatch properties and methods**

In `bin/Queue/PendingDispatch.php`, replace the class body with this implementation:

```php
class PendingDispatch
{
    protected Job $job;
    protected ?string $connection = null;
    protected bool $dispatched = false;

    public function __construct(Job $job)
    {
        $this->job = $job;
    }

    public function onConnection(string $connection): static
    {
        $this->connection = $connection;

        return $this;
    }

    public function onQueue(string $queue): static
    {
        $this->job->onQueue($queue);

        return $this;
    }

    public function delay(int $seconds): static
    {
        $this->job->delay = $seconds;

        return $this;
    }

    public function __destruct()
    {
        $this->dispatch();
    }

    public function dispatch(): static
    {
        if ($this->dispatched) {
            return $this;
        }

        $manager = QueueManager::getInstance();
        $connection = $manager->connection($this->connection);

        if ($this->job->delay > 0) {
            $connection->later($this->job->delay, $this->job, $this->job->getQueue());
        } else {
            $connection->push($this->job, $this->job->getQueue());
        }

        $this->dispatched = true;

        return $this;
    }
}
```

- [ ] **Step 4: Verify Task 3 passes**

Run:

```bash
php test tests/QueueTest.php
```

Expected: PASS for `QueueTest`.

- [ ] **Step 5: Commit Task 3**

Run:

```bash
git add bin/Queue/PendingDispatch.php tests/QueueTest.php
git commit -m "feat: improve pending queue dispatch"
```

---

### Task 4: Queue Console Commands

**Files:**
- Create: `bin/Console/Commands/QueueWorkCommand.php`
- Create: `bin/Console/Commands/QueueFailedCommand.php`
- Create: `bin/Console/Commands/QueueRetryCommand.php`
- Create: `bin/Console/Commands/QueueForgetCommand.php`
- Create: `bin/Console/Commands/QueueFlushCommand.php`
- Create: `tests/QueueCommandTest.php`

- [ ] **Step 1: Create failing command tests**

Create `tests/QueueCommandTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/QueueTestHelpers.php';

use Bin\Console\Kernel;
use Bin\Queue\Drivers\DatabaseQueue;
use Bin\Queue\QueueManager;
use Bin\Testing\TestCase;
use PDO;

class QueueCommandTest extends TestCase
{
    private PDO $pdo;
    private DatabaseQueue $queue;

    protected function setUp(): void
    {
        parent::setUp();

        Kernel::clear();
        QueueManager::resetInstance();
        \QueueTest_TestJob::resetState();

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->createTables();

        $this->queue = new DatabaseQueue('default', $this->pdo);

        $manager = QueueManager::getInstance();
        $manager->setConfig([
            'database' => ['driver' => 'database', 'connection' => 'default'],
        ]);
        $manager->setDefaultConnection('database');
        $manager->setConnection('database', $this->queue);
    }

    protected function tearDown(): void
    {
        Kernel::clear();
        QueueManager::resetInstance();

        parent::tearDown();
    }

    public function testQueueCommandsAreDiscoverable(): void
    {
        Kernel::discover();

        $this->assertTrue(Kernel::hasCommand('queue:work'));
        $this->assertTrue(Kernel::hasCommand('queue:failed'));
        $this->assertTrue(Kernel::hasCommand('queue:retry'));
        $this->assertTrue(Kernel::hasCommand('queue:forget'));
        $this->assertTrue(Kernel::hasCommand('queue:flush'));
    }

    public function testQueueWorkOnceProcessesOneJob(): void
    {
        $this->queue->push(new \QueueTest_TestJob('worked once'), 'default');

        $exitCode = Kernel::callSilent('queue:work', ['database', '--once' => true]);

        $this->assertEquals(0, $exitCode);
        $this->assertEquals('worked once', \QueueTest_TestJob::$lastResult);
        $this->assertEquals(0, $this->queue->size('default'));
    }

    public function testQueueFailedCommandListsFailedJobs(): void
    {
        $this->queue->logFailedJob('database', 'default', new \QueueTest_TestJob('failed'), new \RuntimeException('boom'));

        ob_start();
        $exitCode = Kernel::call('queue:failed', ['database']);
        $output = ob_get_clean();

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('database', $output);
        $this->assertStringContainsString('default', $output);
        $this->assertStringContainsString(\QueueTest_TestJob::class, $output);
    }

    public function testQueueRetryRequeuesFailedJob(): void
    {
        $this->queue->logFailedJob('database', 'emails', new \QueueTest_TestJob('retry me'), new \RuntimeException('boom'));
        $failed = $this->queue->getFailedJobs();

        $exitCode = Kernel::callSilent('queue:retry', [(string) $failed[0]['id'], 'database']);

        $this->assertEquals(0, $exitCode);
        $this->assertSame([], $this->queue->getFailedJobs());
        $this->assertEquals(1, $this->queue->size('emails'));
    }

    public function testQueueForgetDeletesFailedJob(): void
    {
        $this->queue->logFailedJob('database', 'default', new \QueueTest_TestJob('forget me'), new \RuntimeException('boom'));
        $failed = $this->queue->getFailedJobs();

        $exitCode = Kernel::callSilent('queue:forget', [(string) $failed[0]['id'], 'database']);

        $this->assertEquals(0, $exitCode);
        $this->assertSame([], $this->queue->getFailedJobs());
    }

    public function testQueueFlushRequiresForce(): void
    {
        $this->queue->logFailedJob('database', 'default', new \QueueTest_TestJob('flush me'), new \RuntimeException('boom'));

        $exitCode = Kernel::callSilent('queue:flush', ['database']);

        $this->assertEquals(1, $exitCode);
        $this->assertCount(1, $this->queue->getFailedJobs());
    }

    public function testQueueFlushWithForceClearsFailedJobs(): void
    {
        $this->queue->logFailedJob('database', 'default', new \QueueTest_TestJob('flush me'), new \RuntimeException('boom'));

        $exitCode = Kernel::callSilent('queue:flush', ['database', '--force' => true]);

        $this->assertEquals(0, $exitCode);
        $this->assertSame([], $this->queue->getFailedJobs());
    }

    private function createTables(): void
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
}

return new QueueCommandTest();
```

- [ ] **Step 2: Run command tests and verify failure**

Run:

```bash
php test tests/QueueCommandTest.php
```

Expected: FAIL because queue command classes do not exist and commands are not discoverable.

- [ ] **Step 3: Add QueueWorkCommand**

Create `bin/Console/Commands/QueueWorkCommand.php`:

```php
<?php

declare(strict_types=1);

namespace Bin\Console\Commands;

use Bin\Console\Command;
use Bin\Queue\QueueManager;
use Bin\Queue\Worker;

class QueueWorkCommand extends Command
{
    protected string $signature = 'queue:work {connection?} {--queue=default} {--tries=3} {--sleep=1} {--once}';
    protected string $description = 'Process queued jobs';

    public function execute(): int
    {
        $connection = (string) ($this->argument('connection') ?: config('queue.default', 'sync'));
        $queues = array_map('trim', explode(',', (string) $this->option('queue', 'default')));
        $tries = (int) $this->option('tries', 3);
        $sleep = (int) $this->option('sleep', 1);
        $once = $this->hasOption('once');

        try {
            $worker = new Worker(QueueManager::getInstance());
            $exitCode = $worker->run($connection, $queues, $tries, $sleep, $once);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return 1;
        }

        $this->info("Processed: {$worker->getProcessed()}, Failed: {$worker->getFailed()}");

        return $exitCode;
    }
}
```

- [ ] **Step 4: Add failed job command helpers inline**

Create `bin/Console/Commands/QueueFailedCommand.php`:

```php
<?php

declare(strict_types=1);

namespace Bin\Console\Commands;

use Bin\Console\Command;
use Bin\Queue\Drivers\DatabaseQueue;
use Bin\Queue\QueueManager;

class QueueFailedCommand extends Command
{
    protected string $signature = 'queue:failed {connection?}';
    protected string $description = 'List failed queue jobs';

    public function execute(): int
    {
        $connectionName = (string) ($this->argument('connection') ?: config('queue.default', 'sync'));
        $connection = QueueManager::getInstance()->connection($connectionName);

        if (!$connection instanceof DatabaseQueue) {
            $this->error("Queue connection [{$connectionName}] does not support failed jobs.");
            return 1;
        }

        $rows = [];
        foreach ($connection->getFailedJobs() as $job) {
            $payload = json_decode((string) $job['payload'], true) ?: [];
            $rows[] = [
                (string) $job['id'],
                (string) $job['connection'],
                (string) $job['queue'],
                (string) ($payload['displayName'] ?? 'raw payload'),
                date('Y-m-d H:i:s', (int) $job['failed_at']),
            ];
        }

        $this->output->table(['ID', 'Connection', 'Queue', 'Job', 'Failed At'], $rows);

        return 0;
    }
}
```

Create `bin/Console/Commands/QueueRetryCommand.php`:

```php
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
        $id = (int) $this->argument('id');
        $connectionName = (string) ($this->argument('connection') ?: config('queue.default', 'sync'));
        $connection = QueueManager::getInstance()->connection($connectionName);

        if (!$connection instanceof DatabaseQueue) {
            $this->error("Queue connection [{$connectionName}] does not support failed jobs.");
            return 1;
        }

        if (!$connection->retryFailedJob($id)) {
            $this->error("Failed job [{$id}] was not found.");
            return 1;
        }

        $this->info("Retried failed job [{$id}].");

        return 0;
    }
}
```

Create `bin/Console/Commands/QueueForgetCommand.php`:

```php
<?php

declare(strict_types=1);

namespace Bin\Console\Commands;

use Bin\Console\Command;
use Bin\Queue\Drivers\DatabaseQueue;
use Bin\Queue\QueueManager;

class QueueForgetCommand extends Command
{
    protected string $signature = 'queue:forget {id} {connection?}';
    protected string $description = 'Delete a failed queue job';

    public function execute(): int
    {
        $id = (int) $this->argument('id');
        $connectionName = (string) ($this->argument('connection') ?: config('queue.default', 'sync'));
        $connection = QueueManager::getInstance()->connection($connectionName);

        if (!$connection instanceof DatabaseQueue) {
            $this->error("Queue connection [{$connectionName}] does not support failed jobs.");
            return 1;
        }

        if (!$connection->forgetFailedJob($id)) {
            $this->error("Failed job [{$id}] was not found.");
            return 1;
        }

        $this->info("Deleted failed job [{$id}].");

        return 0;
    }
}
```

Create `bin/Console/Commands/QueueFlushCommand.php`:

```php
<?php

declare(strict_types=1);

namespace Bin\Console\Commands;

use Bin\Console\Command;
use Bin\Queue\Drivers\DatabaseQueue;
use Bin\Queue\QueueManager;

class QueueFlushCommand extends Command
{
    protected string $signature = 'queue:flush {connection?} {--force}';
    protected string $description = 'Delete all failed queue jobs';

    public function execute(): int
    {
        if (!$this->hasOption('force')) {
            $this->error('Refusing to flush failed jobs without --force.');
            return 1;
        }

        $connectionName = (string) ($this->argument('connection') ?: config('queue.default', 'sync'));
        $connection = QueueManager::getInstance()->connection($connectionName);

        if (!$connection instanceof DatabaseQueue) {
            $this->error("Queue connection [{$connectionName}] does not support failed jobs.");
            return 1;
        }

        $deleted = $connection->flushFailedJobs();
        $this->info("Deleted {$deleted} failed jobs.");

        return 0;
    }
}
```

- [ ] **Step 5: Verify command tests pass**

Run:

```bash
php test tests/QueueCommandTest.php
```

Expected: PASS for `QueueCommandTest`.

- [ ] **Step 6: Run console regression tests**

Run:

```bash
php test tests/ConsoleTest.php tests/ConsoleArtisanParityTest.php
```

Expected: PASS for both console test files.

- [ ] **Step 7: Commit Task 4**

Run:

```bash
git add bin/Console/Commands/QueueWorkCommand.php bin/Console/Commands/QueueFailedCommand.php bin/Console/Commands/QueueRetryCommand.php bin/Console/Commands/QueueForgetCommand.php bin/Console/Commands/QueueFlushCommand.php tests/QueueCommandTest.php
git commit -m "feat: add queue worker commands"
```

---

### Task 5: Bad Payload Regression and Full Verification

**Files:**
- Modify: `tests/QueueTest.php`
- Modify: `bin/Queue/Drivers/DatabaseQueue.php`
- Modify: `bin/Queue/Worker.php`

- [ ] **Step 1: Add bad payload regression test**

Add this method to `tests/QueueTest.php` after the worker lifecycle tests:

```php
public function testBadPayloadIsMovedToFailedJobsAndRemovedFromQueue(): void
{
    $dbQueue = new DatabaseQueue('default', $this->pdo);
    $manager = new QueueManager();
    $manager->setConfig([
        'database' => ['driver' => 'database', 'connection' => 'default'],
    ]);
    $manager->setConnection('database', $dbQueue);

    $dbQueue->pushRaw('{"job":"not a serialized job"}', 'default');

    $worker = new Worker($manager);
    $processed = $worker->runNextJob('database', ['default'], 3);

    $this->assertTrue($processed);
    $this->assertEquals(1, $worker->getFailed());
    $this->assertEquals(0, $dbQueue->size('default'));
    $this->assertCount(1, $dbQueue->getFailedJobs());
}
```

- [ ] **Step 2: Run QueueTest**

Run:

```bash
php test tests/QueueTest.php
```

Expected: PASS. If it fails because the bad payload remains in `jobs`, revisit Task 1's invalid payload handling before continuing.

- [ ] **Step 3: Run focused queue and console suite**

Run:

```bash
php test tests/QueueTest.php tests/QueueCommandTest.php tests/ConsoleTest.php tests/ConsoleArtisanParityTest.php
```

Expected: PASS for all four test files.

- [ ] **Step 4: Run full suite**

Run:

```bash
php test
```

Expected: PASS. Existing skipped tests or known PHP deprecation warnings may appear, but there must be no failures.

- [ ] **Step 5: Update graphify for code changes**

Run:

```bash
graphify update .
```

Expected: graphify completes successfully and updates local graph artifacts if code changed.

If `graphify update .` is not available, run the fallback from `CLAUDE.md`:

```bash
python3 -c "from graphify.watch import _rebuild_code; from pathlib import Path; _rebuild_code(Path('.'))"
```

Expected: fallback completes successfully. If both commands are unavailable, record the failure in the task summary.

- [ ] **Step 6: Inspect final status**

Run:

```bash
git status --short
```

Expected: only intentional queue code, tests, and graphify artifacts are modified.

- [ ] **Step 7: Commit final regression**

Run:

```bash
git add tests/QueueTest.php bin/Queue/Drivers/DatabaseQueue.php bin/Queue/Worker.php graphify-out
git commit -m "test: cover queue worker bad payload failure"
```

If `graphify-out` did not change, omit it from `git add`.

---

## Self-Review

- Spec coverage: covers database-backed `queue:work`, priority queues, retryable and exhausted failures, failed job commands, container method injection, bad payload handling, and queue/console regression tests.
- Scope control: does not include Redis/SQS, Horizon, batching, Scheduler, Notification, Broadcasting, or a Console rewrite.
- Placeholder scan: no placeholder implementation steps are intentionally left.
- Type consistency: `QueueManager::setConnection()`, `DatabaseQueue::forgetFailedJob()`, `DatabaseQueue::flushFailedJobs()`, `Worker::run()`, `Worker::runNextJob()`, `PendingDispatch::onConnection()`, and `PendingDispatch::dispatch()` are introduced before later tasks use them.
