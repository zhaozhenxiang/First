<?php

declare(strict_types=1);

namespace Bin\Console\Commands;

use Bin\App\App;
use Bin\Console\Command;
use Bin\Route\RouteCache;

class RouteClearCommand extends Command
{
    protected string $signature = 'route:clear';

    protected string $description = 'Remove the route cache file';

    public function execute(): int
    {
        if (!RouteCache::clear(App::getInstance())) {
            $this->info('No route cache to clear.');

            return 0;
        }

        $this->success('Route cache cleared.');

        return 0;
    }
}
