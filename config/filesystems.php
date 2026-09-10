<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | 默认文件系统磁盘
    |--------------------------------------------------------------------------
    */
    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | 文件系统磁盘配置
    |--------------------------------------------------------------------------
    */
    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL') . '/storage',
        ],

        // FTP 磁盘（基于内置 ftp:// 流包装器，无需扩展）：
        // 'ftp' => [
        //     'driver' => 'ftp',
        //     'host' => env('FTP_HOST'),
        //     'username' => env('FTP_USERNAME'),
        //     'password' => env('FTP_PASSWORD'),
        //     'port' => 21,
        //     'root' => '/',
        //     'ssl' => false,
        // ],

    ],

];
