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

// 配置数据库（示例使用 SQLite；模型连接需要一个 PDO 实例）
$databasePath = $config['database']['sqlite']['path'];
\Bin\Database\Model::setConnection(new \PDO('sqlite:' . $databasePath));

// 加载路由
require_once __DIR__ . '/../app/routes/web.php';

// 执行路由
\Bin\Route\RouteAction::action();
