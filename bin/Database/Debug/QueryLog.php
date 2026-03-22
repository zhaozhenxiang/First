<?php

declare(strict_types=1);

namespace Bin\Database\Debug;

/**
 * 查询日志记录
 */
class QueryLog
{
    /**
     * SQL 查询语句
     */
    public string $sql;

    /**
     * 查询参数
     */
    public array $bindings;

    /**
     * 执行时间（毫秒）
     */
    public float $time;

    /**
     * 连接名称
     */
    public string $connection;

    /**
     * 查询时间戳
     */
    public float $microtime;

    /**
     * 结果行数
     */
    public int $rowCount;

    /**
     * 是否成功
     */
    public bool $success;

    /**
     * 错误信息
     */
    public ?string $error = null;

    /**
     * 调用堆栈
     */
    public ?array $trace = null;

    /**
     * 构造函数
     */
    public function __construct(
        string $sql,
        array $bindings = [],
        float $time = 0,
        string $connection = 'default',
        int $rowCount = 0,
        bool $success = true,
        ?string $error = null
    ) {
        $this->sql = $sql;
        $this->bindings = $bindings;
        $this->time = $time;
        $this->connection = $connection;
        $this->microtime = microtime(true);
        $this->rowCount = $rowCount;
        $this->success = $success;
        $this->error = $error;
    }

    /**
     * 创建查询日志
     */
    public static function make(
        string $sql,
        array $bindings = [],
        float $time = 0,
        string $connection = 'default'
    ): self {
        return new self($sql, $bindings, $time, $connection);
    }

    /**
     * 设置结果行数
     */
    public function setRowCount(int $count): self
    {
        $this->rowCount = $count;
        return $this;
    }

    /**
     * 设置为失败
     */
    public function setFailed(string $error): self
    {
        $this->success = false;
        $this->error = $error;
        return $this;
    }

    /**
     * 设置调用堆栈
     */
    public function setTrace(array $trace): self
    {
        $this->trace = $trace;
        return $this;
    }

    /**
     * 获取格式化的 SQL（带绑定参数）
     */
    public function toFormattedSql(): string
    {
        $sql = $this->sql;

        foreach ($this->bindings as $key => $value) {
            $placeholder = is_int($key) ? '?' : ':' . $key;

            if ($value === null) {
                $replacement = 'NULL';
            } elseif (is_bool($value)) {
                $replacement = $value ? '1' : '0';
            } elseif (is_int($value) || is_float($value)) {
                $replacement = (string) $value;
            } else {
                $replacement = "'" . addslashes((string) $value) . "'";
            }

            $pos = strpos($sql, $placeholder);

            if ($pos !== false) {
                $sql = substr_replace($sql, $replacement, $pos, strlen($placeholder));
            }
        }

        return $sql;
    }

    /**
     * 获取查询类型
     */
    public function getType(): string
    {
        $sql = strtoupper(trim($this->sql));
        $words = explode(' ', $sql, 2);

        return $words[0] ?? 'UNKNOWN';
    }

    /**
     * 是否为 SELECT 查询
     */
    public function isSelect(): bool
    {
        return strtoupper(substr(ltrim($this->sql), 0, 6)) === 'SELECT';
    }

    /**
     * 是否为 INSERT 查询
     */
    public function isInsert(): bool
    {
        return strtoupper(substr(ltrim($this->sql), 0, 6)) === 'INSERT';
    }

    /**
     * 是否为 UPDATE 查询
     */
    public function isUpdate(): bool
    {
        return strtoupper(substr(ltrim($this->sql), 0, 6)) === 'UPDATE';
    }

    /**
     * 是否为 DELETE 查询
     */
    public function isDelete(): bool
    {
        return strtoupper(substr(ltrim($this->sql), 0, 6)) === 'DELETE';
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        return [
            'sql' => $this->sql,
            'bindings' => $this->bindings,
            'formatted_sql' => $this->toFormattedSql(),
            'time' => $this->time,
            'connection' => $this->connection,
            'row_count' => $this->rowCount,
            'success' => $this->success,
            'error' => $this->error,
            'type' => $this->getType(),
        ];
    }
}
