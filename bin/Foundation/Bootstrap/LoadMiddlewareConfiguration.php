<?php

declare(strict_types=1);

namespace Bin\Foundation\Bootstrap;

use Bin\App\App;
use Bin\Foundation\Contracts\Bootstrapper as BootstrapperContract;
use Bin\Middleware\MiddlewareStack;

class LoadMiddlewareConfiguration implements BootstrapperContract
{
    public function bootstrap(App $app): void
    {
        $configPath = $app->configPath('middleware.php');

        if (!file_exists($configPath)) {
            return;
        }

        $config = require $configPath;
        MiddlewareStack::loadFromConfig($config);
    }
}
