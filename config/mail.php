<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | 默认邮件驱动
    |--------------------------------------------------------------------------
    */
    'default' => env('MAIL_MAILER', 'array'),

    /*
    |--------------------------------------------------------------------------
    | 邮件驱动配置
    |--------------------------------------------------------------------------
    */
    'mailers' => [

        'smtp' => [
            'driver' => 'smtp',
            'host' => env('MAIL_HOST', 'localhost'),
            'port' => (int) env('MAIL_PORT', 587),
            'encryption' => env('MAIL_ENCRYPTION', 'tls'),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => 30,
        ],

        'sendmail' => [
            'driver' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs'),
        ],

        'array' => [
            'driver' => 'array',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | 全局发件人地址
    |--------------------------------------------------------------------------
    */
    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', 'Example'),
    ],

];
