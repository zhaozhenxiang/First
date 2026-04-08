# Spec: route-model-binding

## 显式绑定

```php
Route::model('user', User::class);           // 自动 User::findOrFail($id)
Route::bind('user', fn($value) => User::where('email', $value)->firstOrFail());
```

## 隐式绑定

在控制器方法或闭包中通过类型提示自动解析：

```php
Route::get('/users/{user}', function(User $user) { ... });
// $user 自动解析为 User::findOrFail($id)
```

## RouteBinding 类

- `static registerModel(string $key, string $class, ?callable $callback)`
- `static registerBinder(string $key, callable $resolver)`
- `static resolve(string $key, mixed $value): mixed`
- 解析失败时抛 404
