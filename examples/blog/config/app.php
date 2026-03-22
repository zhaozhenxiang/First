<?php

declare(strict_types=1);

return [
    // 应用配置
    'name' => 'Blog Example',
    'env' => 'development',
    'debug' => true,
    'url' => 'http://localhost:8000',

    // 数据库配置
    'database' => [
        'driver' => 'sqlite',
        'sqlite' => [
            'path' => __DIR__ . '/database/blog.sqlite',
        ],
    ],
];
