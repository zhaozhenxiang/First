<?php

declare(strict_types=1);

namespace Bin\Testing;

use Throwable;

/**
 * 测试运行器
 */
class TestRunner
{
    protected string $testPath;

    protected string $pattern = '*Test.php';

    protected array $results = [];

    protected array $suites = [];

    protected int $passed = 0;

    protected int $failed = 0;

    protected int $errors = 0;

    protected int $skipped = 0;

    protected int $incomplete = 0;

    protected ?string $filter = null;

    protected float $startTime;

    protected float $duration = 0;

    protected bool $verbose = false;

    protected bool $stopOnFailure = false;

    public function __construct(string $testPath = '')
    {
        $this->testPath = $testPath ?: basePath('/tests');
    }

    /**
     * 运行所有测试
     */
    public function run(): TestSummary
    {
        $this->startTime = microtime(true);

        $this->loadTestSuites();

        $this->runTests();

        $this->duration = microtime(true) - $this->startTime;

        return $this->createSummary();
    }

    /**
     * 加载测试套件
     */
    protected function loadTestSuites(): void
    {
        $files = $this->getTestFiles();

        foreach ($files as $file) {
            $this->loadTestSuite($file);
        }
    }

    /**
     * 获取测试文件
     */
    protected function getTestFiles(): array
    {
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->testPath)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                if (fnmatch($this->pattern, $file->getFilename())) {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }

    /**
     * 加载测试套件
     */
    protected function loadTestSuite(string $file): void
    {
        require_once $file;

        $className = $this->getClassNameFromFile($file);

        if (!class_exists($className)) {
            echo "Warning: Could not find class {$className} in {$file}\n";
            return;
        }

        $suite = new $className();

        if (!($suite instanceof TestCase)) {
            echo "Warning: Class {$className} does not extend TestCase\n";
            return;
        }

        $this->suites[$className] = $suite;
    }

    /**
     * 从文件获取类名
     */
    protected function getClassNameFromFile(string $file): string
    {
        $content = file_get_contents($file);

        if (preg_match('/namespace\s+([\w\\\\]+);/i', $content, $matches)) {
            $namespace = $matches[1];
        } else {
            $namespace = 'Tests';
        }

        if (preg_match('/class\s+(\w+)\s+/i', $content, $matches)) {
            $className = $matches[1];
        } else {
            $className = basename($file, '.php');
        }

        return $namespace . '\\' . $className;
    }

    /**
     * 运行测试
     */
    protected function runTests(): void
    {
        foreach ($this->suites as $className => $suite) {
            $this->runTestSuite($className, $suite);
        }
    }

    /**
     * 运行测试套件
     */
    protected function runTestSuite(string $className, TestCase $suite): void
    {
        // 运行 setUpBeforeClass
        try {
            $className::setUpBeforeClass();
        } catch (Throwable $e) {
            echo "Error in setUpBeforeClass for {$className}: {$e->getMessage()}\n";
            return;
        }

        $methods = $suite->getTestMethods();

        // 应用 filter 过滤
        if ($this->filter !== null) {
            $methods = array_filter($methods, fn(string $m) => str_contains($m, $this->filter));
        }

        if ($this->verbose) {
            echo "\n{$className}\n";
        }

        if (empty($methods)) {
            return;
        }

        foreach ($methods as $method) {
            $this->runTestMethod($suite, $method);

            if ($this->stopOnFailure && ($this->failed > 0 || $this->errors > 0)) {
                break;
            }
        }

        // 运行 tearDownAfterClass
        try {
            $className::tearDownAfterClass();
        } catch (Throwable $e) {
            echo "Error in tearDownAfterClass for {$className}: {$e->getMessage()}\n";
        }
    }

    /**
     * 运行测试方法
     */
    protected function runTestMethod(TestCase $suite, string $method): void
    {
        $instance = clone $suite;

        $startTime = microtime(true);

        try {
            $result = $instance->runTest($method);
        } catch (SkippedTestException $e) {
            $result = new TestResult(get_class($suite), $method);
            $this->skipped++;
            if ($this->verbose) {
                echo "  S  {$method} - {$e->getMessage()}\n";
            }
            return;
        } catch (IncompleteTestException $e) {
            $result = new TestResult(get_class($suite), $method);
            $this->incomplete++;
            if ($this->verbose) {
                echo "  I  {$method} - {$e->getMessage()}\n";
            }
            return;
        }

        // 注入计时信息到 TestResult
        $ref = new \ReflectionProperty($result, 'startTime');
        $ref->setValue($result, $startTime);
        $result->stop();

        $this->results[] = $result;

        if ($result->isPassed()) {
            $this->passed++;
            if ($this->verbose) {
                echo "  ✓  {$method}\n";
            } else {
                echo '.';
            }
        } elseif ($result->isFailure()) {
            $this->failed++;
            if ($this->verbose) {
                echo "  ✗  {$method}\n";
                echo "      " . str_replace("\n", "\n      ", $result->getFailureMessage()) . "\n";
                if ($result->getFile()) {
                    echo "      at {$result->getFile()}:{$result->getLine()}\n";
                }
            } else {
                echo 'F';
            }
        } elseif ($result->isError()) {
            $this->errors++;
            if ($this->verbose) {
                echo "  E  {$method}\n";
                echo "      " . str_replace("\n", "\n      ", $result->getFailureMessage()) . "\n";
                if ($result->getFile()) {
                    echo "      at {$result->getFile()}:{$result->getLine()}\n";
                }
            } else {
                echo 'E';
            }
        }
    }

    /**
     * 创建摘要
     */
    protected function createSummary(): TestSummary
    {
        return new TestSummary(
            $this->passed,
            $this->failed,
            $this->errors,
            $this->skipped,
            $this->incomplete,
            $this->duration,
            $this->results
        );
    }

    /**
     * 设置详细输出
     */
    public function setVerbose(bool $verbose): self
    {
        $this->verbose = $verbose;
        return $this;
    }

    /**
     * 设置失败时停止
     */
    public function setStopOnFailure(bool $stop): self
    {
        $this->stopOnFailure = $stop;
        return $this;
    }

    /**
     * 设置测试文件模式
     */
    public function setPattern(string $pattern): self
    {
        $this->pattern = $pattern;
        return $this;
    }

    /**
     * 设置过滤
     */
    public function setFilter(string $filter): self
    {
        $this->filter = $filter;
        return $this;
    }

    /**
     * 获取结果
     */
    public function getResults(): array
    {
        return $this->results;
    }
}
