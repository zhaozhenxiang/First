<?php

declare(strict_types=1);

namespace Bin\Foundation\Bootstrap;

use Bin\App\App;
use Bin\Foundation\Contracts\Bootstrapper as BootstrapperContract;

/**
 * 加载配置
 *
 * 将 config/ 目录下的配置文件注册到 ConfigRepository
 */
class LoadConfiguration implements BootstrapperContract
{
    public function bootstrap(App $app): void
    {
        $configPath = $app->configPath();

        if (!is_dir($configPath)) {
            return;
        }

        // 配置文件通过 config() 辅助函数按需加载
        // 这里只确保配置目录存在且注册了 ConfigRepository
        if (!$app->bound('config')) {
            $app->singleton('config', \Bin\Config\ConfigRepository::class);
        }
    }
}
