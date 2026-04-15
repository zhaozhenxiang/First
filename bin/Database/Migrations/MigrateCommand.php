#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * 迁移命令行工具
 *
 * 用法:
 * php migrate migrate              # 运行所有待执行的迁移
 * php migrate rollback             # 回滚最后一次迁移
 * php migrate rollback:step 3      # 回滚最近3次迁移
 * php migrate reset                # 回滚所有迁移
 * php migrate refresh              # 回滚并重新运行所有迁移
 * php migrate fresh                # 删除所有表并重新运行迁移
 * php migrate status               # 查看迁移状态
 * php migrate make:create_users    # 创建迁移文件
 * php migrate make:create_posts posts  # 创建指定表的迁移
 */

require_once __DIR__ . '/../../autoload.php';

use Bin\Database\Migrations\Migrator;
use Bin\Database\Migrations\MigrationCreator;
use Bin\Database\Schema\Schema;

class MigrateCommand
{
    protected ?Migrator $migrator = null;

    protected ?MigrationCreator $creator = null;

    protected string $migrationsPath;

    public function __construct()
    {
        $this->migrationsPath = basePath('/database/migrations');
    }

    /**
     * 运行命令
     */
    public function run(array $argv): int
    {
        $command = $argv[1] ?? 'help';

        return match ($command) {
            'migrate' => $this->migrate(),
            'rollback' => $this->rollback($argv[2] ?? null, $argv[3] ?? null),
            'reset' => $this->reset(),
            'refresh' => $this->refresh(),
            'fresh' => $this->fresh(),
            'status' => $this->status(),
            'make' => $this->make($argv[2] ?? null, $argv[3] ?? null),
            'help', '-h', '--help' => $this->help(),
            default => $this->unknownCommand($command),
        };
    }

    /**
     * 运行待执行的迁移
     */
    protected function migrate(): int
    {
        echo "Running migrations...\n\n";

        try {
            $this->getMigrator()->run();
            return 0;
        } catch (\Exception $e) {
            echo "Error: {$e->getMessage()}\n";
            return 1;
        }
    }

    /**
     * 回滚迁移
     */
    protected function rollback(?string $option, ?string $value = null): int
    {
        echo "Rolling back migrations...\n\n";

        try {
            if ($option === 'step' && $value !== null) {
                $steps = (int) $value;
                $this->getMigrator()->rollback($steps);
            } else {
                $this->getMigrator()->rollback();
            }
            return 0;
        } catch (\Exception $e) {
            echo "Error: {$e->getMessage()}\n";
            return 1;
        }
    }

    /**
     * 回滚所有迁移
     */
    protected function reset(): int
    {
        echo "Resetting migrations...\n\n";

        try {
            $this->getMigrator()->reset();
            return 0;
        } catch (\Exception $e) {
            echo "Error: {$e->getMessage()}\n";
            return 1;
        }
    }

    /**
     * 回滚并重新运行
     */
    protected function refresh(): int
    {
        echo "Refreshing migrations...\n\n";

        try {
            $this->getMigrator()->refresh();
            return 0;
        } catch (\Exception $e) {
            echo "Error: {$e->getMessage()}\n";
            return 1;
        }
    }

    /**
     * 删除所有表并重新运行
     */
    protected function fresh(): int
    {
        echo "Dropping all tables...\n\n";

        try {
            Schema::disableForeignKeyConstraints();

            $tables = Schema::getTables();

            foreach ($tables as $table) {
                if ($table !== 'migrations') {
                    Schema::dropIfExists($table);
                    echo "Dropped table: {$table}\n";
                }
            }

            Schema::enableForeignKeyConstraints();

            echo "\nRunning migrations...\n\n";

            $this->getMigrator()->run();
            return 0;
        } catch (\Exception $e) {
            Schema::enableForeignKeyConstraints();
            echo "Error: {$e->getMessage()}\n";
            return 1;
        }
    }

    /**
     * 查看迁移状态
     */
    protected function status(): int
    {
        echo "Migration status:\n\n";

        $files = $this->getMigrator()->getMigrationFiles();

        $ran = $this->getMigrator()->getRanMigrations();

        foreach ($files as $file) {
            $basename = basename($file, '.php');
            $status = in_array($basename, $ran, true) ? '✓' : '✗';
            echo "  {$status} {$basename}\n";
        }

        return 0;
    }

    /**
     * 创建迁移文件
     */
    protected function make(?string $name, ?string $table): int
    {
        if ($name === null) {
            echo "Error: Migration name is required.\n";
            echo "Usage: php migrate make:create_migration_name [table]\n";
            return 1;
        }

        try {
            $path = $this->getCreator()->create($name, $table);
            echo "Created migration: {$path}\n";
            return 0;
        } catch (\Exception $e) {
            echo "Error: {$e->getMessage()}\n";
            return 1;
        }
    }

    /**
     * 未知命令
     */
    protected function unknownCommand(string $command): int
    {
        echo "Unknown command: {$command}\n\n";
        return $this->help(1);
    }

    /**
     * 显示帮助
     */
    protected function help(int $exitCode = 0): int
    {
        echo <<<HELP
Migration Command Tool

Usage:
  php migrate [command] [options]

Commands:
  migrate                    Run all pending migrations
  rollback                   Rollback the last migration
  reset                      Rollback all migrations
  refresh                    Rollback and re-run all migrations
  fresh                      Drop all tables and re-run migrations
  status                     Show migration status
  make:<name> [table]        Create a new migration file

Examples:
  php migrate migrate                     # Run migrations
  php migrate rollback                    # Rollback last migration
  php migrate refresh                     # Refresh all migrations
  php migrate status                      # Show status
  php migrate make:create_users           # Create migration file
  php migrate make:create_posts posts     # Create migration for table

HELP;

        return $exitCode;
    }

    protected function getMigrator(): Migrator
    {
        return $this->migrator ??= new Migrator($this->migrationsPath);
    }

    protected function getCreator(): MigrationCreator
    {
        return $this->creator ??= new MigrationCreator($this->migrationsPath);
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    exit((new MigrateCommand())->run($argv));
}
