<?php

declare(strict_types=1);

namespace Bin\Testing;

/**
 * 测试结果
 */
class TestResult
{
    protected string $testClass;

    protected string $testMethod;

    protected bool $passed = false;

    protected ?string $failureMessage = null;

    protected ?string $file = null;

    protected ?int $line = null;

    protected ?string $errorType = null;

    protected float $duration = 0;

    protected ?float $startTime = null;

    public function __construct(string $testClass, string $testMethod)
    {
        $this->testClass = $testClass;
        $this->testMethod = $testMethod;
    }

    /**
     * 开始计时
     */
    public function start(): void
    {
        $this->startTime = microtime(true);
    }

    /**
     * 结束计时
     */
    public function stop(): void
    {
        if ($this->startTime !== null) {
            $this->duration = microtime(true) - $this->startTime;
        }
    }

    /**
     * 设置通过
     */
    public function setPassed(bool $passed): void
    {
        $this->passed = $passed;
    }

    /**
     * 设置失败
     */
    public function setFailed(string $message, ?string $file = null, ?int $line = null): void
    {
        $this->passed = false;
        $this->failureMessage = $message;
        $this->file = $file;
        $this->line = $line;
        $this->errorType = 'failure';
    }

    /**
     * 设置错误
     */
    public function setError(string $message, ?string $file = null, ?int $line = null): void
    {
        $this->passed = false;
        $this->failureMessage = $message;
        $this->file = $file;
        $this->line = $line;
        $this->errorType = 'error';
    }

    /**
     * 是否通过
     */
    public function isPassed(): bool
    {
        return $this->passed;
    }

    /**
     * 是否失败
     */
    public function isFailure(): bool
    {
        return $this->errorType === 'failure';
    }

    /**
     * 是否错误
     */
    public function isError(): bool
    {
        return $this->errorType === 'error';
    }

    /**
     * 获取测试类名
     */
    public function getTestClass(): string
    {
        return $this->testClass;
    }

    /**
     * 获取测试方法名
     */
    public function getTestMethod(): string
    {
        return $this->testMethod;
    }

    /**
     * 获取测试名称
     */
    public function getTestName(): string
    {
        return $this->testClass . '::' . $this->testMethod . '()';
    }

    /**
     * 获取失败消息
     */
    public function getFailureMessage(): ?string
    {
        return $this->failureMessage;
    }

    /**
     * 获取文件
     */
    public function getFile(): ?string
    {
        return $this->file;
    }

    /**
     * 获取行号
     */
    public function getLine(): ?int
    {
        return $this->line;
    }

    /**
     * 获取错误类型
     */
    public function getErrorType(): ?string
    {
        return $this->errorType;
    }

    /**
     * 获取持续时间
     */
    public function getDuration(): float
    {
        return $this->duration;
    }
}
