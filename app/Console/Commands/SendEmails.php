<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Bin\Console\Command;

/**
 * SendEmails 命令
 */
class SendEmails extends Command
{
    public string $signature = 'send:emails {argument}';

    public string $description = 'Command description';

    public function execute(): int
    {
        $argument = $this->argument('argument');

        $this->info("Hello from SendEmails! {$argument}");

        return 0;
    }
}
