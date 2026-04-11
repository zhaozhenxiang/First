<?php

declare(strict_types=1);

namespace Bin\Foundation\Bootstrap;

use Bin\App\App;
use Bin\Foundation\Contracts\Bootstrapper as BootstrapperContract;

/**
 * 设置请求上下文
 *
 * 为 HTTP 请求设置运行时上下文：时区、编码、请求开始时间等
 */
class SetRequestContext implements BootstrapperContract
{
    public function bootstrap(App $app): void
    {
        // 设置默认时区
        $timezone = env('APP_TIMEZONE', 'UTC');
        date_default_timezone_set($timezone);

        // 设置内部编码
        mb_internal_encoding('UTF-8');
    }
}
