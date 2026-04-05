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
    protected Migrator $migrator;

    protected MigrationCreator $creator;

    protected string $migrationsPath;

    public function __construct()
    {
        $this->migrationsPath = basePath('/database/migrations');
        $this->migrator = new Migrator($this->migrationsPath);
        $this->creator = new MigrationCreator($this->migrationsPath);
    }

    /**
     * 运行命令
     */
    public function run(array $argv): void
    {
        $command = $argv[1] ?? 'help';

        match ($command) {
            'migrate' => $this->migrate(),
            'rollback' => $this->rollback($argv[2] ?? null),
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
    protected function migrate(): void
    {
        echo "Running migrations...\n\n";

        try {
            $this->migrator->run();
        } catch (\Exception $e) {
            echo "Error: {$e->getMessage()}\n";
            exit(1);
        }
    }

    /**
     * 回滚迁移
     */
    protected function rollback(?string $option): void
    {
        echo "Rolling back migrations...\n\n";

        try {
            if ($option === 'step' && isset($argv[3])) {
                $steps = (int) $argv[3];
                $this->migrator->rollback($steps);
            } else {
                $this->migrator->rollback();
            }
        } catch (\Exception $e) {
            echo "Error: {$e->getMessage()}\n";
            exit(1);
        }
    }

    /**
     * 回滚所有迁移
     */
    protected function reset(): void
    {
        echo "Resetting migrations...\n\n";

        try {
            $this->migrator->reset();
        } catch (\Exception $e) {
            echo "Error: {$e->getMessage()}\n";
            exit(1);
        }
    }

    /**
     * 回滚并重新运行
     */
    protected function refresh(): void
    {
        echo "Refreshing migrations...\n\n";

        try {
            $this->migrator->refresh();
        } catch (\Exception $e) {
            echo "Error: {$e->getMessage()}\n";
            exit(1);
        }
    }

    /**
     * 删除所有表并重新运行
     */
    protected function fresh(): void
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

            $this->migrator->run();
        } catch (\Exception $e) {
            Schema::enableForeignKeyConstraints();
            echo "Error: {$e->getMessage()}\n";
            exit(1);
        }
    }

    /**
     * 查看迁移状态
     */
    protected function status(): void
    {
        echo "Migration status:\n\n";

        $files = $this->migrator->getMigrationFiles();

        $ran = $this->migrator->getRanMigrations();

        foreach ($files as $file) {
            $basename = basename($file, '.php');
            $status = in_array($basename, $ran, true) ? '✓' : '✗';
            echo "  {$status} {$basename}\n";
        }
    }

    /**
     * 创建迁移文件
     */
    protected function make(?string $name, ?string $table): void
    {
        if ($name === null) {
            echo "Error: Migration name is required.\n";
            echo "Usage: php migrate make:create_migration_name [table]\n";
            exit(1);
        }

        try {
            $path = $this->creator->create($name, $table);
            echo "Created migration: {$path}\n";
        } catch (\Exception $e) {
            echo "Error: {$e->getMessage()}\n";
            exit(1);
        }
    }

    /**
     * 未知命令
     */
    protected function unknownCommand(string $command): void
    {
        echo "Unknown command: {$command}\n\n";
        $this->help();
    }

    /**
     * 显示帮助
     */
    protected function help(): void
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
    }
}

// 运行命令
(new MigrateCommand())->run($argv);
