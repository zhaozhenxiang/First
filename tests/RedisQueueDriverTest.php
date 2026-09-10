<?php

declare(strict_types=1);

namespace Tests;

use Bin\Queue\Contracts\QueueInterface;
use Bin\Queue\Drivers\RedisQueue;
use Bin\Queue\InvalidPayloadException;
use Bin\Queue\Job;
use Bin\Queue\QueueManager;
use Bin\Testing\TestCase;
use InvalidArgumentException;

/**
 * Track E Redis 队列驱动测试 — 通过注入内存 fake 连接覆盖全部逻辑（无需 ext-redis）
 */
class RedisQueueDriverTest extends TestCase
{
    protected FakeRedisConnection $redis;
    protected RedisQueue $queue;

    protected function setUp(): void
    {
        parent::setUp();
        QueueManager::resetInstance();

        $this->redis = new FakeRedisConnection();
        $this->queue = new RedisQueue($this->redis, 'queues:');
    }

    protected function tearDown(): void
    {
        QueueManager::resetInstance();
        parent::tearDown();
    }

    public function testDriverImplementsContract(): void
    {
        $this->assertTrue($this->queue instanceof QueueInterface);
    }

    public function testPushAndPopRoundTrip(): void
    {
        $job = new RedisQueueTest_Job('payload-a');
        $this->queue->push($job);

        $this->assertSame(1, $this->queue->size());

        $popped = $this->queue->pop();

        $this->assertInstanceOf(RedisQueueTest_Job::class, $popped);
        $this->assertEquals('payload-a', $popped->value);
        $this->assertSame(1, $popped->getAttempts());
        $this->assertNotEquals('', $popped->getJobId());
        $this->assertSame(0, $this->queue->size());
    }

    public function testFifoOrder(): void
    {
        $this->queue->push(new RedisQueueTest_Job('first'));
        $this->queue->push(new RedisQueueTest_Job('second'));

        $this->assertEquals('first', $this->queue->pop()->value);
        $this->assertEquals('second', $this->queue->pop()->value);
    }

    public function testPopReturnsNullWhenEmpty(): void
    {
        $this->assertNull($this->queue->pop());
    }

    public function testLaterDelaysUntilDue(): void
    {
        $this->queue->later(60, new RedisQueueTest_Job('later'));

        // 未到期：不迁移，就绪列表为空
        $this->assertNull($this->queue->pop('default'));
        $this->assertSame(1, $this->queue->size());

        // 修改到期时间为过去 → pop 迁移并返回
        $this->redis->zsets['queues:delayed:default'] = array_map(
            static fn (int $score): int => $score - 120,
            $this->redis->zsets['queues:delayed:default']
        );

        $popped = $this->queue->pop();

        $this->assertInstanceOf(RedisQueueTest_Job::class, $popped);
        $this->assertEquals('later', $popped->value);
        $this->assertSame(0, $this->queue->size());
    }

    public function testReleaseImmediatePushesToReadyList(): void
    {
        $this->queue->push(new RedisQueueTest_Job('retry'));
        $job = $this->queue->pop();

        $this->queue->release($job, 0);

        $this->assertSame(1, $this->queue->size());
        $requeued = $this->queue->pop();
        $this->assertEquals('retry', $requeued->value);
        $this->assertSame(2, $requeued->getAttempts());
    }

    public function testReleaseWithDelayGoesToZset(): void
    {
        $this->queue->push(new RedisQueueTest_Job('delayed-retry'));
        $job = $this->queue->pop();

        $this->queue->release($job, 30);

        $this->assertSame(1, $this->queue->size());
        $this->assertSame(1, $this->redis->zcard('queues:delayed:default'));
        $this->assertNull($this->queue->pop(), '延迟任务未到期不可弹出');
    }

    public function testSizeCombinesReadyAndDelayed(): void
    {
        $this->queue->push(new RedisQueueTest_Job('a'));
        $this->queue->later(60, new RedisQueueTest_Job('b'));

        $this->assertSame(2, $this->queue->size());
    }

