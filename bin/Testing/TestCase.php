<?php

declare(strict_types=1);

namespace Bin\Testing;

use Bin\Database\Schema\Schema;
use Bin\Database\Migrations\Migrator;
use Throwable;

/**
 * 测试用例基类
 */
abstract class TestCase
{
    protected bool $setUpHasRun = false;

    protected bool $tearDownHasRun = false;

    protected array $beforeEachCallbacks = [];

    protected array $afterEachCallbacks = [];

    /**
     * @var Mock[] 已创建的 Mock 对象，测试结束后自动 verify
     */
    protected array $createdMocks = [];

    /**
     * 测试开始前执行
     */
    protected function setUp(): void
    {
        // 子类可以重写此方法
    }

    /**
     * 测试结束后执行
     */
    protected function tearDown(): void
    {
        // 子类可以重写此方法
    }

    /**
     * 所有测试开始前执行一次
     */
    public static function setUpBeforeClass(): void
    {
        // 子类可以重写此方法
    }

    /**
     * 所有测试结束后执行一次
     */
    public static function tearDownAfterClass(): void
    {
        // 子类可以重写此方法
    }

    /**
     * 添加前置回调
     */
    protected function beforeEach(callable $callback): void
    {
        $this->beforeEachCallbacks[] = $callback;
    }

    /**
     * 添加后置回调
     */
    protected function afterEach(callable $callback): void
    {
        $this->afterEachCallbacks[] = $callback;
    }

    /**
     * 运行测试用例
     */
    final public function runTest(string $method): TestResult
    {
        $result = new TestResult(static::class, $method);
        $deferredException = null;
        $cleanupError = null;
        $previousErrorHandler = $this->captureErrorHandler();
        $previousExceptionHandler = $this->captureExceptionHandler();

        try {
            // 执行 beforeEach 回调
            foreach ($this->beforeEachCallbacks as $callback) {
                $callback($this);
            }

            // 执行 setUp
            $this->setUpHasRun = true;
            $this->setUp();

            // 执行测试方法
            $this->$method();

            $result->setPassed(true);

        } catch (AssertionFailedException $e) {
            $result->setFailed($e->getMessage(), $e->getFile(), $e->getLine());
        } catch (SkippedTestException | IncompleteTestException $e) {
            $deferredException = $e;
        } catch (Throwable $e) {
            $result->setError($e->getMessage(), $e->getFile(), $e->getLine());
        } finally {
            $cleanupError = $this->runCleanup();
            $this->restoreErrorHandler($previousErrorHandler);
            $this->restoreExceptionHandler($previousExceptionHandler);

            if ($cleanupError !== null && !$result->isFailure() && !$result->isError()) {
                $result->setError($cleanupError->getMessage(), $cleanupError->getFile(), $cleanupError->getLine());
            }
        }

        if ($deferredException !== null && !$result->isError()) {
            throw $deferredException;
        }

        return $result;
    }

    /**
     * 获取所有测试方法
     */
    final public function getTestMethods(): array
    {
        $methods = [];

        $reflection = new \ReflectionClass($this);

        foreach ($reflection->getMethods() as $method) {
            if (str_starts_with($method->getName(), 'test')) {
                $methods[] = $method->getName();
            }
        }

        return $methods;
    }

