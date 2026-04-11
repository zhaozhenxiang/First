<?php

declare(strict_types=1);

namespace Bin\Foundation\Bootstrap;

use Bin\App\App;
use Bin\Foundation\Contracts\Bootstrapper as BootstrapperContract;

/**
 * 加载环境变量
 *
 * 从 .env 文件加载环境配置
 */
class LoadEnvironmentVariables implements BootstrapperContract
{
    public function bootstrap(App $app): void
    {
        $envFile = $app->basePath() . '/.env';

        if (file_exists($envFile)) {
            \Bin\Config\EnvLoader::load($envFile);
        }
    }
}
