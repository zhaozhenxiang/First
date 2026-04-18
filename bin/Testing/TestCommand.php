#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * 测试命令行工具
 *
 * 用法:
 * php test                       # 运行所有测试
 * php test --verbose             # 详细输出
 * php test --stop-on-failure     # 失败时停止
 * php test tests/ExampleTest.php # 运行指定测试文件
 */

require_once dirname(__DIR__) . '/autoload.php';

use Bin\Testing\TestRunner;

class TestCommand
{
    protected string $testPath;

    protected bool $verbose = false;

    protected bool $stopOnFailure = false;

    protected string $pattern = '*Test.php';

    protected ?string $filter = null;

    protected ?string $singleTestFile = null;

    public function __construct(array $argv)
    {
        $this->testPath = basePath('/tests');

        $this->parseOptions($argv);
    }

    /**
     * 解析选项
     */
    protected function parseOptions(array $argv): void
    {
        array_shift($argv); // 移除脚本名

        foreach ($argv as $arg) {
            match ($arg) {
                '--verbose', '-v' => $this->verbose = true,
                '--stop-on-failure' => $this->stopOnFailure = true,
                default => $this->parseArgument($arg),
            };
        }
    }

    /**
     * 解析参数
     */
    protected function parseArgument(string $arg): void
    {
        if (str_starts_with($arg, '--filter=')) {
            $this->filter = substr($arg, 9);
        } elseif (str_starts_with($arg, '--pattern=')) {
            $this->pattern = substr($arg, 10);
        } elseif (str_starts_with($arg, '-')) {
            // 未知选项，忽略
        } elseif (is_file($arg)) {
            $this->singleTestFile = $arg;
        } else {
            // 可能是目录
            if (is_dir($arg)) {
                $this->testPath = $arg;
            }
        }
    }

    /**
     * 运行测试文件
     */
    protected function runTestFile(string $file): int
    {
        require_once $file;

        $className = $this->getClassNameFromFile($file);

        if (!class_exists($className)) {
            echo "Error: Could not find class {$className}\n";
            return 1;
        }

        $instance = new $className();

        if (!($instance instanceof \Bin\Testing\TestCase)) {
            echo "Error: Class {$className} does not extend TestCase\n";
            return 1;
        }

        $methods = $instance->getTestMethods();

        echo "Running tests in {$file}\n\n";

        $passed = 0;
        $failed = 0;

        foreach ($methods as $method) {
            echo "  {$method}... ";

            try {
                $result = $instance->runTest($method);

                if ($result->isPassed()) {
                    echo "✓ PASS\n";
                    $passed++;
                } else {
                    echo "✗ FAIL\n";
                    echo "    " . str_replace("\n", "\n    ", $result->getFailureMessage()) . "\n";
                    if ($result->getFile()) {
                        echo "    at {$result->getFile()}:{$result->getLine()}\n";
                    }
                    $failed++;
                }
            } catch (\Throwable $e) {
                echo "✗ ERROR\n";
                echo "    {$e->getMessage()}\n";
                echo "    at {$e->getFile()}:{$e->getLine()}\n";
                $failed++;
            }
        }

        echo "\n";
        echo "Passed: {$passed}, Failed: {$failed}\n";

        return $failed > 0 ? 1 : 0;
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
     * 使用输出缓冲运行
     */
    private function runWithBufferedOutput(callable $callback): int
    {
        $initialBufferLevel = ob_get_level();
        ob_start();

        try {
            return $callback();
        } finally {
            while (ob_get_level() > $initialBufferLevel) {
                ob_end_flush();
            }
        }
    }

    /**
     * 运行
     */
    public function run(): int
    {
        return $this->runWithBufferedOutput(function (): int {
            if ($this->singleTestFile !== null) {
                return $this->runTestFile($this->singleTestFile);
            }

            echo "Testing Framework\n";
            echo "==================\n\n";

            $runner = new TestRunner($this->testPath);

            if ($this->verbose) {
                $runner->setVerbose(true);
            }

            if ($this->stopOnFailure) {
                $runner->setStopOnFailure(true);
            }

            $runner->setPattern($this->pattern);

            if ($this->filter !== null) {
                $runner->setFilter($this->filter);
            }

            $summary = $runner->run();

            $summary->output();

            return $summary->getExitCode();
        });
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $command = new TestCommand($argv);
    exit($command->run());
}
