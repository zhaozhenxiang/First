<?php

declare(strict_types=1);

namespace Bin\Foundation\Bootstrap;

use Bin\App\App;
use Bin\Foundation\Contracts\Bootstrapper as BootstrapperContract;

/**
 * 启动服务提供者
 *
 * 调用所有已注册提供者的 boot() 方法
 */
class BootProviders implements BootstrapperContract
{
    public function bootstrap(App $app): void
    {
        $app->boot();
    }
}
