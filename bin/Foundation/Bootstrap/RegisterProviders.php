<?php

declare(strict_types=1);

namespace Bin\Foundation\Bootstrap;

use Bin\App\App;
use Bin\Foundation\Contracts\Bootstrapper as BootstrapperContract;

/**
 * 注册服务提供者
 *
 * 将应用配置的服务提供者注册到容器中
 */
class RegisterProviders implements BootstrapperContract
{
    public function bootstrap(App $app): void
    {
        // 核心服务提供者已在 App 构造时注册
        // 这里可以加载额外的应用级提供者（如来自 config/app.php）
        $configPath = $app->configPath('app.php');

        if (file_exists($configPath)) {
            $config = require $configPath;
            $providers = $config['providers'] ?? [];

            foreach ($providers as $provider) {
                $app->register($provider);
            }
        }
    }
}
