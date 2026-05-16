<?php

declare(strict_types=1);

namespace Bin\Console\Commands;

use Bin\App\App;
use Bin\Console\Command;
use Bin\Foundation\Bootstrap\LoadRoutes;
use Bin\Route\RouteCollection;

class RouteListCommand extends Command
{
    protected string $signature = 'route:list';

    protected string $description = 'List registered routes';

    public function execute(): int
    {
        $app = App::getInstance();

        if (!$app->hasBeenBootstrappedBy(LoadRoutes::class)) {
            $app->bootstrapWith([LoadRoutes::class]);
        }

        $rows = array_map(static fn (array $route): array => [
            $route['method'],
            $route['uri'],
            $route['name'],
            $route['action'],
            $route['middleware'],
        ], RouteCollection::routeTable());

        $this->table(['Method', 'URI', 'Name', 'Action', 'Middleware'], $rows);

        return 0;
    }
}
