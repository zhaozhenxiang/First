<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | 默认队列连接
    |--------------------------------------------------------------------------
    */
    'default' => env('QUEUE_CONNECTION', 'sync'),

    /*
    |--------------------------------------------------------------------------
    | 队列连接配置
    |--------------------------------------------------------------------------
    */
    'connections' => [

        'sync' => [
            'driver' => 'sync',
        ],

        'database' => [
            'driver' => 'database',
            'connection' => 'default',
            'table' => 'jobs',
            'failed_table' => 'failed_jobs',
            'retry_after' => 90,
        ],

        // Redis 驱动要求 ext-redis（与 cache.redis 存储同一策略）：
        // 'redis' => [
        //     'driver' => 'redis',
        //     'host' => env('REDIS_HOST', '127.0.0.1'),
        //     'port' => env('REDIS_PORT', 6379),
        //     'password' => env('REDIS_PASSWORD'),
        //     'database' => env('REDIS_DB', 0),
        //     'prefix' => 'queues:',
        // ],

    ],

    /*
    |--------------------------------------------------------------------------
    | 失败任务配置
    |--------------------------------------------------------------------------
    */
    'failed' => [
        'database' => 'default',
        'table' => 'failed_jobs',
    ],

];
