<?php

declare(strict_types=1);

namespace Bin\Console\Commands;

use Bin\Cache\CacheManager;
use Bin\Console\Command;

/**
 * ClearCache 命令 - 清除缓存
 */
class ClearCacheCommand extends Command
{
    protected string $signature = 'cache:clear {--type=}';

    protected string $description = 'Clear the application cache';

    public function execute(): int
    {
        $type = $this->option('type', 'all');

        $this->info('Clearing cache...');

        switch ($type) {
            case 'file':
                CacheManager::getStore('file')->clear();
                break;
            case 'redis':
                CacheManager::getStore('redis')->clear();
                break;
            case 'array':
                CacheManager::getStore('array')->clear();
                break;
            case 'all':
            default:
                CacheManager::getStore('file')->clear();
                $this->info('File cache cleared.');
                break;
        }

        $this->success('Cache cleared successfully.');

        return 0;
    }
}
