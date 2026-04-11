<?php

declare(strict_types=1);

define('START_TIME', time());

/**
 * HTTP 入口文件
 *
 * 职责：加载应用 → 获取 HTTP 内核 → 处理请求 → 输出响应
 * 不负责：路由加载、中间件配置、Provider 注册（这些由 Kernel 管理）
 */

$app = require __DIR__ . '/../bootstrap/app.php';

$kernel = new \Bin\Foundation\HttpKernel($app);

$response = $kernel->handle();

echo (string)$response;
