<?php

declare(strict_types=1);

namespace Bin\Console\Commands;

use Bin\Console\Command;

/**
 * Test 命令 - 运行测试
 */
class TestCommand extends Command
{
    protected string $signature = 'test {--filter=} {--stop-on-failure}';

    protected string $description = 'Run the application tests';

    public function execute(): int
    {
        $filter = $this->option('filter');
        $stopOnFailure = $this->hasOption('stop-on-failure');

        $this->info('Running tests...');

        $command = 'php test';

        if ($filter) {
            $command .= " --filter={$filter}";
        }

        if ($stopOnFailure) {
            $command .= ' --stop-on-failure';
        }

        passthru($command, $exitCode);

        return $exitCode ?? 0;
    }
}
