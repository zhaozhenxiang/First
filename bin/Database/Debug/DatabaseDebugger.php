<?php

declare(strict_types=1);

namespace Bin\Database\Debug;

use Bin\Log\Logger;

/**
 * 数据库调试器
 *
 * 记录和分析所有数据库查询
 */
class DatabaseDebugger
{
    /**
     * 查询日志
     */
    private static array $queries = [];

    /**
     * 是否启用
     */
    private static bool $enabled = false;

    /**
     * 慢查询阈值（毫秒）
     */
    private static float $slowQueryThreshold = 100;

    /**
     * 最大记录数量
     */
    private static int $maxQueries = 1000;

    /**
     * 回调函数
     */
    private static $callback = null;

    /**
     * 启用调试
     */
    public static function enable(): void
    {
        self::$enabled = true;
        self::clear();
    }

    /**
     * 禁用调试
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
     * 记录查询
     */
    public static function log(QueryLog $query): void
    {
        if (!self::$enabled) {
            return;
        }

        // 限制记录数量
        if (count(self::$queries) >= self::$maxQueries) {
            array_shift(self::$queries);
        }

        self::$queries[] = $query;

        // 检查是否为慢查询
        if ($query->time > self::$slowQueryThreshold) {
            self::onSlowQuery($query);
        }

        // 触发回调
        if (self::$callback !== null) {
            call_user_func(self::$callback, $query);
        }
    }

    /**
     * 记录 SQL 查询（快捷方法）
     */
    public static function logQuery(
        string $sql,
        array $bindings = [],
        float $time = 0,
        string $connection = 'default'
    ): void {
        $query = QueryLog::make($sql, $bindings, $time, $connection);
        self::log($query);
    }

    /**
     * 获取所有查询
     */
    public static function getQueries(): array
    {
        return self::$queries;
    }

    /**
     * 获取查询数量
     */
    public static function getCount(): int
    {
        return count(self::$queries);
    }

    /**
     * 清除查询日志
     */
    public static function clear(): void
    {
        self::$queries = [];
    }

    /**
     * 获取总查询时间
     */
    public static function getTotalTime(): float
    {
        return array_sum(array_map(fn($q) => $q->time, self::$queries));
    }

    /**
     * 获取平均查询时间
     */
    public static function getAverageTime(): float
    {
        $count = count(self::$queries);

        if ($count === 0) {
            return 0;
        }

        return self::getTotalTime() / $count;
    }

    /**
     * 获取最慢的查询
     */
    public static function getSlowestQuery(): ?QueryLog
    {
        if (empty(self::$queries)) {
            return null;
        }

        usort(self::$queries, fn($a, $b) => $b->time <=> $a->time);

        return self::$queries[0];
    }

    /**
     * 获取慢查询列表
     */
    public static function getSlowQueries(?float $threshold = null): array
    {
        $threshold = $threshold ?? self::$slowQueryThreshold;

        return array_values(array_filter(self::$queries, fn($q) => $q->time >= $threshold));
    }

    /**
     * 获取查询类型统计
     */
    public static function getTypeStats(): array
    {
        $stats = [];

        foreach (self::$queries as $query) {
            $type = $query->getType();

            if (!isset($stats[$type])) {
                $stats[$type] = [
                    'count' => 0,
                    'total_time' => 0,
                    'avg_time' => 0,
                ];
            }

            $stats[$type]['count']++;
            $stats[$type]['total_time'] += $query->time;
        }

        // 计算平均时间
        foreach ($stats as $type => &$data) {
            $data['avg_time'] = $data['count'] > 0
                ? round($data['total_time'] / $data['count'], 2)
                : 0;
        }

        return $stats;
    }

    /**
     * 获取失败的查询
     */
    public static function getFailedQueries(): array
    {
        return array_values(array_filter(self::$queries, fn($q) => !$q->success));
    }

    /**
     * 检查是否有失败的查询
     */
    public static function hasFailedQueries(): bool
    {
        return !empty(self::getFailedQueries());
    }

    /**
     * 设置慢查询阈值
     */
    public static function setSlowQueryThreshold(float $milliseconds): void
    {
        self::$slowQueryThreshold = $milliseconds;
    }

