<?php

declare(strict_types=1);

namespace Bin\Console\Commands;

use Bin\App\App;
use Bin\Console\Command;
use Bin\Foundation\Bootstrap\LoadRoutes;
use Bin\Route\RouteCache;
use Bin\Route\RouteCollection;
use RuntimeException;

class RouteCacheCommand extends Command
{
    protected string $signature = 'route:cache';

    protected string $description = 'Create a route cache file for faster route registration';

    public function execute(): int
    {
        $app = App::getInstance();

        RouteCache::clear($app);
        RouteCollection::clear();

        try {
            (new LoadRoutes())->bootstrap($app);
            $payload = RouteCollection::exportForCache();
            RouteCache::write($payload, $app);
        } catch (RuntimeException $exception) {
            RouteCache::clear($app);
            $this->error($exception->getMessage());

            return 1;
        }

        $count = count($payload['routes']) + ($payload['fallback'] === null ? 0 : 1);
        $this->success("Route cache generated ({$count} routes).");

        return 0;
    }
}
