#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * CLI 命令行入口
 *
 * 职责：加载应用 → 获取 Console 内核 → 处理命令
 */

$app = require __DIR__ . '/bootstrap/app.php';

$kernel = new \Bin\Foundation\ConsoleKernel($app);

exit($kernel->handle());
