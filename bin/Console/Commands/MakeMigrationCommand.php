<?php

declare(strict_types=1);

namespace Bin\Console\Commands;

/**
 * 创建 Migration 命令
 *
 * php command make:migration create_posts_table
 * php command make:migration add_status_to_posts --table=posts
 * php command make:migration create_users_table --create=users
 */
class MakeMigrationCommand extends MakeCommand
{
    public string $signature = 'make:migration {name} {--create=} {--table=}';
    public string $description = 'Create a new migration file';

    protected function getTargetPath(string $name): string
    {
        $timestamp = date('Y_m_d_His');
        $dir = basePath('database/migrations');

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return $dir . "/{$timestamp}_{$name}.php";
    }

    protected function getStubFile(): string
    {
        $create = $this->option('create');
        $table = $this->option('table');

        if ($create !== null) {
            return 'migration.create.stub';
        }
        if ($table !== null) {
            return 'migration.update.stub';
        }
        return 'migration.blank.stub';
    }

    protected function getReplacements(string $name): array
    {
        $create = $this->option('create');
        $table = $this->option('table');
        $resolvedTable = $create ?? $table ?? '';

        $description = str_replace('_', ' ', $name);

        return [
            '{{ table }}' => $resolvedTable,
            '{{ description }}' => ucfirst($description),
        ];
    }

    /**
     * Migration 不需要名称验证（允许 snake_case）
     */
    protected function validateName(string $name): void
    {
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
            $this->error("Invalid migration name '{$name}'. Use snake_case (e.g. create_posts_table).");
            exit(1);
        }
    }
}
