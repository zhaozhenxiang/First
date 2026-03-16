<?php

declare(strict_types=1);

namespace Bin\Log;

/**
 * 日志记录器
 */
class Logger
{
    /** @var array<string, mixed> 上下文数据 */
    private array $context = [];

    /** @var string 日志通道 */
    private string $channel;

    /** @var string 最低日志级别 */
    private string $level = 'debug';

    /** @var array<string, int> 日志级别优先级 */
    private const LEVELS = [
        'debug' => 100,
        'info' => 200,
        'notice' => 250,
        'warning' => 300,
        'error' => 400,
        'critical' => 500,
        'alert' => 550,
        'emergency' => 600,
    ];

    public function __construct(string $channel = 'app')
    {
        $this->channel = $channel;
    }

    /**
     * 记录 DEBUG 级别日志
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    /**
     * 记录 INFO 级别日志
     */
    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    /**
     * 记录 NOTICE 级别日志
     */
    public function notice(string $message, array $context = []): void
    {
        $this->log('notice', $message, $context);
    }

    /**
     * 记录 WARNING 级别日志
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    /**
     * 记录 ERROR 级别日志
     */
    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    /**
     * 记录 CRITICAL 级别日志
     */
    public function critical(string $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    /**
     * 记录 ALERT 级别日志
     */
    public function alert(string $message, array $context = []): void
    {
        $this->log('alert', $message, $context);
    }

    /**
     * 记录 EMERGENCY 级别日志
     */
    public function emergency(string $message, array $context = []): void
    {
        $this->log('emergency', $message, $context);
    }

    /**
     * 记录日志
     */
    public function log(string $level, string $message, array $context = []): void
    {
        if (!$this->shouldLog($level)) {
            return;
        }

        $record = $this->format($level, $message, $context);
        $this->write($record);
    }

    /**
     * 添加全局上下文
     */
    public function withContext(array $context): self
    {
        $logger = clone $this;
        $logger->context = array_merge($this->context, $context);
        return $logger;
    }

    /**
     * 设置最低日志级别
     */
    public function setLevel(string $level): void
    {
        if (!isset(self::LEVELS[$level])) {
            throw new \InvalidArgumentException("Invalid log level: {$level}");
        }
        $this->level = $level;
    }

    /**
     * 检查是否应该记录该级别的日志
     */
    private function shouldLog(string $level): bool
    {
        return self::LEVELS[$level] >= self::LEVELS[$this->level];
    }

    /**
     * 格式化日志记录
     */
    private function format(string $level, string $message, array $context): string
    {
        $time = date('Y-m-d H:i:s');
        $channel = $this->channel;
        $contextStr = $context !== [] ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';

        return "[{$time}] {$channel}.{$level}: {$message}{$contextStr}" . PHP_EOL;
    }

    /**
     * 写入日志
     */
    private function write(string $record): void
    {
        $logPath = BASE_PATH . '/storage/logs';

        // 确保日志目录存在
        if (!is_dir($logPath)) {
            mkdir($logPath, 0755, true);
        }

        $file = $logPath . '/' . date('Y-m-d') . '.log';

        file_put_contents($file, $record, FILE_APPEND | LOCK_EX);
    }

    /**
     * 记录异常
     */
    public function exception(\Throwable $e): void
    {
        $context = [
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ];

        $this->error($e->getMessage(), $context);
    }

    /**
     * 记录 SQL 查询
     */
    public function query(string $sql, array $bindings = [], float $time = 0): void
    {
        $context = [
            'sql' => $sql,
            'bindings' => $bindings,
            'time' => round($time * 1000, 2) . 'ms'
        ];

        $this->debug('SQL Query', $context);
    }
}
