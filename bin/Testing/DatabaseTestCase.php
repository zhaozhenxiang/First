<?php

declare(strict_types=1);

namespace Bin\Testing;

use Bin\Database\Model;
use PDO;

/**
 * 数据库测试基类
 *
 * 提供内存 SQLite 连接和自动清理功能。
 * 所有需要数据库的测试都应继承此类。
 */
abstract class DatabaseTestCase extends TestCase
{
    protected PDO $pdo;

    /**
     * @var string[] 已注册的模型类（需要 tearDown 清理）
     */
    private array $registeredModels = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = $this->createSqliteConnection();
    }

    protected function tearDown(): void
    {
        // 清理所有注册的模型
        foreach ($this->registeredModels as $modelClass) {
            $modelClass::setConnection(null);
        }

        $this->registeredModels = [];

        parent::tearDown();
    }

    /**
     * 创建内存 SQLite 连接
     */
    protected function createSqliteConnection(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $pdo;
    }

    /**
     * 建表并返回 PDO
     *
     * @param string $table 表名
     * @param array<string, string> $columns 列定义 ['name' => 'TEXT NOT NULL', ...]
     */
    protected function createTable(string $table, array $columns): void
    {
        $cols = [];
        foreach ($columns as $name => $definition) {
            $cols[] = "{$name} {$definition}";
        }

        $sql = 'CREATE TABLE ' . $table . ' (' . implode(', ', $cols) . ')';
        $this->pdo->exec($sql);
    }

    /**
     * 注册模型类（tearDown 时自动 setConnection(null)）
     */
    protected function registerModel(string $modelClass): void
    {
        $this->registeredModels[] = $modelClass;
        $modelClass::setConnection($this->pdo);
    }

    /**
     * 插入测试数据
     */
    protected function insertData(string $table, array $rows): void
    {
        foreach ($rows as $row) {
            $keys = implode(', ', array_keys($row));
            $values = implode(', ', array_map(fn($v) => $this->pdo->quote((string) $v), array_values($row)));
            $this->pdo->exec("INSERT INTO {$table} ({$keys}) VALUES ({$values})");
        }
    }
}
