<?php

declare(strict_types=1);

namespace Bin\Console\Commands;

use Bin\Console\Command;
use Bin\Database\Seeders\SeederCreator;

/**
 * 创建 Seeder 命令
 */
class MakeSeederCommand extends Command
{
    public string $signature = 'make:seeder {name}';
    public string $description = 'Create a new database seeder';

    public function execute(): int
    {
        $name = $this->argument('name');

        if (empty($name)) {
            $this->error('Seeder name is required.');
            return 1;
        }

        // 验证名称格式
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) {
            $this->error('Invalid seeder name. Use only letters, numbers and underscores.');
            return 1;
        }

        try {
            $creator = new SeederCreator();
            $path = $creator->create($name);

            $this->success("Seeder created successfully: {$path}");

            // 显示使用提示
            $className = $name . 'Seeder';
            $this->newLine();
            $this->comment('To run this seeder, use:');
            $this->line("  php command db:seed {$name}");
            $this->line("  php command db:seed Database\\Seeders\\{$className}");

            return 0;
        } catch (\Throwable $e) {
            $this->error("Failed to create seeder: {$e->getMessage()}");
            return 1;
        }
    }
}
