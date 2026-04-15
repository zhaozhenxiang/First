<?php

declare(strict_types=1);

namespace Bin\Console\Commands;

use Bin\Console\Command;
use Bin\Console\Kernel;

/**
 * List 命令 - 列出所有可用命令
 */
class ListCommand extends Command
{
    protected string $signature = 'list';

    protected string $description = 'List all available commands';

    public function execute(): int
    {
        $commands = Kernel::getCommands();

        $rows = [];
        foreach ($commands as $name => $command) {
            $rows[] = [$name, $command->getDescription() ?: ''];
        }

        $this->table(['Command', 'Description'], $rows);

        return 0;
    }
}
