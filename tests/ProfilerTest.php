<?php

declare(strict_types=1);

namespace Tests;

use Bin\Testing\TestCase;
use Bin\Profiler\Profiler;

/**
 * 性能分析器测试
 */
class ProfilerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Profiler::enable();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Profiler::disable();
        Profiler::clear();
    }

    public function testProfilerEnableDisable(): void
    {
        $this->assertTrue(Profiler::isEnabled());

        Profiler::disable();

        $this->assertFalse(Profiler::isEnabled());
    }

    public function testProfilerStart(): void
    {
        // enable() 已经调用了 start()，所以这里不需要再次调用
        usleep(1000); // 1ms 延迟确保有时间经过

        $this->assertGreaterThan(0, Profiler::getElapsed());
    }

    public function testProfilerGetElapsed(): void
    {
        Profiler::start();

        usleep(5000); // 5ms

        $elapsed = Profiler::getElapsed();

        $this->assertGreaterThan(4, $elapsed);
        $this->assertLessThan(20, $elapsed);
    }

    public function testProfilerGetMemoryUsage(): void
    {
        Profiler::start();

        $memory = Profiler::getMemoryUsage();

        $this->assertGreaterThanOrEqual(0, $memory);
    }

    public function testProfilerGetMemoryPeak(): void
    {
        $peak = Profiler::getMemoryPeak();

        $this->assertGreaterThan(0, $peak);
    }

    public function testProfilerFormatBytes(): void
    {
        $this->assertEquals('1 KB', Profiler::formatBytes(1024, 0));
        $this->assertStringContainsString('KB', Profiler::formatBytes(1024));
        $this->assertStringContainsString('KB', Profiler::formatBytes(2048));
        $this->assertStringContainsString('MB', Profiler::formatBytes(1024 * 1024));
    }

    public function testProfilerCheckpoint(): void
    {
        Profiler::checkpoint('test');

        $checkpoints = Profiler::getCheckpoints();

        $this->assertArrayHasKey('test', $checkpoints);
        $this->assertArrayHasKey('time', $checkpoints['test']);
        $this->assertArrayHasKey('memory', $checkpoints['test']);
    }

    public function testProfilerMultipleCheckpoints(): void
    {
        Profiler::checkpoint('first');
        usleep(2000);
        Profiler::checkpoint('second');

        $checkpoints = Profiler::getCheckpoints();

        $this->assertCount(2, $checkpoints);
        $this->assertGreaterThan($checkpoints['first']['time'], $checkpoints['second']['time']);
    }

    public function testProfilerRecord(): void
    {
        Profiler::record('custom_metric', 42.5, 1024);

        $data = Profiler::getData();

        $this->assertArrayHasKey('custom_metric', $data);
        $this->assertEquals(42.5, $data['custom_metric']['value']);
        $this->assertEquals(1024, $data['custom_metric']['memory']);
    }

    public function testProfilerRecordQuery(): void
    {
        Profiler::recordQuery('SELECT * FROM users', 5.5, 10);

        $data = Profiler::getData();

        $this->assertArrayHasKey('queries', $data);
        $this->assertEquals(1, $data['queries']['count']);
        $this->assertEquals(5.5, $data['queries']['total_time']);
        $this->assertEquals(10, $data['queries']['total_rows']);
    }

    public function testProfilerRecordMultipleQueries(): void
    {
        Profiler::recordQuery('SELECT * FROM users', 5.0, 10);
        Profiler::recordQuery('SELECT * FROM posts', 3.0, 5);

        $data = Profiler::getData();

        $this->assertEquals(2, $data['queries']['count']);
        $this->assertEquals(8.0, $data['queries']['total_time']);
        $this->assertEquals(15, $data['queries']['total_rows']);
    }

    public function testProfilerGetReport(): void
    {
        Profiler::checkpoint('start');
        Profiler::recordQuery('SELECT 1', 2.5);

        $report = Profiler::getReport();

        $this->assertArrayHasKey('elapsed', $report);
        $this->assertArrayHasKey('memory_usage', $report);
        $this->assertArrayHasKey('memory_peak', $report);
        $this->assertArrayHasKey('checkpoints', $report);
        $this->assertArrayHasKey('queries', $report);
    }

    public function testProfilerPrintSummary(): void
    {
        ob_start();
        Profiler::printSummary();
        $output = ob_get_clean();

        $this->assertStringContainsString('Performance Summary', $output);
        $this->assertStringContainsString('Elapsed Time:', $output);
        $this->assertStringContainsString('Memory Usage:', $output);
    }

    public function testProfilerClear(): void
    {
        Profiler::record('test', 100);
        Profiler::checkpoint('test');

        $this->assertNotEmpty(Profiler::getData());
        $this->assertNotEmpty(Profiler::getCheckpoints());

        Profiler::clear();

        $this->assertEmpty(Profiler::getData());
        $this->assertEmpty(Profiler::getCheckpoints());
    }

    public function testProfilerToJson(): void
    {
        Profiler::checkpoint('test');

        $json = Profiler::toJson();

        $this->assertStringContainsString('elapsed', $json);
        $this->assertStringContainsString('checkpoints', $json);
    }

    public function testProfilerDisabledOperations(): void
    {
        Profiler::disable();

        Profiler::record('test', 100);
        Profiler::checkpoint('test');

        $this->assertEmpty(Profiler::getData());
        $this->assertEmpty(Profiler::getCheckpoints());
    }
}
