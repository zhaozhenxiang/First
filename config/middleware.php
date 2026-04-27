<?php

declare(strict_types=1);

use Bin\Middleware\AuthMiddleware;
use Bin\Middleware\CsrfMiddleware;
use Bin\Middleware\GuestMiddleware;
use Bin\Middleware\RateLimitMiddleware;
use Bin\Middleware\SessionMiddleware;

/**
 * 中间件配置
 *
 * 参考 Laravel config/middleware.php 结构
 */
return [

    /*
    |--------------------------------------------------------------------------
    | 全局中间件
    |--------------------------------------------------------------------------
    |
    | 每个请求都会经过这些中间件，按数组顺序执行。
    |
    */
    'global' => [
        // CsrfMiddleware::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | 中间件组
    |--------------------------------------------------------------------------
    |
    | 路由可以指定使用某个组的所有中间件。
    | 例如 Route::group(['middleware_group' => 'web'], ...)
    |
    */
    'groups' => [
        'web' => [
            SessionMiddleware::class,
            CsrfMiddleware::class,
        ],

        'api' => [
            RateLimitMiddleware::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 中间件别名
    |--------------------------------------------------------------------------
    |
    | 路由中可以用短名引用中间件：
    |   Route::get('/profile', ...)->middleware('auth')
    |   Route::post('/data', ...)->middleware('throttle:60,1')
    |
    */
    'aliases' => [
        'session' => SessionMiddleware::class,
        'auth' => AuthMiddleware::class,
        'guest' => GuestMiddleware::class,
        'csrf' => CsrfMiddleware::class,
        'throttle' => RateLimitMiddleware::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | 中间件优先级
    |--------------------------------------------------------------------------
    |
    | 值越大越先执行。未列出的中间件优先级为 0。
    | 可用短名或完整类名。
    |
    */
    'priority' => [
        'session' => 30,
        SessionMiddleware::class => 30,
        'csrf' => 20,
        CsrfMiddleware::class => 20,
        'auth' => 10,
        AuthMiddleware::class => 10,
        'guest' => 10,
        GuestMiddleware::class => 10,
        'throttle' => 0,
        RateLimitMiddleware::class => 0,
    ],

];
