<?php

declare(strict_types=1);

namespace Bin\Foundation\Bootstrap;

use Bin\App\App;
use Bin\Foundation\Contracts\Bootstrapper as BootstrapperContract;

class LoadRoutes implements BootstrapperContract
{
    public function bootstrap(App $app): void
    {
        $routeFile = $app->basePath() . '/app/routes.php';

        if (file_exists($routeFile)) {
            require_once $routeFile;
        }
    }
}
