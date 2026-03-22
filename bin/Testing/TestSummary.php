<?php

declare(strict_types=1);

namespace Bin\Testing;

/**
 * 测试摘要
 */
class TestSummary
{
    public function __construct(
        protected int $passed,
        protected int $failed,
        protected int $errors,
        protected int $skipped,
        protected int $incomplete,
        protected float $duration,
        protected array $results = []
    ) {
    }

    /**
     * 是否全部通过
     */
    public function isSuccessful(): bool
    {
        return $this->failed === 0 && $this->errors === 0;
    }

    /**
     * 获取通过数量
     */
    public function getPassedCount(): int
    {
        return $this->passed;
    }

    /**
     * 获取失败数量
     */
    public function getFailedCount(): int
    {
        return $this->failed;
    }

    /**
     * 获取错误数量
     */
    public function getErrorsCount(): int
    {
        return $this->errors;
    }

    /**
     * 获取跳过数量
     */
    public function getSkippedCount(): int
    {
        return $this->skipped;
    }

    /**
     * 获取不完整数量
     */
    public function getIncompleteCount(): int
    {
        return $this->incomplete;
    }

    /**
     * 获取总数量
     */
    public function getTotalCount(): int
    {
        return $this->passed + $this->failed + $this->errors + $this->skipped + $this->incomplete;
    }

    /**
     * 获取持续时间
     */
    public function getDuration(): float
    {
        return $this->duration;
    }

    /**
     * 获取结果
     */
    public function getResults(): array
    {
        return $this->results;
    }

    /**
     * 获取失败的结果
     */
    public function getFailures(): array
    {
        return array_filter($this->results, fn($r) => $r->isFailure());
    }

    /**
     * 获取错误的结果
     */
    public function getErrors(): array
    {
        return array_filter($this->results, fn($r) => $r->isError());
    }

    /**
     * 输出摘要
     */
    public function output(): void
    {
        echo "\n\n";

        $failures = $this->getFailures();
        $errors = $this->getErrors();

        foreach ($failures as $result) {
            echo "\n1) {$result->getTestName()}\n";
            echo "   " . str_replace("\n", "\n   ", $result->getFailureMessage()) . "\n";
            if ($result->getFile()) {
                echo "   at {$result->getFile()}:{$result->getLine()}\n";
            }
        }

        foreach ($errors as $result) {
            echo "\n1) {$result->getTestName()}\n";
            echo "   {$result->getFailureMessage()}\n";
            if ($result->getFile()) {
                echo "   at {$result->getFile()}:{$result->getLine()}\n";
            }
        }

        echo "\n";
        echo $this->getSummaryString();
        echo "\n";
    }

    /**
     * 获取摘要字符串
     */
    public function getSummaryString(): string
    {
        $total = $this->getTotalCount();
        $passed = $this->passed;
        $failed = $this->failed + $this->errors;

        $output = "Tests:  {$total}, ";

        if ($failed > 0) {
            $output .= "✗ {$failed} failed, ";
        }

        if ($this->skipped > 0) {
            $output .= "S {$this->skipped} skipped, ";
        }

        if ($this->incomplete > 0) {
            $output .= "I {$this->incomplete} incomplete, ";
        }

        $output .= "✓ {$passed} passed";

        $output .= " in " . number_format($this->duration * 1000, 2) . 'ms';

        return $output;
    }

    /**
     * 获取退出码
     */
    public function getExitCode(): int
    {
        return $this->isSuccessful() ? 0 : 1;
    }
}
