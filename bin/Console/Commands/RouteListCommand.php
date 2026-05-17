<?php

declare(strict_types=1);

namespace Bin\Console\Commands;

use Bin\App\App;
use Bin\Console\Command;
use Bin\Foundation\Bootstrap\LoadRoutes;
use Bin\Route\RouteCollection;

class RouteListCommand extends Command
{
    protected string $signature = 'route:list {--path=} {--name=} {--method=}';

    protected string $description = 'List registered routes';

    public function execute(): int
    {
        $app = App::getInstance();

        if (!$app->hasBeenBootstrappedBy(LoadRoutes::class)) {
            $app->bootstrapWith([LoadRoutes::class]);
        }

        $routes = array_filter(
            RouteCollection::routeTable(),
            fn (array $route): bool => $this->matchesFilters($route)
        );

        $rows = array_map(static fn (array $route): array => [
            $route['method'],
            $route['uri'],
            $route['name'],
            $route['action'],
            $route['middleware'],
        ], $routes);

        $this->table(['Method', 'URI', 'Name', 'Action', 'Middleware'], $rows);

        return 0;
    }

    /**
     * @param array{method: string, uri: string, name: string, action: string, middleware: string} $route
     */
    private function matchesFilters(array $route): bool
    {
        $path = $this->option('path');
        if (is_string($path) && $path !== '') {
            $path = $this->normalizePath($path);
            $routeUri = $this->normalizePath($route['uri']);

            if ($path !== '/' && $routeUri !== $path && !str_starts_with($routeUri, $path . '/')) {
                return false;
            }
        }

        $name = $this->option('name');
        if (is_string($name) && $name !== '' && !str_contains($route['name'], $name)) {
            return false;
        }

        $method = $this->option('method');
        if (is_string($method) && $method !== '' && strtoupper($route['method']) !== strtoupper($method)) {
            return false;
        }

        return true;
    }

    private function normalizePath(string $path): string
    {
        $path = trim($path, '/');

        return $path === '' ? '/' : '/' . $path;
    }
}
