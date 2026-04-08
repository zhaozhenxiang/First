# Spec: route-groups

## 新增 group 属性

### namespace

```php
Route::group(['namespace' => 'App\Controllers\Admin'], function() {
    Route::get('/dashboard', 'AdminController@dashboard');
});
```

Action 前缀自动加上 namespace。

### domain

```php
Route::group(['domain' => '{account}.example.com'], function() {
    Route::get('/dashboard', ...);
});
```

子域名路由，{account} 参数可用。

## 已修复
- group() 回调只调用一次
- prefix 正确应用到路由路径
