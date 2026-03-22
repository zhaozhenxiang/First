<?php

declare(strict_types=1);

namespace Tests;

use Bin\Log\Logger;
use Bin\Log\LogManager;
use Bin\Testing\TestCase;

/**
 * 日志系统测试
 */
class LoggerTest extends TestCase
{
    private string $tempLogPath;
    private TestLogger $logger;

    protected function setUp(): void
    {
        parent::setUp();

        // 创建临时日志目录
        $this->tempLogPath = sys_get_temp_dir() . '/logs_test_' . uniqid();
        mkdir($this->tempLogPath, 0777, true);

        $this->logger = new TestLogger('test', $this->tempLogPath);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // 清理临时目录
        $this->removeDirectory($this->tempLogPath);
    }

    public function testDebug(): void
    {
        $this->logger->debug('Debug message');

        $this->assertLogFileContains('Debug message');
    }

    public function testInfo(): void
    {
        $this->logger->info('Info message');

        $this->assertLogFileContains('Info message');
    }

    public function testWarning(): void
    {
        $this->logger->warning('Warning message');

        $this->assertLogFileContains('Warning message');
    }

    public function testError(): void
    {
        $this->logger->error('Error message');

        $this->assertLogFileContains('Error message');
    }

    public function testLogWithContext(): void
    {
        $this->logger->info('User login', ['user_id' => 123, 'ip' => '127.0.0.1']);

        $this->assertLogFileContains('User login');
        $this->assertLogFileContains('user_id');
    }

    public function testLogManager(): void
    {
        LogManager::clear();

        // 注册 TestLogger 以便写入到临时目录
        LogManager::registerChannel('app', $this->logger);

        LogManager::debug('Debug via manager');

        $this->assertLogFileContains('Debug via manager');
    }

    private function assertLogFileContains(string $text): void
    {
        $content = $this->getLogFileContent();
        $this->assertTrue(str_contains($content, $text), "Log file should contain: {$text}");
    }

    private function getLogFileContent(): string
    {
        $date = date('Y-m-d');
        $logFile = $this->tempLogPath . '/' . $date . '.log';

        if (!file_exists($logFile)) {
            return '';
        }

        return file_get_contents($logFile);
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $files = scandir($path);
        $files = array_diff($files, ['.', '..']);

        foreach ($files as $file) {
            $filePath = $path . '/' . $file;

            if (is_dir($filePath)) {
                $this->removeDirectory($filePath);
            } else {
                unlink($filePath);
            }
        }

        rmdir($path);
    }
}

/**
 * 测试用 Logger
 */
class TestLogger extends Logger
{
    private string $customPath;

    public function __construct(string $channel, string $customPath)
    {
        $this->customPath = $customPath;
        parent::__construct($channel);
    }

    protected function write(string $record): void
    {
        $logPath = $this->customPath;

        // 确保日志目录存在
        if (!is_dir($logPath)) {
            mkdir($logPath, 0755, true);
        }

        $file = $logPath . '/' . date('Y-m-d') . '.log';

        file_put_contents($file, $record, FILE_APPEND | LOCK_EX);
    }
}
