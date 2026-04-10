<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | CORS 跨域配置
    |--------------------------------------------------------------------------
    */

    /*
    | 允许的跨域路径
    */
    'paths' => ['api/*'],

    /*
    | 允许的请求方法
    */
    'allowed_methods' => ['*'],

    /*
    | 允许的请求来源
    */
    'allowed_origins' => ['*'],

    /*
    | 允许的正则匹配来源
    */
    'allowed_origins_patterns' => [],

    /*
    | 允许的请求头
    */
    'allowed_headers' => ['*'],

    /*
    | 暴露给前端的响应头
    */
    'exposed_headers' => [],

    /*
    | 是否允许携带凭证（Cookie）
    */
    'supports_credentials' => false,

    /*
    | 预检请求缓存时间（秒）
    */
    'max_age' => 0,

];
