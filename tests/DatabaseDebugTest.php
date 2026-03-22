<?php

declare(strict_types=1);

namespace Tests;

use Bin\Testing\TestCase;
use Bin\Database\Debug\DatabaseDebugger;
use Bin\Database\Debug\QueryLog;
use Bin\Database\Debug\QueryTimer;

/**
 * 数据库调试工具测试
 */
class DatabaseDebugTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DatabaseDebugger::enable();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        DatabaseDebugger::disable();
        DatabaseDebugger::clear();
    }

    // QueryLog 测试
    public function testQueryLogCreation(): void
    {
        $log = QueryLog::make('SELECT * FROM users', [], 5.5);

        $this->assertEquals('SELECT * FROM users', $log->sql);
        $this->assertEquals(5.5, $log->time);
        $this->assertEquals('default', $log->connection);
        $this->assertTrue($log->success);
    }

    public function testQueryLogType(): void
    {
        $select = QueryLog::make('SELECT * FROM users');
        $insert = QueryLog::make('INSERT INTO users VALUES (1, "test")');
        $update = QueryLog::make('UPDATE users SET name = "test"');
        $delete = QueryLog::make('DELETE FROM users WHERE id = 1');

        $this->assertEquals('SELECT', $select->getType());
        $this->assertEquals('INSERT', $insert->getType());
        $this->assertEquals('UPDATE', $update->getType());
        $this->assertEquals('DELETE', $delete->getType());
    }

    public function testQueryLogTypeCheckers(): void
    {
        $select = QueryLog::make('SELECT * FROM users');
        $insert = QueryLog::make('INSERT INTO users VALUES (1, "test")');

        $this->assertTrue($select->isSelect());
        $this->assertFalse($select->isInsert());
        $this->assertTrue($insert->isInsert());
        $this->assertFalse($insert->isSelect());
    }

    public function testQueryLogFormattedSql(): void
    {
        $log = QueryLog::make('SELECT * FROM users WHERE id = ? AND name = ?', [1, 'John']);

        $formatted = $log->toFormattedSql();

        $this->assertStringContainsString('1', $formatted);
        $this->assertStringContainsString("'John'", $formatted);
    }

    public function testQueryLogWithNullBinding(): void
    {
        $log = QueryLog::make('SELECT * FROM users WHERE name IS ?', [null]);

        $formatted = $log->toFormattedSql();

        $this->assertStringContainsString('NULL', $formatted);
    }

    public function testQueryLogSetFailed(): void
    {
        $log = QueryLog::make('SELECT * FROM users');
        $log->setFailed('Connection error');

        $this->assertFalse($log->success);
        $this->assertEquals('Connection error', $log->error);
    }

    public function testQueryLogToArray(): void
    {
        $log = QueryLog::make('SELECT * FROM users WHERE id = ?', [1], 5.5);

        $array = $log->toArray();

        $this->assertArrayHasKey('sql', $array);
        $this->assertArrayHasKey('bindings', $array);
        $this->assertArrayHasKey('time', $array);
        $this->assertArrayHasKey('type', $array);
        $this->assertEquals(5.5, $array['time']);
        $this->assertEquals('SELECT', $array['type']);
    }

    // QueryTimer 测试
    public function testQueryTimerStart(): void
    {
        $timer = QueryTimer::startNew();

        usleep(10000); // 10ms

        $elapsed = $timer->stop();

        $this->assertGreaterThan(0, $elapsed);
    }

    public function testQueryTimerElapsed(): void
    {
        $timer = new QueryTimer();

        usleep(5000); // 5ms

        $elapsed = $timer->getElapsed();

        $this->assertGreaterThan(0, $elapsed);
        $this->assertLessThan(100, $elapsed); // Should be less than 100ms
    }

    public function testQueryTimerMemoryUsage(): void
    {
        $timer = new QueryTimer();

        // 使用一些内存
        $data = str_repeat('x', 10000);
        unset($data);

        $memory = $timer->getMemoryUsage();

        $this->assertGreaterThanOrEqual(0, $memory);
    }

    public function testQueryTimerIsSlow(): void
    {
        $timer = new QueryTimer();
        $timer->stop();

        $this->assertFalse($timer->isSlow(100));
    }

    public function testQueryTimerFormattedMemory(): void
    {
        $timer = new QueryTimer();

        $formatted = $timer->getMemoryUsageFormatted();

        $this->assertMatchesRegularExpression('/^\d+(\.\d+)? (B|KB|MB)$/', $formatted);
    }

    // DatabaseDebugger 测试
    public function testDebuggerEnableDisable(): void
    {
        DatabaseDebugger::disable();

        $this->assertFalse(DatabaseDebugger::isEnabled());

        DatabaseDebugger::enable();

        $this->assertTrue(DatabaseDebugger::isEnabled());
    }

    public function testDebuggerLogQuery(): void
    {
        DatabaseDebugger::logQuery('SELECT * FROM users', [], 5.5);

        $queries = DatabaseDebugger::getQueries();

        $this->assertCount(1, $queries);
        $this->assertEquals('SELECT * FROM users', $queries[0]->sql);
        $this->assertEquals(5.5, $queries[0]->time);
    }

    public function testDebuggerLog(): void
    {
        $log = QueryLog::make('SELECT * FROM users', [], 3.2);
        DatabaseDebugger::log($log);

        $queries = DatabaseDebugger::getQueries();

        $this->assertCount(1, $queries);
        $this->assertEquals(3.2, $queries[0]->time);
    }

    public function testDebuggerClear(): void
    {
        DatabaseDebugger::logQuery('SELECT * FROM users');
        DatabaseDebugger::logQuery('SELECT * FROM posts');

        $this->assertEquals(2, DatabaseDebugger::getCount());

        DatabaseDebugger::clear();

        $this->assertEquals(0, DatabaseDebugger::getCount());
    }

    public function testDebuggerTotalTime(): void
    {
        DatabaseDebugger::logQuery('SELECT 1', [], 5.0);
        DatabaseDebugger::logQuery('SELECT 2', [], 3.0);

        $total = DatabaseDebugger::getTotalTime();

        $this->assertEquals(8.0, $total);
    }

    public function testDebuggerAverageTime(): void
    {
        DatabaseDebugger::logQuery('SELECT 1', [], 6.0);
        DatabaseDebugger::logQuery('SELECT 2', [], 4.0);

        $average = DatabaseDebugger::getAverageTime();

        $this->assertEquals(5.0, $average);
    }

    public function testDebuggerSlowestQuery(): void
    {
        DatabaseDebugger::logQuery('SELECT 1', [], 5.0);
        DatabaseDebugger::logQuery('SELECT 2', [], 10.0);
        DatabaseDebugger::logQuery('SELECT 3', [], 3.0);

        $slowest = DatabaseDebugger::getSlowestQuery();

        $this->assertEquals(10.0, $slowest->time);
    }

    public function testDebuggerSlowQueries(): void
    {
        DatabaseDebugger::setSlowQueryThreshold(5.0);

        DatabaseDebugger::logQuery('SELECT 1', [], 3.0);
        DatabaseDebugger::logQuery('SELECT 2', [], 6.0);
        DatabaseDebugger::logQuery('SELECT 3', [], 7.0);

        $slowQueries = DatabaseDebugger::getSlowQueries();

        $this->assertCount(2, $slowQueries);
    }

    public function testDebuggerGetTypeStats(): void
    {
        DatabaseDebugger::logQuery('SELECT * FROM users');
        DatabaseDebugger::logQuery('SELECT * FROM posts');
        DatabaseDebugger::logQuery('INSERT INTO users VALUES (1)');
        DatabaseDebugger::logQuery('UPDATE users SET name = "test"');

        $stats = DatabaseDebugger::getTypeStats();

        $this->assertArrayHasKey('SELECT', $stats);
        $this->assertArrayHasKey('INSERT', $stats);
        $this->assertArrayHasKey('UPDATE', $stats);
        $this->assertEquals(2, $stats['SELECT']['count']);
    }

    public function testDebuggerFailedQueries(): void
    {
        DatabaseDebugger::logQuery('SELECT * FROM users');
        $failed = QueryLog::make('SELECT * FROM posts')->setFailed('Table not found');
        DatabaseDebugger::log($failed);

        $failedQueries = DatabaseDebugger::getFailedQueries();

        $this->assertCount(1, $failedQueries);
        $this->assertEquals('Table not found', $failedQueries[0]->error);
    }

    public function testDebuggerHasFailedQueries(): void
    {
        DatabaseDebugger::logQuery('SELECT * FROM users');

        $this->assertFalse(DatabaseDebugger::hasFailedQueries());

        $failed = QueryLog::make('SELECT * FROM posts')->setFailed('Error');
        DatabaseDebugger::log($failed);

        $this->assertTrue(DatabaseDebugger::hasFailedQueries());
    }

    public function testDebuggerSetSlowQueryThreshold(): void
    {
        DatabaseDebugger::setSlowQueryThreshold(50.0);

        DatabaseDebugger::logQuery('SELECT 1', [], 30.0);
        DatabaseDebugger::logQuery('SELECT 2', [], 60.0);

        $slowQueries = DatabaseDebugger::getSlowQueries();

        $this->assertCount(1, $slowQueries);
    }

    public function testDebuggerToArray(): void
    {
        DatabaseDebugger::logQuery('SELECT * FROM users', [], 5.0);
        DatabaseDebugger::logQuery('INSERT INTO users VALUES (1)', [], 2.0);

        $array = DatabaseDebugger::toArray();

        $this->assertArrayHasKey('enabled', $array);
        $this->assertArrayHasKey('count', $array);
        $this->assertArrayHasKey('total_time', $array);
        $this->assertArrayHasKey('average_time', $array);
        $this->assertArrayHasKey('type_stats', $array);
        $this->assertEquals(2, $array['count']);
        $this->assertEquals(7.0, $array['total_time']);
    }

    public function testDebuggerGetReport(): void
    {
        DatabaseDebugger::logQuery('SELECT * FROM users', [], 5.0);
        DatabaseDebugger::logQuery('SELECT * FROM posts', [], 3.0);

        $report = DatabaseDebugger::getReport();

        $this->assertStringContainsString('Total Queries: 2', $report);
        $this->assertStringContainsString('Total Time:', $report);
        $this->assertStringContainsString('Average Time:', $report);
    }

    public function testDebuggerMaxQueriesLimit(): void
    {
        DatabaseDebugger::setMaxQueries(3);

        DatabaseDebugger::logQuery('SELECT 1');
        DatabaseDebugger::logQuery('SELECT 2');
        DatabaseDebugger::logQuery('SELECT 3');
        DatabaseDebugger::logQuery('SELECT 4');

        // 应该只保留最近的 3 个查询
        $this->assertEquals(3, DatabaseDebugger::getCount());
    }

    // 辅助函数测试
    public function testDbDebugHelper(): void
    {
        db_debug_enable();

        $this->assertTrue(\Bin\Database\Debug\DatabaseDebugger::isEnabled());
    }

    public function testDbQueriesHelper(): void
    {
        db_query_log('SELECT * FROM users');

        $queries = db_queries();

        $this->assertCount(1, $queries);
    }

    public function testDbQueryCountHelper(): void
    {
        db_query_log('SELECT 1');
        db_query_log('SELECT 2');

        $this->assertEquals(2, db_query_count());
    }

    public function testDbQueryTimeHelper(): void
    {
        db_query_log('SELECT 1', [], 5.0);
        db_query_log('SELECT 2', [], 3.0);

        $this->assertEquals(8.0, db_query_time());
    }

    public function testDbSlowQueriesHelper(): void
    {
        db_query_log('SELECT 1', [], 3.0);
        db_query_log('SELECT 2', [], 10.0);

        $slowQueries = db_slow_queries(5.0);

        $this->assertCount(1, $slowQueries);
    }
}
