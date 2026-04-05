<?php

declare(strict_types=1);

if (!function_exists('db_debug')) {
    /**
     * 获取数据库调试器实例
     */
    function db_debug(): \Bin\Database\Debug\DatabaseDebugger
    {
        static $debugger = null;

        if ($debugger === null) {
            $debugger = new \Bin\Database\Debug\DatabaseDebugger();
        }

        return $debugger;
    }
}

if (!function_exists('db_debug_enable')) {
    /**
     * 启用数据库调试
     */
    function db_debug_enable(): void
    {
        \Bin\Database\Debug\DatabaseDebugger::enable();
    }
}

if (!function_exists('db_debug_disable')) {
    /**
     * 禁用数据库调试
     */
    function db_debug_disable(): void
    {
        \Bin\Database\Debug\DatabaseDebugger::disable();
    }
}

if (!function_exists('db_queries')) {
    /**
     * 获取所有查询日志
     */
    function db_queries(): array
    {
        return \Bin\Database\Debug\DatabaseDebugger::getQueries();
    }
}

if (!function_exists('db_query_log')) {
    /**
     * 记录数据库查询
     */
    function db_query_log(
        string $sql,
        array $bindings = [],
        float $time = 0,
        string $connection = 'default'
    ): void {
        \Bin\Database\Debug\DatabaseDebugger::logQuery($sql, $bindings, $time, $connection);
    }
}

if (!function_exists('db_query_count')) {
    /**
     * 获取查询数量
     */
    function db_query_count(): int
    {
        return \Bin\Database\Debug\DatabaseDebugger::getCount();
    }
}

if (!function_exists('db_query_time')) {
    /**
     * 获取总查询时间
     */
    function db_query_time(): float
    {
        return \Bin\Database\Debug\DatabaseDebugger::getTotalTime();
    }
}

if (!function_exists('db_slow_queries')) {
    /**
     * 获取慢查询列表
     */
    function db_slow_queries(?float $threshold = null): array
    {
        return \Bin\Database\Debug\DatabaseDebugger::getSlowQueries($threshold);
    }
}

if (!function_exists('db_query_report')) {
    /**
     * 获取查询报告
     */
    function db_query_report(): string
    {
        return \Bin\Database\Debug\DatabaseDebugger::getReport();
    }
}

if (!function_exists('db_query_summary')) {
    /**
     * 打印查询摘要
     */
    function db_query_summary(): void
    {
        \Bin\Database\Debug\DatabaseDebugger::printSummary();
    }
}

if (!function_exists('profiler')) {
    /**
     * 获取性能分析器实例
     */
    function profiler(): \Bin\Profiler\Profiler
    {
        static $profiler = null;

        if ($profiler === null) {
            $profiler = new \Bin\Profiler\Profiler();
        }

        return $profiler;
    }
}

if (!function_exists('profiler_enable')) {
    /**
     * 启用性能分析
     */
    function profiler_enable(): void
    {
        \Bin\Profiler\Profiler::enable();
    }
}

if (!function_exists('profiler_disable')) {
    /**
     * 禁用性能分析
     */
    function profiler_disable(): void
    {
        \Bin\Profiler\Profiler::disable();
    }
}

if (!function_exists('profiler_checkpoint')) {
    /**
     * 记录性能测量点
     */
    function profiler_checkpoint(string $name): void
    {
        \Bin\Profiler\Profiler::checkpoint($name);
    }
}

if (!function_exists('profiler_record')) {
    /**
     * 记录性能数据
     */
    function profiler_record(string $key, float $value, ?int $memory = null): void
    {
        \Bin\Profiler\Profiler::record($key, $value, $memory);
    }
}

if (!function_exists('profiler_get_elapsed')) {
    /**
     * 获取经过时间（毫秒）
     */
    function profiler_get_elapsed(): float
    {
        return \Bin\Profiler\Profiler::getElapsed();
    }
}

if (!function_exists('profiler_get_memory')) {
    /**
     * 获取内存使用（字节）
     */
    function profiler_get_memory(): int
    {
        return \Bin\Profiler\Profiler::getMemoryUsage();
    }
}

if (!function_exists('profiler_get_memory_peak')) {
    /**
     * 获取内存峰值（字节）
     */
    function profiler_get_memory_peak(): int
    {
        return \Bin\Profiler\Profiler::getMemoryPeak();
    }
}

if (!function_exists('profiler_report')) {
    /**
     * 获取性能报告
     */
    function profiler_report(): array
    {
        return \Bin\Profiler\Profiler::getReport();
    }
}

if (!function_exists('profiler_print')) {
    /**
     * 打印性能摘要
     */
    function profiler_print(): void
    {
        \Bin\Profiler\Profiler::printSummary();
    }
}
