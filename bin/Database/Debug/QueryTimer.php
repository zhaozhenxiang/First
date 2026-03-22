<?php

declare(strict_types=1);

namespace Bin\Database\Debug;

/**
 * 查询计时器
 */
class QueryTimer
{
    /**
     * 查询开始时间
     */
    private float $startTime;

    /**
     * 查询结束时间
     */
    private float $endTime;

    /**
     * 查询内存使用
     */
    private int $startMemory;

    /**
     * 是否已开始
     */
    private bool $started = false;

    /**
     * 是否已结束
     */
    private bool $ended = false;

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->start();
    }

    /**
     * 开始计时
     */
    public function start(): void
    {
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage();
        $this->started = true;
        $this->ended = false;
    }

    /**
     * 结束计时
     */
    public function stop(): float
    {
        if (!$this->started) {
            return 0;
        }

        $this->endTime = microtime(true);
        $this->ended = true;

        return $this->getElapsed();
    }

    /**
     * 获取经过时间（毫秒）
     */
    public function getElapsed(): float
    {
        if (!$this->started) {
            return 0;
        }

        $end = $this->ended ? $this->endTime : microtime(true);

        return round(($end - $this->startTime) * 1000, 2);
    }

    /**
     * 获取内存使用（字节）
     */
    public function getMemoryUsage(): int
    {
        $endMemory = memory_get_usage();

        return max(0, $endMemory - $this->startMemory);
    }

    /**
     * 获取内存使用（可读格式）
     */
    public function getMemoryUsageFormatted(): string
    {
        $bytes = $this->getMemoryUsage();

        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        $kb = $bytes / 1024;
        if ($kb < 1024) {
            return round($kb, 2) . ' KB';
        }

        $mb = $kb / 1024;
        return round($mb, 2) . ' MB';
    }

    /**
     * 检查是否为慢查询
     */
    public function isSlow(float $threshold = 100): bool
    {
        return $this->getElapsed() > $threshold;
    }

    /**
     * 创建新的计时器
     */
    public static function startNew(): self
    {
        return new self();
    }
}
