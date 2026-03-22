<?php

declare(strict_types=1);

namespace Bin\Console\Commands;

use Bin\Console\Command;
use Bin\Database\Seeders\SeederRepository;

/**
 * 数据库填充命令
 */
class SeedCommand extends Command
{
    public string $signature = 'db:seed {class?} {--force}';
    public string $description = 'Run database seeders to populate tables with sample data';

    public function execute(): int
    {
        $class = $this->argument('class');
        $force = $this->option('force', false);

        // 确认操作（除非使用 --force）
        if (!$force && !$this->confirm('This will seed the database. Are you sure?', true)) {
            $this->warning('Seeding cancelled.');
            return 1;
        }

        $this->info('Seeding database...');

        try {
            if ($class !== null) {
                // 运行指定 Seeder
                $this->info("Running seeder: {$class}");
                SeederRepository::run($class);
                $this->success("Seeder [{$class}] completed successfully.");
            } else {
                // 运行所有 Seeder
                $seeders = SeederRepository::all();

                if (empty($seeders)) {
                    $this->warning('No seeders found.');
                    return 0;
                }

                $this->info('Running all seeders...');
                SeederRepository::runAll();
                $this->success('All seeders completed successfully.');
            }

            return 0;
        } catch (\Throwable $e) {
            $this->error("Seeding failed: {$e->getMessage()}");
            return 1;
        }
    }
}
