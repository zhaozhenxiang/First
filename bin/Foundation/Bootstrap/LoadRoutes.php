<?php

declare(strict_types=1);

namespace Bin\Foundation\Bootstrap;

use Bin\App\App;
use Bin\Foundation\Contracts\Bootstrapper as BootstrapperContract;

class LoadRoutes implements BootstrapperContract
{
    public function bootstrap(App $app): void
    {
        $configuration = $app->getApplicationConfiguration();
        $routeFiles = $configuration->routeFiles();

        if ($routeFiles !== []) {
            $this->loadRouteFiles($routeFiles);
            return;
        }

        if ($configuration->hasRouteConfiguration()) {
            return;
        }

        $legacy = $app->basePath() . '/app/routes.php';

        if (is_file($legacy)) {
            require $legacy;
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
