<?php

declare(strict_types=1);

namespace Bin\Testing;

use Bin\Database\Schema\Schema;
use Bin\Database\Migrations\Migrator;

/**
 * 测试套件 - 基类，用于数据库测试
 */
abstract class TestSuite extends TestCase
{
    protected static bool $migrated = false;

    /**
     * 设置测试环境
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // 运行测试迁移
        if (!static::$migrated) {
            static::runTestMigrations();
            static::$migrated = true;
        }
    }

    /**
     * 清理测试环境
     */
    public static function tearDownAfterClass(): void
    {
        // 清理测试数据
        static::cleanupTestData();

        parent::tearDownAfterClass();
    }

    /**
     * 运行测试迁移
     */
    protected static function runTestMigrations(): void
    {
        $migrator = new Migrator(basePath('/database/migrations'));

        // 清空所有表（除了 migrations 表）
        Schema::disableForeignKeyConstraints();

        $tables = Schema::getTables();
        foreach ($tables as $table) {
            if ($table !== 'migrations') {
                Schema::dropIfExists($table);
            }
        }

        Schema::enableForeignKeyConstraints();

        // 运行迁移
        $migrator->run();
    }

    /**
     * 清理测试数据
     */
    protected static function cleanupTestData(): void
    {
        // 清空所有表数据（但保留表结构）
        Schema::disableForeignKeyConstraints();

        $tables = Schema::getTables();
        foreach ($tables as $table) {
            if ($table !== 'migrations') {
                Schema::table($table, function ($table) {
                    // SQLite 风格
                    try {
                        \Bin\Model\Model::getConnection()->exec("DELETE FROM {$table->getTable()}");
                    } catch (\Exception $e) {
                        // 忽略错误
                    }
                });
            }
        }

        Schema::enableForeignKeyConstraints();
    }

    /**
     * 断言表存在
     */
    protected function assertTableExists(string $table, string $message = ''): void
    {
        $this->assertTrue(
            Schema::hasTable($table),
            $message ?: "Failed asserting that table '{$table}' exists"
        );
    }

    /**
     * 断言表不存在
     */
    protected function assertTableNotExists(string $table, string $message = ''): void
    {
        $this->assertFalse(
            Schema::hasTable($table),
            $message ?: "Failed asserting that table '{$table}' does not exist"
        );
    }

    /**
     * 断言列存在
     */
    protected function assertColumnExists(string $table, string $column, string $message = ''): void
    {
        $this->assertTrue(
            Schema::hasColumn($table, $column),
            $message ?: "Failed asserting that column '{$column}' exists on table '{$table}'"
        );
    }

    /**
     * 断言列不存在
     */
    protected function assertColumnNotExists(string $table, string $column, string $message = ''): void
    {
        $this->assertFalse(
            Schema::hasColumn($table, $column),
            $message ?: "Failed asserting that column '{$column}' does not exist on table '{$table}'"
        );
    }

    /**
     * 断言数据库有记录
     */
    protected function assertDatabaseHas(string $table, array $data, string $message = ''): void
    {
        $query = \Bin\Database\Model::query();

        $query->getConnection();

        $qb = new \Bin\Database\QueryBuilder($query->getConnection());
        $qb->from($table);

        foreach ($data as $key => $value) {
            $qb->where($key, $value);
        }

        $count = $qb->count();

        $this->assertTrue(
            $count > 0,
            $message ?: "Failed asserting that table '{$table}' has record matching " . json_encode($data)
        );
    }

    /**
     * 断言数据库没有记录
     */
    protected function assertDatabaseMissing(string $table, array $data, string $message = ''): void
    {
        $query = \Bin\Database\Model::query();

        $qb = new \Bin\Database\QueryBuilder($query->getConnection());
        $qb->from($table);

        foreach ($data as $key => $value) {
            $qb->where($key, $value);
        }

        $count = $qb->count();

        $this->assertTrue(
            $count === 0,
            $message ?: "Failed asserting that table '{$table}' has no record matching " . json_encode($data)
        );
    }

    /**
     * 断言记录数量
     */
    protected function assertDatabaseCount(string $table, int $count, string $message = ''): void
    {
        $query = \Bin\Database\Model::query();

        $qb = new \Bin\Database\QueryBuilder($query->getConnection());
        $qb->from($table);

        $actualCount = $qb->count();

        $this->assertSame(
            $count,
            $actualCount,
            $message ?: "Failed asserting that table '{$table}' has {$count} records. Actual: {$actualCount}"
        );
    }
}
