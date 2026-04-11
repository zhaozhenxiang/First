<?php

declare(strict_types=1);

/**
 * 应用装配入口
 *
 * 此文件是应用生命周期的新入口点。
 * 它创建 App 实例并返回，供 HTTP/CLI Kernel 使用。
 *
 * 用法:
 *   $app = require __DIR__ . '/../bootstrap/app.php';
 *   $kernel = $app->getHttpKernel();
 *   $response = $kernel->handle($request);
 */

require_once __DIR__ . '/../bin/autoload.php';

use Bin\App\App;

$app = App::getInstance();

return $app;
