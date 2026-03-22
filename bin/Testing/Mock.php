<?php

declare(strict_types=1);

namespace Bin\Testing;

use Closure;
use Exception;

/**
 * 模拟对象
 */
class Mock
{
    protected string $class;

    protected array $mockedMethods = [];

    protected array $expectations = [];

    protected array $methodCalls = [];

    protected bool $ignoreMissing = false;

    protected mixed $mockObject = null;

    public function __construct(string $class, array $methods = [])
    {
        $this->class = $class;
        $this->mockedMethods = $methods;
    }

    /**
     * 期望方法被调用
     */
    public function expects(string $method): MethodExpectation
    {
        $expectation = new MethodExpectation($this, $method);

        $this->expectations[$method][] = $expectation;

        return $expectation;
    }

    /**
     * 设置方法返回值
     */
    public function allows(string $method): MethodExpectation
    {
        $expectation = new MethodExpectation($this, $method);
        $expectation->atLeast()->once();

        $this->expectations[$method][] = $expectation;

        return $expectation;
    }

    /**
     * 忽略缺失的方法
     */
    public function shouldIgnoreMissing(): self
    {
        $this->ignoreMissing = true;
        return $this;
    }

    /**
     * 记录方法调用
     */
    public function recordCall(string $method, array $args = []): void
    {
        if (!isset($this->methodCalls[$method])) {
            $this->methodCalls[$method] = [];
        }

        $this->methodCalls[$method][] = [
            'args' => $args,
            'time' => microtime(true),
        ];
    }

    /**
     * 获取方法调用次数
     */
    public function getCallsCount(string $method): int
    {
        return isset($this->methodCalls[$method]) ? count($this->methodCalls[$method]) : 0;
    }

    /**
     * 验证期望
     */
    public function verify(): void
    {
        foreach ($this->expectations as $method => $expectations) {
            foreach ($expectations as $expectation) {
                $expectation->verify();
            }
        }
    }

    /**
     * 创建模拟对象实例
     */
    public function make(): object
    {
        if ($this->mockObject !== null) {
            return $this->mockObject;
        }

        return $this->createMockInstance();
    }

    /**
     * 创建模拟实例
     */
    protected function createMockInstance(): object
    {
        $mock = $this;

        $className = $this->class;

        // 使用匿名类创建模拟对象
        $this->mockObject = new class($mock, $className) {
            protected Mock $mockBuilder;

            protected string $originalClass;

            public function __construct(Mock $mockBuilder, string $originalClass)
            {
                $this->mockBuilder = $mockBuilder;
                $this->originalClass = $originalClass;
            }

            public function __call(string $method, array $args)
            {
                $this->mockBuilder->recordCall($method, $args);

                // 检查是否有期望
                $expectations = $this->mockBuilder->expectations[$method] ?? [];

                if (!empty($expectations)) {
                    $expectation = $expectations[0];

                    if ($expectation->getReturnCallback() !== null) {
                        return call_user_func_array($expectation->getReturnCallback(), $args);
                    }

                    return $expectation->getReturnValue();
                }

                // 如果忽略缺失方法，返回 null
                if ($this->mockBuilder->ignoreMissing) {
                    return null;
                }

                throw new Exception("Unexpected method call: {$method}");
            }
        };

        return $this->mockObject;
    }

    /**
     * 魔术方法 - 允许链式调用
     */
    public function __call(string $method, array $args)
    {
        return $this->allows($method);
    }

    /**
     * 获取类名
     */
    public function getClass(): string
    {
        return $this->class;
    }
}
