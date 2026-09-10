<?php

declare(strict_types=1);

namespace Bin\Database\Migrations;

use Bin\Database\Schema\Schema;
use Bin\Database\ConnectionManager;
use PDO;
use Exception;

/**
 * 迁移执行器
 */
class Migrator
{
    protected PDO $connection;

    protected string $table = 'migrations';

    protected string $path = '';

    public function __construct(string $path = '')
    {
        $this->connection = ConnectionManager::getConnection();
        $this->path = $path ?: basePath('/database/migrations');
    }

    /**
     * 运行所有待执行的迁移
     */
    public function run(array $options = []): void
    {
        $this->ensureMigrationTableExists();

        $files = $this->getMigrationFiles();

        $ran = $this->getRanMigrations();

        $pending = $this->pendingMigrations($files, $ran);

        if (empty($pending)) {
            echo "Nothing to migrate.\n";
            return;
        }

        $this->runPending($pending);

        $count = count($pending);
        echo "Migrated: {$count}\n";
    }    /**
     * 回滚最后一次迁移
     */
    public function rollback(int $steps = 1): void
    {
        $this->ensureMigrationTableExists();

        $migrations = $this->getLastMigrations($steps);

        if (empty($migrations)) {
            echo "Nothing to rollback.\n";
            return;
        }

        foreach ($migrations as $migration) {
            $this->down($migration);
            $this->deleteMigration($migration);
            echo "Rolled back: {$migration}\n";
        }
    }

    /**
     * 回滚所有迁移
     */
    public function reset(): void
    {
        $this->ensureMigrationTableExists();

        $migrations = $this->getRanMigrations();

        foreach (array_reverse($migrations) as $migration) {
            $this->down($migration);
            $this->deleteMigration($migration);
            echo "Rolled back: {$migration}\n";
        }
    }

    /**
     * 回滚并重新运行所有迁移
     */
    public function refresh(): void
    {
        $this->reset();
        echo "\n";
        $this->run();
    }

    /**
     * 获取迁移文件列表
     */
    public function getMigrationFiles(): array
    {
        $files = [];

        if (!is_dir($this->path)) {
            return $files;
        }

        foreach (scandir($this->path) as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                $files[] = $this->path . '/' . $file;
            }
        }

        sort($files);

        return $files;
    }

    /**
     * 获取已运行的迁移
     */
    protected function getRanMigrations(): array
    {
        try {
            $stmt = $this->connection->query("SELECT migration FROM {$this->table} ORDER BY id ASC");
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * 获取待执行的迁移
     */
    protected function pendingMigrations(array $files, array $ran): array
    {
        $pending = [];

        foreach ($files as $file) {
            $basename = basename($file, '.php');

            if (!in_array($basename, $ran, true)) {
                $pending[$basename] = $file;
            }
        }

        return $pending;
    }

    /**
     * 运行待执行的迁移（同一轮 run 共用一个递增的 batch 号）
     */
    protected function runPending(array $pending): void
    {
        $batch = $this->nextBatchNumber();

        foreach ($pending as $name => $file) {
            $this->runUp($name, $file, $batch);
        }
    }

    /**
     * 运行单个迁移
     *
     * up() 与台账记录放在一起：迁移失败时不留下"半执行"状态
     * （MySQL 的 DDL 会隐式提交，事务包裹在支持 DDL 事务的驱动上才有实效）。
     */
    protected function runUp(string $name, string $file, ?int $batch = null): void
    {
        $migration = $this->resolve($file);

        $connection = $this->connection;

        $execute = function () use ($migration, $name, $batch): void {
            $migration->up();
            $this->recordMigration($name, $batch);
        };

        if ($connection->inTransaction()) {
            $execute();
        } else {
            $connection->beginTransaction();
            try {
                $execute();
                $connection->commit();
            } catch (\Throwable $e) {
                if ($connection->inTransaction()) {
                    $connection->rollBack();
                }
                throw $e;
            }
        }

        echo "Migrated: {$name}\n";
    }

    /**
     * 回滚单个迁移
     */
    protected function down(string $name): void
    {
        $file = $this->findMigrationFile($name);

        if ($file === null) {
            throw new Exception("Migration not found: {$name}");
        }

        $migration = $this->resolve($file);

        $migration->down();
    }

    /**
     * 解析迁移文件（支持匿名类和命名类）
     */
    protected function resolve(string $file): Migration
    {
        $migration = require $file;

        // 匿名类：文件返回 new class extends Migration
        if ($migration instanceof Migration) {
            return $migration;
        }

        // 命名类：通过类名实例化
        require_once $file;

        $class = $this->getMigrationClass($file);

        if (!class_exists($class)) {
            throw new Exception("Migration class not found: {$class}");
        }

        return new $class();
    }

    /**
     * 获取迁移类名
     */
    protected function getMigrationClass(string $file): string
    {
        $basename = basename($file, '.php');

        // 移除时间戳前缀
        $name = preg_replace('/^[0-9]+_/', '', $basename);

        // 转换为类名
        $name = str_replace('_', ' ', $name);
        $name = ucwords($name);
        $name = str_replace(' ', '', $name);

        return "Database\\Migrations\\{$name}";
    }

    /**
     * 查找迁移文件
     */
    protected function findMigrationFile(string $name): ?string
    {
        $files = $this->getMigrationFiles();

        foreach ($files as $file) {
            if (basename($file, '.php') === $name) {
                return $file;
            }
        }

        return null;
    }

    /**
     * 获取最后几次迁移
     */
    protected function getLastMigrations(int $steps): array
    {
        $sql = "SELECT migration FROM {$this->table} ORDER BY id DESC LIMIT " . (int) $steps;

        $stmt = $this->connection->query($sql);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * 记录迁移
     */
    protected function recordMigration(string $name, ?int $batch = null): void
    {
        $stmt = $this->connection->prepare("INSERT INTO {$this->table} (migration, batch) VALUES (?, ?)");

        $stmt->execute([$name, $batch ?? $this->nextBatchNumber()]);
    }

    /**
     * 下一个 batch 号（每轮 run 递增）
     */
    protected function nextBatchNumber(): int
    {
        try {
            $stmt = $this->connection->query("SELECT COALESCE(MAX(batch), 0) FROM {$this->table}");

            return (int) $stmt->fetchColumn() + 1;
        } catch (\Throwable) {
            return 1;
        }
    }

    /**
     * 删除迁移记录
     */
    protected function deleteMigration(string $name): void
    {
        $stmt = $this->connection->prepare("DELETE FROM {$this->table} WHERE migration = ?");

        $stmt->execute([$name]);
    }

    /**
     * 确保迁移表存在
     */
    protected function ensureMigrationTableExists(): void
    {
        if ($this->hasMigrationTable()) {
            return;
        }

        Schema::create($this->table, function ($table) {
            $table->id();
            $table->string('migration');
            $table->integer('batch');
        });
    }

    /**
     * 检查迁移表是否存在
     */
    protected function hasMigrationTable(): bool
    {
        try {
            $stmt = $this->connection->query("SELECT 1 FROM {$this->table} LIMIT 1");
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * 设置迁移路径
     */
    public function setPath(string $path): self
    {
        $this->path = $path;
        return $this;
    }

    /**
     * 获取迁移路径
     */
    public function getPath(): string
    {
        return $this->path;
    }
}
