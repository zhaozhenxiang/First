<?php

declare(strict_types=1);

namespace Bin\Foundation\Bootstrap;

use Bin\App\App;
use Bin\Foundation\Contracts\Bootstrapper as BootstrapperContract;
use Bin\Route\RouteCollection;

class LoadRoutes implements BootstrapperContract
{
    public function bootstrap(App $app): void
    {
        $configuration = $app->getApplicationConfiguration();
        $routeFileEntries = $configuration->routeFileEntries();

        if ($routeFileEntries !== []) {
            $this->loadRouteFileEntries($routeFileEntries);
            return;
        }

        $routeFiles = $configuration->routeFiles();

        if ($routeFiles !== []) {
            $this->loadRouteFiles($routeFiles);
            return;
        }

        if ($configuration->hasRouteConfiguration()) {
            return;
        }

        $legacy = $app->appPath('routes.php');

        if (is_file($legacy)) {
            require $legacy;
        }
    }

    /**
     * @param array<int, array{path: string, type: string}> $routeFileEntries
     */
    private function loadRouteFileEntries(array $routeFileEntries): void
    {
        foreach ($routeFileEntries as $entry) {
            $routeFile = $entry['path'];

            if (!is_file($routeFile)) {
                continue;
            }

            if (($entry['type'] ?? 'extra') === 'api') {
                RouteCollection::group([
                    'prefix' => 'api',
                    'middleware_group' => 'api',
                ], static function () use ($routeFile): void {
                    require $routeFile;
                });
                continue;
            }

            require $routeFile;
        }
    }

    /**
     * @param array<int, string> $routeFiles
     */
    private function loadRouteFiles(array $routeFiles): void
    {
        foreach ($routeFiles as $routeFile) {
            if (is_file($routeFile)) {
                require $routeFile;
            }
        }
    }
}
