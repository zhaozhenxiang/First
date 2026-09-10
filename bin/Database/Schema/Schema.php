<?php

declare(strict_types=1);

namespace Bin\Database\Schema;

use PDO;
use Bin\Database\ConnectionManager;

/**
 * Schema 门面 - 提供静态接口访问 Schema Builder
 */
class Schema
{
    protected static ?SchemaBuilder $builder = null;

    /**
     * 获取 Schema Builder
     */
    public static function builder(): SchemaBuilder
    {
        if (self::$builder === null) {
            self::$builder = new SchemaBuilder(ConnectionManager::getConnection());
        }

        return self::$builder;
    }

    /**
     * 创建表
     */
    public static function create(string $table, callable $callback): void
    {
        self::builder()->create($table, $callback);
    }

    /**
     * 修改表
     */
    public static function table(string $table, callable $callback): void
    {
        self::builder()->table($table, $callback);
    }

    /**
     * 删除表
     */
    public static function drop(string $table): void
    {
        self::builder()->drop($table);
    }

    /**
     * 删除表（如果存在）
     */
    public static function dropIfExists(string $table): void
    {
        self::builder()->dropIfExists($table);
    }

    /**
     * 重命名表
     */
    public static function rename(string $from, string $to): void
    {
        self::builder()->rename($from, $to);
    }

    /**
     * 检查表是否存在
     */
    public static function hasTable(string $table): bool
    {
        return self::builder()->hasTable($table);
    }

    /**
     * 检查列是否存在
     */
    public static function hasColumn(string $table, string $column): bool
    {
        return self::builder()->hasColumn($table, $column);
    }

    /**
     * 获取表列
     */
    public static function getColumns(string $table): array
    {
        return self::builder()->getColumns($table);
    }

    /**
     * 获取表列表
     */
    public static function getTables(): array
    {
        return self::builder()->getTables();
    }

    /**
     * 获取索引
     */
    public static function getIndexes(string $table): array
    {
        return self::builder()->getIndexes($table);
    }

    /**
     * 检查索引是否存在
     */
    public static function hasIndex(string $table, string $index): bool
    {
        return self::builder()->hasIndex($table, $index);
    }

    /**
     * 获取外键
     */
    public static function getForeignKeys(string $table): array
    {
        return self::builder()->getForeignKeys($table);
    }

    /**
     * 禁用外键约束
     */
    public static function disableForeignKeyConstraints(): void
    {
        self::builder()->disableForeignKeyConstraints();
    }

    /**
     * 启用外键约束
     */
    public static function enableForeignKeyConstraints(): void
    {
        self::builder()->enableForeignKeyConstraints();
    }

    /**
     * 设置表前缀
     */
    public static function setTablePrefix(string $prefix): void
    {
        self::builder()->setTablePrefix($prefix);
    }

    /**
     * 获取表前缀
     */
    public static function getTablePrefix(): string
    {
        return self::builder()->getTablePrefix();
    }

    /**
     * 在事务中执行
     */
    public static function transaction(callable $callback): mixed
    {
        $connection = ConnectionManager::getConnection();

        try {
            $connection->beginTransaction();
            $result = $callback();
            $connection->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
            throw $e;
        }
    }

    /**
     * 静态调用转发
     */
    public static function __callStatic(string $method, array $parameters): mixed
    {
        return self::builder()->$method(...$parameters);
    }
}
