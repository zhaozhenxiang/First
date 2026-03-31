<?php

declare(strict_types=1);

namespace Bin\Testing;

use Closure;

/**
 * 方法期望
 */
class MethodExpectation
{
    protected Mock $mock;

    protected string $method;

    protected int|null $times = null;

    protected mixed $returnValue = null;

    protected ?Closure $returnCallback = null;

    protected array $withArgs = [];

    protected string $operator = '=';

    protected bool $verified = false;

    public function __construct(Mock $mock, string $method)
    {
        $this->mock = $mock;
        $this->method = $method;
    }

    /**
     * 期望被调用一次
     */
    public function once(): self
    {
        return $this->times(1);
    }

    /**
     * 期望被调用两次
     */
    public function twice(): self
    {
        return $this->times(2);
    }

    /**
     * 期望被调用指定次数
     */
    public function times(int $times): self
    {
        $this->times = $times;
        return $this;
    }

    /**
     * 至少调用一次
     */
    public function atLeast(): self
    {
        $this->operator = '>=';
        $this->times = 1;
        return $this;
    }

    /**
     * 最多调用一次
     */
    public function atMost(): self
    {
        $this->operator = '<=';
        $this->times = 1;
        return $this;
    }

    /**
     * 设置返回值
     */
    public function andReturn(mixed $value): self
    {
        $this->returnValue = $value;
        return $this;
    }

    /**
     * 设置返回回调
     */
    public function andReturnReturn(Closure $callback): self
    {
        $this->returnCallback = $callback;
        return $this;
    }

    /**
     * 期望指定参数
     */
    public function with(...$args): self
    {
        $this->withArgs = $args;
        return $this;
    }

    /**
     * 验证期望
     */
    public function verify(): void
    {
        if ($this->verified) {
            return;
        }

        $this->verified = true;

        $actualCalls = $this->mock->getCallsCount($this->method);

        $expected = $this->times;

        $passed = match ($this->operator) {
            '>=' => $actualCalls >= $expected,
            '<=' => $actualCalls <= $expected,
            default => $actualCalls === $expected,
        };

        if (!$passed) {
            throw new \Exception(
                "Method {$this->method} was expected to be called " .
                $this->getExpectedTimesMessage() .
                " but was called {$actualCalls} time(s)."
            );
        }
    }

    /**
     * 获取期望次数消息
     */
    protected function getExpectedTimesMessage(): string
    {
        return match ($this->operator) {
            '>=' => "at least {$this->times} time(s)",
            '<=' => "at most {$this->times} time(s)",
            default => "exactly {$this->times} time(s)",
        };
    }

    /**
     * 获取返回值
     */
    public function getReturnValue(): mixed
    {
        return $this->returnValue;
    }

    /**
     * 获取返回回调
     */
    public function getReturnCallback(): ?Closure
    {
        return $this->returnCallback;
    }

    /**
     * 获取方法名
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * 获取 with() 期望参数
     */
    public function getWithArgs(): array
    {
        return $this->withArgs;
    }
}
