<?php

declare(strict_types=1);

// 定义基础路径
define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');

// 自动加载函数
spl_autoload_register(function (string $class) {
    // 项目命名空间前缀映射
    $prefixes = [
        'Bin\\' => BASE_PATH . '/bin/',
        'App\\' => APP_PATH . '/',
        'Tests\\' => BASE_PATH . '/tests/',
    ];

    // 查找匹配的前缀
    foreach ($prefixes as $prefix => $baseDir) {
        if (str_starts_with($class, $prefix)) {
            // 移除前缀
            $relativeClass = substr($class, strlen($prefix));

            // 转换为文件路径
            $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

            if (file_exists($file)) {
                require_once $file;
                return true;
            }
        }
    }

    return false;
});

// 加载辅助函数
require_once BASE_PATH . '/bin/Func/helpers.php';

// 加载 .env 环境变量
\Bin\Config\EnvLoader::load(BASE_PATH . '/.env');

// 加载中间件配置
$middlewareConfig = file_exists(BASE_PATH . '/config/middleware.php')
    ? require BASE_PATH . '/config/middleware.php'
    : [];
\Bin\Middleware\MiddlewareStack::loadFromConfig($middlewareConfig);

// 加载路由（仅在 Web 请求时需要）
if (PHP_SAPI !== 'cli' && file_exists(APP_PATH . '/routes.php')) {
    require_once APP_PATH . '/routes.php';
}
