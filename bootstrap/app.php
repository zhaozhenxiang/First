<?php

declare(strict_types=1);

/**
 * 应用装配入口
 *
 * 此文件创建并配置 App 实例，供 HTTP/CLI Kernel 使用。
 */

require_once __DIR__ . '/../bin/autoload.php';

use Bin\App\App;

return App::configure(dirname(__DIR__))
    ->withProviders()
    ->withRouting()
    ->withMiddleware()
    ->create();
