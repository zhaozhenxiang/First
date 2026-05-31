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

    protected bool $isPartial = false;

    protected mixed $mockObject = null;

    public function __construct(string $class, array $methods = [], bool $isPartial = false)
    {
        $this->class = $class;
        $this->mockedMethods = $methods;
        $this->isPartial = $isPartial;
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
        $isPartial = $this->isPartial;

        // partialMock: 创建真实实例用于代理调用
        $realInstance = null;
        if ($isPartial) {
            try {
                $realInstance = new $className();
            } catch (\Throwable $e) {
                $realInstance = null;
            }
        }

        $this->mockObject = new class($mock, $className, $isPartial, $realInstance) {
            protected Mock $mockBuilder;
            protected string $originalClass;
            protected bool $isPartial;
            protected ?object $realInstance;

            public function __construct(Mock $mockBuilder, string $originalClass, bool $isPartial, ?object $realInstance)
            {
                $this->mockBuilder = $mockBuilder;
                $this->originalClass = $originalClass;
                $this->isPartial = $isPartial;
                $this->realInstance = $realInstance;
            }

            public function __call(string $method, array $args)
            {
                $this->mockBuilder->recordCall($method, $args);

                // 检查是否有期望
                $expectations = $this->mockBuilder->getExpectations($method);

                if (!empty($expectations)) {
                    $expectation = $expectations[0];

                    // 验证 with() 参数
                    $expectedArgs = $expectation->getWithArgs();
                    if (!empty($expectedArgs) && $expectedArgs !== $args) {
                        throw new Exception(
                            "Method {$method} called with unexpected arguments.\n" .
                            "Expected: " . json_encode($expectedArgs) . "\n" .
                            "Actual: " . json_encode($args)
                        );
                    }

                    if ($expectation->getReturnCallback() !== null) {
                        return call_user_func_array($expectation->getReturnCallback(), $args);
                    }

                    return $expectation->getReturnValue();
                }

                // partialMock: 代理到真实实例
                if ($this->isPartial && $this->realInstance !== null && method_exists($this->realInstance, $method)) {
                    return call_user_func_array([$this->realInstance, $method], $args);
                }

                // 如果忽略缺失方法，返回 null
                if ($this->mockBuilder->isIgnoringMissingMethods()) {
                    return null;
                }

                throw new Exception("Unexpected method call: {$method}");
            }
        };

        return $this->mockObject;
    }

    /**
     * 获取指定方法的期望列表
     */
    public function getExpectations(string $method): array
    {
        return $this->expectations[$method] ?? [];
    }

    /**
     * 是否忽略缺失方法
     */
    public function isIgnoringMissingMethods(): bool
    {
        return $this->ignoreMissing;
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