    /**
     * 设置最大记录数量
     */
    public static function setMaxQueries(int $max): void
    {
        self::$maxQueries = $max;
    }

    /**
     * 设置回调函数
     */
    public static function setCallback($callback): void
    {
        self::$callback = $callback;
    }

    /**
     * 慢查询回调
     */
    protected static function onSlowQuery(QueryLog $query): void
    {
        // 记录到日志
        if (class_exists(Logger::class)) {
            $logger = new Logger('database');
            $logger->warning('Slow query detected', [
                'sql' => $query->toFormattedSql(),
                'time' => $query->time . 'ms',
            ]);
        }
    }

    /**
     * 导出为 JSON
     */
    public static function toJson(): string
    {
        $data = [
            'enabled' => self::$enabled,
            'count' => self::getCount(),
            'total_time' => self::getTotalTime(),
            'average_time' => self::getAverageTime(),
            'type_stats' => self::getTypeStats(),
            'queries' => array_map(fn($q) => $q->toArray(), self::$queries),
        ];

        return json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    }

    /**
     * 导出为数组
     */
    public static function toArray(): array
    {
        return [
            'enabled' => self::$enabled,
            'count' => self::getCount(),
            'total_time' => self::getTotalTime(),
            'average_time' => self::getAverageTime(),
            'slow_queries' => count(self::getSlowQueries()),
            'failed_queries' => count(self::getFailedQueries()),
            'type_stats' => self::getTypeStats(),
            'queries' => array_map(fn($q) => $q->toArray(), self::$queries),
        ];
    }

    /**
     * 打印查询摘要
     */
    public static function printSummary(): void
    {
        echo "\n=== Database Query Summary ===\n";
        echo "Total Queries: " . self::getCount() . "\n";
        echo "Total Time: " . round(self::getTotalTime(), 2) . "ms\n";
        echo "Average Time: " . round(self::getAverageTime(), 2) . "ms\n";

        $slowQueries = self::getSlowQueries();
        if (!empty($slowQueries)) {
            echo "Slow Queries: " . count($slowQueries) . "\n";
        }

        $failedQueries = self::getFailedQueries();
        if (!empty($failedQueries)) {
            echo "Failed Queries: " . count($failedQueries) . "\n";
        }

        echo "\n--- Query Types ---\n";

        foreach (self::getTypeStats() as $type => $stats) {
            echo "{$type}: {$stats['count']} (avg: {$stats['avg_time']}ms)\n";
        }

        echo "\n";
    }

    /**
     * 获取格式化的报告
     */
    public static function getReport(): string
    {
        $report = "=== Database Query Report ===\n\n";

        $report .= "Summary:\n";
        $report .= "  Total Queries: " . self::getCount() . "\n";
        $report .= "  Total Time: " . round(self::getTotalTime(), 2) . "ms\n";
        $report .= "  Average Time: " . round(self::getAverageTime(), 2) . "ms\n";

        $slowQueries = self::getSlowQueries();
        if (!empty($slowQueries)) {
            $report .= "  Slow Queries: " . count($slowQueries) . "\n";
        }

        $failedQueries = self::getFailedQueries();
        if (!empty($failedQueries)) {
            $report .= "  Failed Queries: " . count($failedQueries) . "\n";
        }

        $report .= "\nQuery Types:\n";

        foreach (self::getTypeStats() as $type => $stats) {
            $report .= "  {$type}: {$stats['count']} (avg: {$stats['avg_time']}ms)\n";
        }

        // 显示最慢的 5 个查询
        $sorted = self::$queries;
        usort($sorted, fn($a, $b) => $b->time <=> $a->time);
        $top5 = array_slice($sorted, 0, 5);

        if (!empty($top5)) {
            $report .= "\nTop 5 Slowest Queries:\n";

            foreach ($top5 as $i => $query) {
                $report .= "  " . ($i + 1) . ". {$query->time}ms - {$query->getType()}\n";
                $report .= "     {$query->toFormattedSql()}\n";

                if ($query->error !== null) {
                    $report .= "     ERROR: {$query->error}\n";
                }

                $report .= "\n";
            }
        }

        return $report;
    }
}
