<?php

declare(strict_types=1);

use Bin\Route\RouteCollection as Route;

// 加载配置
config(require __DIR__ . '/../../config/app.php');

// -------------------------------------------------------------------------
// Web 路由
// -------------------------------------------------------------------------

// 首页
Route::get('/', function () {
    return view('welcome.php');
});

// 认证路由
Route::get('/login', 'AuthController@loginForm');
Route::post('/login', 'AuthController@login');
Route::post('/logout', 'AuthController@logout');

// 文章路由 (需要认证)
Route::middle(['auth' => []], function () {
    // 文章列表
    Route::get('/posts', 'PostController@index');

    // 文章详情
    Route::get('/posts/{id}', 'PostController@show')->with('[0-9]+');

    // 创建文章
    Route::get('/posts/create', 'PostController@create');
    Route::post('/posts', 'PostController@store');
});

// -------------------------------------------------------------------------
// API 路由
// -------------------------------------------------------------------------

Route::prefix('/api', function () {
    // API 认证
    Route::post('/login', 'AuthController@apiLogin');
    Route::post('/logout', 'AuthController@apiLogout');

    // API 文章路由
    Route::get('/posts', 'PostController@apiIndex');
    Route::get('/posts/{id}', 'PostController@apiShow')->with('[0-9]+');
});
