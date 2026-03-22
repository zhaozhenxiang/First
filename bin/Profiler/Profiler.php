<?php

declare(strict_types=1);

namespace Bin\Profiler;

/**
 * 性能分析器
 *
 * 记录和分析应用程序性能数据
 */
class Profiler
{
    /**
     * 分析数据
     */
    private static array $data = [];

    /**
     * 开始时间
     */
    private static float $startTime;

    /**
     * 开始内存
     */
    private static int $startMemory;

    /**
     * 是否启用
     */
    private static bool $enabled = false;

    /**
     * 测量点
     */
    private static array $checkpoints = [];

    /**
     * 启用性能分析
     */
    public static function enable(): void
    {
        self::$enabled = true;
        self::$data = [];
        self::$checkpoints = [];
        self::start();
    }

    /**
     * 禁用性能分析
     */
    public static function disable(): void
    {
        self::$enabled = false;
    }

    /**
     * 检查是否启用
     */
    public static function isEnabled(): bool
    {
        return self::$enabled;
    }

    /**
     * 开始分析
     */
    public static function start(): void
    {
        self::$startTime = microtime(true);
        self::$startMemory = memory_get_usage();
    }

    /**
     * 停止分析
     */
    public static function stop(): void
    {
        self::record('total', self::getElapsed(), self::getMemoryUsage());
    }

    /**
     * 记录测量点
     */
    public static function checkpoint(string $name): void
    {
        if (!self::$enabled) {
            return;
        }

        $now = microtime(true);
        $elapsed = ($now - self::$startTime) * 1000;

        self::$checkpoints[$name] = [
            'time' => $elapsed,
            'memory' => memory_get_usage(),
            'memory_delta' => memory_get_usage() - self::$startMemory,
        ];
    }

    /**
     * 记录数据
     */
    public static function record(string $key, float $value, ?int $memory = null): void
    {
        if (!self::$enabled) {
            return;
        }

        self::$data[$key] = [
            'value' => $value,
            'memory' => $memory,
            'time' => microtime(true),
        ];
    }

    /**
     * 记录查询
     */
    public static function recordQuery(string $sql, float $time, int $rows = 0): void
    {
        if (!self::$enabled) {
            return;
        }

        if (!isset(self::$data['queries'])) {
            self::$data['queries'] = [
                'count' => 0,
                'total_time' => 0,
                'total_rows' => 0,
                'queries' => [],
            ];
        }

        self::$data['queries']['count']++;
        self::$data['queries']['total_time'] += $time;
        self::$data['queries']['total_rows'] += $rows;

        self::$data['queries']['queries'][] = [
            'sql' => $sql,
            'time' => $time,
            'rows' => $rows,
        ];
    }

    /**
     * 获取经过时间（毫秒）
     */
    public static function getElapsed(): float
    {
        return (microtime(true) - self::$startTime) * 1000;
    }

    /**
     * 获取内存使用（字节）
     */
    public static function getMemoryUsage(): int
    {
        return memory_get_usage() - self::$startMemory;
    }

    /**
     * 获取内存峰值（字节）
     */
    public static function getMemoryPeak(): int
    {
        return memory_get_peak_usage();
    }

    /**
     * 获取内存峰值（可读格式）
     */
    public static function getMemoryPeakFormatted(): string
    {
        return self::formatBytes(self::getMemoryPeak());
    }

    /**
     * 获取当前内存使用（可读格式）
     */
    public static function getMemoryUsageFormatted(): string
    {
        return self::formatBytes(self::getMemoryUsage());
    }

    /**
     * 格式化字节数
     */
    public static function formatBytes(int $bytes, int $precision = 2): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        $kb = $bytes / 1024;
        if ($kb < 1024) {
            return round($kb, $precision) . ' KB';
        }

        $mb = $kb / 1024;
        if ($mb < 1024) {
            return round($mb, $precision) . ' MB';
        }

        $gb = $mb / 1024;
        return round($gb, $precision) . ' GB';
    }

    /**
     * 获取所有数据
     */
    public static function getData(): array
    {
        return self::$data;
    }

    /**
     * 获取测量点
     */
    public static function getCheckpoints(): array
    {
        return self::$checkpoints;
    }

    /**
     * 获取性能报告
     */
    public static function getReport(): array
    {
        $elapsed = self::getElapsed();
        $memory = self::getMemoryUsage();
        $peak = self::getMemoryPeak();

        return [
            'elapsed' => round($elapsed, 2),
            'memory_usage' => $memory,
            'memory_usage_formatted' => self::formatBytes($memory),
            'memory_peak' => $peak,
            'memory_peak_formatted' => self::formatBytes($peak),
            'checkpoints' => self::$checkpoints,
            'queries' => self::$data['queries'] ?? null,
            'data' => self::$data,
        ];
    }

    /**
     * 打印性能摘要
     */
    public static function printSummary(): void
    {
        $report = self::getReport();

        echo "\n=== Performance Summary ===\n";
        echo "Elapsed Time: {$report['elapsed']}ms\n";
        echo "Memory Usage: {$report['memory_usage_formatted']}\n";
        echo "Memory Peak: {$report['memory_peak_formatted']}\n";

        if (!empty($report['checkpoints'])) {
            echo "\n--- Checkpoints ---\n";

            foreach ($report['checkpoints'] as $name => $data) {
                echo "{$name}: {$data['time']}ms ({$data['memory_delta']} bytes)\n";
            }
        }

        if (isset($report['queries'])) {
            $queries = $report['queries'];
            echo "\n--- Queries ---\n";
            echo "Count: {$queries['count']}\n";
            echo "Total Time: {$queries['total_time']}ms\n";
            echo "Total Rows: {$queries['total_rows']}\n";
        }

        echo "\n";
    }

    /**
     * 导出为 JSON
     */
    public static function toJson(): string
    {
        return json_encode(self::getReport(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    }

    /**
     * 清除数据
     */
    public static function clear(): void
    {
        self::$data = [];
        self::$checkpoints = [];
    }
}
