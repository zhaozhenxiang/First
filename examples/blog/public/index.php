<?php

/**
 * Blog 示例应用入口文件
 */

// 加载框架
require_once __DIR__ . '/../../../bin/autoload.php';

// 加载配置
$config = require __DIR__ . '/../config/app.php';

// 设置基础路径
define('BASE_PATH', __DIR__ . '/../../..');

// 配置数据库
\Bin\Model\Model::setConnection([
    'driver' => 'sqlite',
    'database' => $config['database']['sqlite']['path'],
]);

// 加载路由
require_once __DIR__ . '/../app/routes/web.php';

// 执行路由
\Bin\Route\RouteAction::action();
