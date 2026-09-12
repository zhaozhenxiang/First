<?php

declare(strict_types=1);

return [
    'default' => env('DB_CONNECTION', 'mysql'),
    'resultType' => PDO::FETCH_ASSOC,
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'user' => env('DB_USERNAME', 'root'),
            'pass' => env('DB_PASSWORD', ''),
            'dbname' => env('DB_DATABASE', 'test'),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
        ],

        // 示例：命名 sqlite 连接。database 为库文件路径，缺省内存库。
        // 使用方式：Model::on('sqlite')、Schema::connection('sqlite')、
        // new Migrator(path: ..., connection: 'sqlite')。
        'sqlite' => [
            'driver' => 'sqlite',
            'database' => env('DB_SQLITE_PATH', ':memory:'),
        ],
    ],
];
