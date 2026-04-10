<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | 默认日志通道
    |--------------------------------------------------------------------------
    |
    | 支持：single / daily / syslog / errorlog
    |
    */
    'default' => env('LOG_CHANNEL', 'single'),

    /*
    |--------------------------------------------------------------------------
    | 日志通道配置
    |--------------------------------------------------------------------------
    */
    'channels' => [

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/app.log'),
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/app.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => env('LOG_DAILY_DAYS', 14),
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => env('LOG_LEVEL', 'debug'),
        ],

    ],

];
