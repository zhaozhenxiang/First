<?php

declare(strict_types=1);

namespace Bin\Console\Commands;

use Bin\Console\Command;
use Bin\Database\Migrations\Migrator;

/**
 * Migrate 命令 - 运行数据库迁移
 */
class MigrateCommand extends Command
{
    protected string $signature = 'migrate {--force} {--path=}';

    protected string $description = 'Run database migrations';

    public function execute(): int
    {
        $force = $this->hasOption('force');
        $path = $this->option('path');

        if (!$force) {
            $this->warning('This will modify your database.');
            if (!$this->confirm('Are you sure you want to run migrations?')) {
                $this->comment('Migration cancelled.');
                return 0;
            }
        }

        $this->info('Running migrations...');

        try {
            $migrator = new Migrator();

            if ($path) {
                $migrator->setMigrationPath($path);
            }

            $migrator->run();

            $this->success('Migrations completed successfully.');
            return 0;
        } catch (\Exception $e) {
            $this->error('Migration failed: ' . $e->getMessage());
            return 1;
        }
    }
}
