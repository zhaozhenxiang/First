<?php

declare(strict_types=1);

namespace Bin\Console\Commands;

/**
 * 创建 Model 命令
 *
 * php command make:model Post
 * php command make:model Post --no-migration
 */
class MakeModelCommand extends MakeCommand
{
    public string $signature = 'make:model {name} {--no-migration}';
    public string $description = 'Create a new Eloquent model class';

    protected function getTargetPath(string $name): string
    {
        return $this->getBaseDirectory() . '/' . str_replace('\\', '/', $name) . '.php';
    }

    protected function getStubFile(): string
    {
        return 'model.stub';
    }

    protected function getReplacements(string $name): array
    {
        $className = $this->className($name);
        $table = $this->tableName($className);

        return array_merge(parent::getReplacements($name), [
            '{{ table }}' => $table,
        ]);
    }

    protected function getBaseNamespace(): string
    {
        return 'App\\Model';
    }

    protected function getBaseDirectory(): string
    {
        return basePath('app/Model');
    }

    protected function afterCreate(string $name, string $path): void
    {
        if ($this->hasOption('no-migration')) {
            return;
        }

        // 自动创建 migration
        $className = $this->className($name);
        $tableName = $this->tableName($className);

        $migrationName = "create_{$tableName}_table";
        $migrationPath = $this->createMigration($migrationName, $tableName);

        if ($migrationPath !== '') {
            $this->info("Migration created: {$migrationPath}");
        }
    }

    /**
     * 从模型名推导表名
     */
    private function tableName(string $className): string
    {
        // Post → posts, Category → categories
        if (preg_match('/[b-df-hj-np-tv-z]y$/i', $className)) {
            return strtolower(substr($className, 0, -1)) . 'ies';
        }
        if (preg_match('/(?:s|x|z|sh|ch)$/i', $className)) {
            return strtolower($className) . 'es';
        }
        return strtolower($className) . 's';
    }

    /**
     * 创建迁移文件
     */
    private function createMigration(string $name, string $table): string
    {
        $timestamp = date('Y_m_d_His');
        $fileName = "{$timestamp}_{$name}.php";
        $dir = basePath('database/migrations');

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = $dir . '/' . $fileName;

        if (file_exists($path)) {
            return '';
        }

        $stub = $this->renderStub('migration.create.stub', [
            '{{ table }}' => $table,
            '{{ description }}' => "Create {$table} table",
        ]);

        file_put_contents($path, $stub);

        return $path;
    }
}