    public function testPushRawStoresPayloadString(): void
    {
        $this->queue->pushRaw('{"raw":true}');

        $this->assertSame(1, $this->queue->size());
        $this->assertSame('{"raw":true}', $this->redis->lists['queues:default'][0] ?? null);
    }

    public function testPushRejectsNonJobPayloads(): void
    {
        $this->assertThrows(InvalidArgumentException::class, function (): void {
            $this->queue->push('not-a-job');
        });
    }

    public function testPopThrowsOnCorruptPayload(): void
    {
        $this->queue->pushRaw('{"corrupt":true}');

        $this->assertThrows(InvalidPayloadException::class, function (): void {
            $this->queue->pop();
        });
    }

    public function testDeleteIsNoopSuccess(): void
    {
        $this->queue->push(new RedisQueueTest_Job('gone'));
        $job = $this->queue->pop();

        $this->assertTrue($this->queue->delete($job));
    }

    public function testQueuePrefixKeysAreNamespaced(): void
    {
        $this->queue->push(new RedisQueueTest_Job('x'), 'emails');

        $this->assertSame(['queues:emails'], array_keys($this->redis->lists));
    }

    public function testManagerResolvesRedisDriverAndFailsLoudlyWithoutExtension(): void
    {
        if (class_exists(\Redis::class)) {
            $this->markTestSkipped('ext-redis loaded; loud-failure path not reachable');
        }

        $manager = QueueManager::getInstance();
        $manager->setConfig([
            'redis-test' => [
                'driver' => 'redis',
                'host' => '127.0.0.1',
                'port' => 6379,
            ],
        ]);

        try {
            $manager->connection('redis-test');
            $this->fail('Expected RuntimeException without ext-redis');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('redis extension', $exception->getMessage());
        }
    }
}

class RedisQueueTest_Job extends Job
{
    public function __construct(public string $value = '')
    {
    }

    public function handle(): void
    {
    }
}

/**
 * 内存 fake Redis 连接（仅实现驱动用到的方法）
 */
class FakeRedisConnection
{
    /** @var array<string, array<int, string>> */
    public array $lists = [];

    /** @var array<string, array<string, int>> */
    public array $zsets = [];

    public function rpush(string $key, string $value): int
    {
        $this->lists[$key] ??= [];
        $this->lists[$key][] = $value;

        return count($this->lists[$key]);
    }

    public function lpop(string $key): ?string
    {
        if (!isset($this->lists[$key]) || $this->lists[$key] === []) {
            return null;
        }

        return array_shift($this->lists[$key]);
    }

    public function llen(string $key): int
    {
        return count($this->lists[$key] ?? []);
    }

    public function zadd(string $key, int $score, string $member): int
    {
        $this->zsets[$key] ??= [];
        $isNew = !array_key_exists($member, $this->zsets[$key]);
        $this->zsets[$key][$member] = $score;

        return $isNew ? 1 : 0;
    }

    /**
     * @return array<int, string>
     */
    public function zrangebyscore(string $key, string $min, string $max): array
    {
        $members = $this->zsets[$key] ?? [];
        $minScore = $min === '-inf' ? PHP_INT_MIN : (int) $min;
        $maxScore = $max === '+inf' ? PHP_INT_MAX : (int) $max;

        $due = array_filter($members, static fn (int $score): bool => $score >= $minScore && $score <= $maxScore);

        // 按 score 升序返回，保证迁移顺序稳定
        uasort($due, static fn (int $a, int $b): int => $a <=> $b);

        return array_keys($due);
    }

    public function zrem(string $key, string $member): int
    {
        if (!array_key_exists($member, $this->zsets[$key] ?? [])) {
            return 0;
        }

        unset($this->zsets[$key][$member]);

        return 1;
    }

    public function zcard(string $key): int
    {
        return count($this->zsets[$key] ?? []);
    }
}
