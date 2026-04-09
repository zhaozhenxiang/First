# facade-registry Spec

## Facade 基类行为

### 解析流程
1. `__callStatic($method, $args)` 被调用
2. `getInstance()` 获取底层实例
3. 底层实例通过容器 `$container->make($className)` 解析
4. 结果缓存到 `$instances[static::class]`

### 测试支持
- `Facade::setInstance($instance)` — 注入 mock
- `Facade::clear()` — 清除所有缓存
- `Facade::clearFacade($name)` — 清除特定 Facade

## 各 Facade 规格

### Cache
```php
Bin\Facade\Cache::get('key');
Bin\Facade\Cache::set('key', 'value', 60);
Bin\Facade\Cache::remember('key', 60, fn() => 'value');
Bin\Facade\Cache::has('key');
Bin\Facade\Cache::delete('key');
Bin\Facade\Cache::flush();
```
底层：`CacheManager::getInstance()`

### Config
```php
Bin\Facade\Config::get('app.name');
Bin\Facade\Config::set('app.debug', true);
Bin\Facade\Config::has('app.name');
```
底层：`ConfigRepository`（singleton in container）

### DB
```php
Bin\Facade\DB::getConnection();
Bin\Facade\DB::table('users')->where('id', 1)->first();
```
底层：`ConnectionManager`（static）

### Log
```php
Bin\Facade\Log::info('message');
Bin\Facade\Log::error('error', ['context' => 'data']);
Bin\Facade\Log::debug('debug info');
Bin\Facade\Log::warning('warning');
```
底层：`LogManager::getInstance()->channelFor()`

### Session
```php
Bin\Facade\Session::get('key');
Bin\Facade\Session::set('key', 'value');
Bin\Facade\Session::has('key');
Bin\Facade\Session::flash('key', 'value');
Bin\Facade\Session::remove('key');
```
底层：`SessionManager`

### Auth
```php
Bin\Facade\Auth::user();
Bin\Facade\Auth::check();
Bin\Facade\Auth::guest();
Bin\Facade\Auth::attempt(['email' => 'test@test.com']);
Bin\Facade\Auth::login($user);
Bin\Facade\Auth::logout();
Bin\Facade\Auth::id();
```
底层：`AuthManager::getInstance()`

### Gate
```php
Bin\Facade\Gate::check('edit-post', $post);
Bin\Facade\Gate::any(['edit', 'delete'], $post);
Bin\Facade\Gate::define('edit-post', fn($user, $post) => $user->id === $post->user_id);
```
底层：`Gate::getInstance()`

### Hash
```php
Bin\Facade\Hash::make('password');
Bin\Facade\Hash::check('password', $hash);
Bin\Facade\Hash::needsRehash($hash);
```
底层：`HashManager`（纯静态）

### Route
```php
Bin\Facade\Route::get('/path', 'Controller@method');
Bin\Facade\Route::post('/path', 'Controller@method');
Bin\Facade\Route::group(['prefix' => 'admin'], fn() => ...);
Bin\Facade\Route::resource('posts', 'PostController');
```
底层：`RouteCollection`（纯静态）

### URL
```php
Bin\Facade\URL::to('posts');
Bin\Facade\URL::route('posts.show', ['id' => 1]);
```
底层：`RouteCollection`（同 Route）

### View
```php
Bin\Facade\View::make('pages.home');
Bin\Facade\View::make('pages.home')->with('title', 'Home');
```
底层：`View`（每次 new）

### Validator
```php
Bin\Facade\Validator::make($data, $rules);
Bin\Facade\Validator::validate($data, $rules);
```
底层：`ValidationManager`

### Cookie
```php
Bin\Facade\Cookie::get('name');
Bin\Facade\Cookie::set('name', 'value', 60);
Bin\Facade\Cookie::has('name');
Bin\Facade\Cookie::forget('name');
```
底层：`CookieManager`（纯静态）

### File（预留）
暂不实现，仅创建空壳 Facade。
