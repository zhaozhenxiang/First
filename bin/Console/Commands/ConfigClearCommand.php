<?php

declare(strict_types=1);

namespace Bin\Console\Commands;

use Bin\Console\Command;

/**
 * ConfigClear 命令 - 删除编译配置缓存
 */
class ConfigClearCommand extends Command
{
    protected string $signature = 'config:clear';

    protected string $description = 'Remove the compiled config cache files';

    public function execute(): int
    {
        $compiledPath = basePath('/storage/config');

        if (!is_dir($compiledPath)) {
            $this->info('No config cache to clear.');
            return 0;
        }

        $files = glob($compiledPath . '/*.php');
        $count = 0;

        foreach ($files as $file) {
            unlink($file);
            $count++;
        }

        $this->success("Config cache cleared ({$count} files removed).");

        return 0;
    }
}
