<?php

declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/QueueTestHelpers.php';

use Bin\Console\Input;
use Bin\Console\Kernel;
use Bin\Console\Output;
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
        $this->createQueueTables();

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

        $this->assertSame(0, $exitCode);
        $this->assertSame('worked once', \QueueTest_TestJob::$lastResult);
        $this->assertSame(1, \QueueTest_TestJob::$handleCount);
        $this->assertSame(0, $this->queue->size('default'));
    }

    public function testQueueFailedListsFailedJobs(): void
    {
        $this->queue->logFailedJob(
            'database',
            'default',
            new \QueueTest_TestJob('failed for list'),
            new \RuntimeException('boom')
        );

        [$exitCode, $output] = $this->runCommandWithOutput('queue:failed', ['database']);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('database', $output);
        $this->assertStringContainsString('default', $output);
        $this->assertStringContainsString(\QueueTest_TestJob::class, $output);
    }

    public function testQueueRetryMovesFailedJobBackToQueue(): void
    {
        $this->queue->logFailedJob(
            'database',
            'default',
            new \QueueTest_TestJob('retry me'),
            new \RuntimeException('boom')
        );
        $failed = $this->queue->getFailedJobs();

        $exitCode = Kernel::callSilent('queue:retry', [(string) $failed[0]['id'], 'database']);

        $this->assertSame(0, $exitCode);
        $this->assertSame([], $this->queue->getFailedJobs());
        $this->assertSame(1, $this->queue->size('default'));
    }

    public function testQueueForgetRemovesFailedJob(): void
    {
        $this->queue->logFailedJob(
            'database',
            'default',
            new \QueueTest_TestJob('forget me'),
            new \RuntimeException('boom')
        );
        $failed = $this->queue->getFailedJobs();

        $exitCode = Kernel::callSilent('queue:forget', [(string) $failed[0]['id'], 'database']);

        $this->assertSame(0, $exitCode);
        $this->assertSame([], $this->queue->getFailedJobs());
    }

    public function testQueueFlushRequiresForce(): void
    {
        $this->queue->logFailedJob(
            'database',
            'default',
            new \QueueTest_TestJob('keep me'),
            new \RuntimeException('boom')
        );

        $exitCode = Kernel::callSilent('queue:flush', ['database']);

        $this->assertSame(1, $exitCode);
        $this->assertCount(1, $this->queue->getFailedJobs());
    }

    public function testQueueFlushWithForceDeletesFailedJobs(): void
    {
        $this->queue->logFailedJob(
            'database',
            'default',
            new \QueueTest_TestJob('flush me'),
            new \RuntimeException('boom')
        );

        $exitCode = Kernel::callSilent('queue:flush', ['database', '--force' => true]);

        $this->assertSame(0, $exitCode);
        $this->assertSame([], $this->queue->getFailedJobs());
    }

    private function createQueueTables(): void
    {
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

    /**
     * @return array{0:int, 1:string}
     */
    private function runCommandWithOutput(string $command, array $arguments = []): array
    {
        Kernel::discover();

        $instance = Kernel::getCommand($command);
        $instance->parseSignature();

        $argv = ['script', $command];
        foreach ($arguments as $key => $value) {
            if (is_int($key)) {
                $argv[] = $value;
                continue;
            }

            $argv[] = str_starts_with((string) $key, '--')
                ? $key . '=' . $value
                : '--' . $key . '=' . $value;
        }

        ob_start();
        $exitCode = $instance->run(new Input($argv), new Output());
        $output = ob_get_clean();

        return [$exitCode, $output];
    }
}