    /**
     * 断言 - 相等
     */
    protected function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            $this->fail(
                $message ?: "Failed asserting that two values are the same.\n" .
                "Expected: " . $this->formatValue($expected) . "\n" .
                "Actual: " . $this->formatValue($actual)
            );
        }
    }

    /**
     * 断言 - 相等（宽松）
     */
    protected function assertEquals(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected != $actual) {
            $this->fail(
                $message ?: "Failed asserting that two values are equal.\n" .
                "Expected: " . $this->formatValue($expected) . "\n" .
                "Actual: " . $this->formatValue($actual)
            );
        }
    }

    /**
     * 断言 - 不相等
     */
    protected function assertNotSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected === $actual) {
            $this->fail(
                $message ?: "Failed asserting that two values are not the same.\n" .
                "Both values are: " . $this->formatValue($expected)
            );
        }
    }

    /**
     * 断言 - 不相等（宽松）
     */
    protected function assertNotEquals(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected == $actual) {
            $this->fail(
                $message ?: "Failed asserting that two values are not equal.\n" .
                "Both values are: " . $this->formatValue($expected)
            );
        }
    }

    /**
     * 断言 - 为真
     */
    protected function assertTrue(mixed $condition, string $message = ''): void
    {
        if ($condition !== true) {
            $this->fail(
                $message ?: "Failed asserting that " . $this->formatValue($condition) . " is true"
            );
        }
    }

    /**
     * 断言 - 为假
     */
    protected function assertFalse(mixed $condition, string $message = ''): void
    {
        if ($condition !== false) {
            $this->fail(
                $message ?: "Failed asserting that " . $this->formatValue($condition) . " is false"
            );
        }
    }

    /**
     * 断言 - 为空
     */
    protected function assertNull(mixed $value, string $message = ''): void
    {
        if ($value !== null) {
            $this->fail(
                $message ?: "Failed asserting that " . $this->formatValue($value) . " is null"
            );
        }
    }

    /**
     * 断言 - 非空
     */
    protected function assertNotNull(mixed $value, string $message = ''): void
    {
        if ($value === null) {
            $this->fail($message ?: "Failed asserting that value is not null");
        }
    }

    /**
     * 断言 - 为空数组/字符串
     */
    protected function assertEmpty(mixed $value, string $message = ''): void
    {
        if (!empty($value)) {
            $this->fail(
                $message ?: "Failed asserting that " . $this->formatValue($value) . " is empty"
            );
        }
    }

    /**
     * 断言 - 非空
     */
    protected function assertNotEmpty(mixed $value, string $message = ''): void
    {
        if (empty($value)) {
            $this->fail($message ?: "Failed asserting that value is not empty");
        }
    }

    /**
     * 断言 - 包含
     */
    protected function assertContains(mixed $needle, array|string $haystack, string $message = ''): void
    {
        if (is_array($haystack)) {
            if (!in_array($needle, $haystack, true)) {
                $this->fail(
                    $message ?: "Failed asserting that array contains " . $this->formatValue($needle)
                );
            }
        } else {
            if (!str_contains($haystack, (string) $needle)) {
                $this->fail(
                    $message ?: "Failed asserting that string contains " . $this->formatValue($needle)
                );
            }
        }
    }

    /**
     * 断言 - 不包含
     */
    protected function assertNotContains(mixed $needle, array|string $haystack, string $message = ''): void
    {
        if (is_array($haystack)) {
            if (in_array($needle, $haystack, true)) {
                $this->fail(
                    $message ?: "Failed asserting that array does not contain " . $this->formatValue($needle)
                );
            }
        } else {
            if (str_contains($haystack, (string) $needle)) {
                $this->fail(
                    $message ?: "Failed asserting that string does not contain " . $this->formatValue($needle)
                );
            }
        }
    }

    /**
     * 断言 - 数组键存在
     */
    protected function assertArrayHasKey(string|int $key, array $array, string $message = ''): void
    {
        if (!array_key_exists($key, $array)) {
            $this->fail(
                $message ?: "Failed asserting that array has key " . $this->formatValue($key)
            );
        }
    }

    /**
     * 断言 - 数组键不存在
     */
    protected function assertArrayNotHasKey(string|int $key, array $array, string $message = ''): void
    {
        if (array_key_exists($key, $array)) {
            $this->fail(
                $message ?: "Failed asserting that array does not have key " . $this->formatValue($key)
            );
        }
    }

    /**
     * 断言 - 数组长度
     */
    protected function assertCount(int $expectedCount, array|\Countable $array, string $message = ''): void
    {
        $actualCount = count($array);

        if ($expectedCount !== $actualCount) {
            $this->fail(
                $message ?: "Failed asserting that array count is {$expectedCount}. Actual: {$actualCount}"
            );
        }
    }

    /**
     * 断言 - 实例
     */
    protected function assertInstanceOf(string $expected, mixed $actual, string $message = ''): void
    {
        if (!($actual instanceof $expected)) {
            $this->fail(
                $message ?: "Failed asserting that " . get_debug_type($actual) . " is an instance of {$expected}"
            );
        }
    }

    /**
     * 断言 - 类型
     */
    protected function assertIsType(string $expected, mixed $actual, string $message = ''): void
    {
        $actualType = get_debug_type($actual);

        if ($actualType !== $expected) {
            $this->fail(
                $message ?: "Failed asserting that type is {$expected}. Actual: {$actualType}"
            );
        }
    }

    /**
     * 断言 - 异常
     */
    protected function assertThrows(string $exceptionClass, callable $callback): void
    {
        try {
            $callback();
        } catch (\Throwable $e) {
            if ($e instanceof $exceptionClass) {
                return;
            }
            $this->fail(
                "Expected exception {$exceptionClass} to be thrown, but " . get_class($e) . " was thrown"
            );
        }

        $this->fail("Expected exception {$exceptionClass} to be thrown, but no exception was thrown");
    }

    /**
     * 断言 - 字符串匹配
     */
    protected function assertMatchesRegularExpression(string $pattern, string $string, string $message = ''): void
    {
        if (!preg_match($pattern, $string)) {
            $this->fail(
                $message ?: "Failed asserting that string matches pattern {$pattern}"
            );
        }
    }

    /**
     * 断言 - 字符串包含
     */
    protected function assertStringContainsString(string $needle, string $haystack, string $message = ''): void
    {
        if (!str_contains($haystack, $needle)) {
            $this->fail(
                $message ?: "Failed asserting that string contains " . $this->formatValue($needle)
            );
        }
    }

    /**
     * 断言 - 字符串不包含
     */
    protected function assertStringNotContainsString(string $needle, string $haystack, string $message = ''): void
    {
        if (str_contains($haystack, $needle)) {
            $this->fail(
                $message ?: "Failed asserting that string does not contain " . $this->formatValue($needle)
            );
        }
    }

    /**
     * 断言 - 字符串开始于
     */
    protected function assertStringStartsWith(string $prefix, string $string, string $message = ''): void
    {
        if (!str_starts_with($string, $prefix)) {
            $this->fail(
                $message ?: "Failed asserting that string starts with " . $this->formatValue($prefix)
            );
        }
    }

    /**
     * 断言 - 字符串结束于
     */
    protected function assertStringEndsWith(string $suffix, string $string, string $message = ''): void
    {
        if (!str_ends_with($string, $suffix)) {
            $this->fail(
                $message ?: "Failed asserting that string ends with " . $this->formatValue($suffix)
            );
        }
    }

    /**
     * 断言 - 大于
     */
    protected function assertGreaterThan(int|float $expected, int|float $actual, string $message = ''): void
    {
        if (!($actual > $expected)) {
            $this->fail(
                $message ?: "Failed asserting that {$actual} is greater than {$expected}"
            );
        }
    }

    /**
     * 断言 - 大于等于
     */
    protected function assertGreaterThanOrEqual(int|float $expected, int|float $actual, string $message = ''): void
    {
        if (!($actual >= $expected)) {
            $this->fail(
                $message ?: "Failed asserting that {$actual} is greater than or equal to {$expected}"
            );
        }
    }

    /**
     * 断言 - 小于
     */
    protected function assertLessThan(int|float $expected, int|float $actual, string $message = ''): void
    {
        if (!($actual < $expected)) {
            $this->fail(
                $message ?: "Failed asserting that {$actual} is less than {$expected}"
            );
        }
    }

    /**
     * 断言 - 小于等于
     */
    protected function assertLessThanOrEqual(int|float $expected, int|float $actual, string $message = ''): void
    {
        if (!($actual <= $expected)) {
            $this->fail(
                $message ?: "Failed asserting that {$actual} is less than or equal to {$expected}"
            );
        }
    }

    /**
     * 断言 - 文件存在
     */
    protected function assertFileExists(string $file, string $message = ''): void
    {
        if (!file_exists($file)) {
            $this->fail(
                $message ?: "Failed asserting that file exists: {$file}"
            );
        }
    }

    /**
     * 断言 - 文件不存在
     */
    protected function assertFileDoesNotExist(string $file, string $message = ''): void
    {
        if (file_exists($file)) {
            $this->fail(
                $message ?: "Failed asserting that file does not exist: {$file}"
            );
        }
    }

    /**
     * 断言 - 目录存在
     */
    protected function assertDirectoryExists(string $directory, string $message = ''): void
    {
        if (!is_dir($directory)) {
            $this->fail(
                $message ?: "Failed asserting that directory exists: {$directory}"
            );
        }
    }

    /**
     * 失败
     */
    protected function fail(string $message = ''): void
    {
        throw new AssertionFailedException($message);
    }

    /**
     * 标记测试为跳过
     */
    protected function markTestSkipped(string $message = ''): void
    {
        throw new SkippedTestException($message ?: 'Test skipped');
    }

    /**
     * 标记测试为不完整
     */
    protected function markTestIncomplete(string $message = ''): void
    {
        throw new IncompleteTestException($message ?: 'Test incomplete');
    }

    /**
     * 格式化值用于输出
     */
    protected function formatValue(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_string($value)) {
            return "'{$value}'";
        }

        if (is_array($value)) {
            return 'Array(' . count($value) . ')';
        }

        if (is_object($value)) {
            return get_class($value);
        }

        return (string) $value;
    }

    private function runCleanup(): ?Throwable
    {
        try {
            // 执行 tearDown
            if ($this->setUpHasRun && !$this->tearDownHasRun) {
                $this->tearDownHasRun = true;
                $this->tearDown();
            }

            // 执行 afterEach 回调
            foreach ($this->afterEachCallbacks as $callback) {
                $callback($this);
            }

            // 自动验证所有 Mock
            foreach ($this->createdMocks as $mock) {
                $mock->verify();
            }
        } catch (Throwable $e) {
            return $e;
        }

        return null;
    }

    private function captureExceptionHandler(): mixed
    {
        $handler = set_exception_handler(static function (Throwable $e): void {
        });
        restore_exception_handler();

        return $handler;
    }

    private function captureErrorHandler(): mixed
    {
        $handler = set_error_handler(static function (): bool {
            return false;
        });
        restore_error_handler();

        return $handler;
    }

    private function restoreExceptionHandler(mixed $expected): void
    {
        for ($i = 0; $i < 8; $i++) {
            $current = $this->captureExceptionHandler();
            if ($this->handlersMatch($current, $expected)) {
                return;
            }

            restore_exception_handler();
        }
    }

    private function restoreErrorHandler(mixed $expected): void
    {
        for ($i = 0; $i < 8; $i++) {
            $current = $this->captureErrorHandler();
            if ($this->handlersMatch($current, $expected)) {
                return;
            }

            restore_error_handler();
        }
    }

    private function handlersMatch(mixed $left, mixed $right): bool
    {
        return $this->normalizeHandler($left) === $this->normalizeHandler($right);
    }

    private function normalizeHandler(mixed $handler): string
    {
        if ($handler === null) {
            return 'null';
        }

        if ($handler instanceof \Closure) {
            return 'closure:' . spl_object_id($handler);
        }

        if (is_string($handler)) {
            return 'string:' . $handler;
        }

        if (is_array($handler) && count($handler) === 2) {
            $target = is_object($handler[0])
                ? 'object:' . spl_object_id($handler[0])
                : 'class:' . (string) $handler[0];

            return 'array:' . $target . '::' . (string) $handler[1];
        }

        if (is_object($handler)) {
            return 'object:' . spl_object_id($handler);
        }

        return get_debug_type($handler) . ':' . json_encode($handler);
    }

    /**
     * 创建模拟对象
     */
    protected function mock(string $class): Mock
    {
        $mock = new Mock($class);
        $this->createdMocks[] = $mock;
        return $mock;
    }

    /**
     * 创建部分模拟
     */
    protected function partialMock(string $class, array $methods = []): Mock
    {
        $mock = new Mock($class, $methods, true);
        $this->createdMocks[] = $mock;
        return $mock;
    }

    /**
     * 监听方法调用
     */
    protected function spy(string $class): Mock
    {
        $mock = new Mock($class);
        $mock->shouldIgnoreMissing();
        $this->createdMocks[] = $mock;
        return $mock;
    }
}
