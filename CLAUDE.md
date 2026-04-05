# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a custom PHP8.5 web framework inspired by Laravel, built with PHP 8.3+. The framework implements core MVC patterns with IoC/DI containers, routing, middleware, and database abstraction. 
## Architecture

### Entry Point
- `public/index.php` - Application entry point. Loads autoloader, includes routes, executes `RouteAction::action()`

### Directory Structure
- `bin/` - Framework core (PSR-4 namespace: `Bin\`)
  - `App/` - IoC container and service management
  - `Container/` - IoC 容器核心（Container, ContextualBindingBuilder, Exceptions）
  - `Route/` - Route collection, routing, and action dispatch
  - `Request/` - HTTP request handling with ArrayAccess/Iterator
  - `Response/` - HTTP response formatting (string/array/view)
  - `View/` - Template rendering with compiler
  - `Model/` - Database model base and query builder
  - `Middleware/` - Abstract middleware class
  - `Reflection/` - Dependency injection via reflection
  - `Facade/` - Static proxy pattern
  - `Psr/Container/` - PSR-11 接口定义
  - `Contracts/` - 框架接口约定
  - `Func/helpers.php` - Global helper functions
- `app/` - Application code (PSR-4 namespace: `App\`)
  - `Controllers/` - Controller classes
  - `Middleware/` - Custom middleware
  - `Model/` - Eloquent-like models
  - `routes.php` - Route definitions
- `config/` - Configuration files
- `views/` - Template files
- `vendor/` - Composer dependencies

### Request Flow

1. `public/index.php` → autoload → `app/routes.php`
2. `RouteAction::action()` matches route via `RouteCollection::getRoute()`
3. Middleware executes (if defined) - must return `true` to continue
4. Action dispatches to:
   - Closure with DI parameters via `Reflection::getCallBackParam()`
   - Controller method via `Reflection::getClassMethodParamInject()`
5. Response returned as string and echoed

### Route Definition

Routes are defined in `app/routes.php` using `RouteCollection`:

```php
use Bin\Route\RouteCollection as Route;

// Closure callback
Route::get('/path', function() { return 'response'; });

// Controller@method syntax
Route::get('/path', 'ControllerName@methodName');

// POST request
Route::post('/path', 'ControllerName@method');

// Parameter with regex validation
Route::get('/user/{id}', function($id) { return $id; })->with('[0-9]+');

// Multiple parameters
Route::get('/post/{id}/{comment}', function($id, $comment) { })
    ->with('[0-9]+')->with('[0-9]+');

// Middleware group
Route::middle(['middleware_name' => [param1, param2]], function() {
    Route::get('/protected', 'Controller@method');
});

// Bulk routes
Route::getArray(['/path1' => 'Controller@method1', '/path2' => 'Controller@method2']);
```

### Dependency Injection

The framework uses reflection for automatic dependency injection:

- **Controller methods**: Type-hinted parameters are auto-resolved from the IoC container
- **Closures**: Non-type-hinted parameters receive URL path parameters; type-hinted receive container instances
- **Constructor DI**: Use `app(ClassName::class)` or `App::make(ClassName::class)` for manual resolution

### IoC Container

框架包含两层容器架构：`Bin\App\App`（应用门面）和 `Bin\Container\Container`（底层容器）。

**注意**：PHP 8.5 不允许对已有实例方法使用静态调用，`__callStatic` 不会拦截。所有静态调用需通过 `App::getInstance()` 获取实例后调用。

```php
// 解析服务（推荐通过 app() 辅助函数）
$instance = app(ClassName::class);

// 或通过 App 门面
$instance = App::getInstance()->make(ClassName::class);
```

#### 绑定

```php
// 基础绑定（每次解析返回新实例）
$container->bind('service', ClassName::class);
$container->bind('service', function ($container) { return new ClassName(); });

// 单例绑定（多次解析返回同一实例）
$container->singleton('service', ClassName::class);

// 实例绑定
$container->instance('service', new ClassName());

// 条件绑定（仅在未绑定时绑定）
$container->bindIf('service', ClassName::class);
$container->singletonIf('service', ClassName::class);
```

#### 作用域绑定

```php
// 作用域内共享，resetScope() 后重新创建
$container->scoped('service', ClassName::class);
$container->resetScope();  // 重置所有作用域实例（不影响 singleton）
```

#### 标签绑定

```php
$container->bind('cache.redis', RedisCache::class);
$container->bind('cache.file', FileCache::class);
$container->tag(['cache.redis', 'cache.file'], 'cache');

$services = $container->tagged('cache'); // 返回所有标签下的实例
```

#### 上下文绑定

```php
// when()->needs()->give() 流畅接口
$container->when(AuditService::class)
    ->needs(LoggerInterface::class)
    ->give(function () { return new CloudLogger(); });

// 原始参数注入
$container->when(TimeoutService::class)
    ->needs('timeout')
    ->give(60);
```

#### 解析回调

```php
// 全局回调
$container->resolving(function ($object, $container) { /* ... */ });
$container->afterResolving(function ($object, $container) { /* ... */ });

// 特定抽象名回调
$container->resolving('service', function ($object, $container) { /* ... */ });
```

#### 重绑定回调

```php
$container->rebinding('cache', function ($container, $instance) {
    // 绑定重新注册时触发
});
```

#### 扩展器（装饰器模式）

```php
$container->extend('service', function ($instance, $container) {
    $instance->extra = 'decorated';
    return $instance;
});
```

#### 方法注入

```php
// 支持 Closure、Class@method、数组回调
$result = $container->call(ServiceImpl::class . '@handle', ['message' => 'hello']);
$result = $container->call([$instance, 'method'], ['param' => 'value']);
$result = $container->call(function (Logger $logger) { return $logger->name(); });
```

#### PSR-11 兼容

```php
// Container 实现了 Psr\Container\ContainerInterface
$has = $container->has('service');     // bool
$instance = $container->get('service'); // 解析或抛 NotFoundException
```

#### 循环依赖检测

容器自动检测循环依赖并抛出 `CircularDependencyException`。

#### 异常体系

- `BindingResolutionException` - 绑定/解析失败（含 `getAbstract()`）
- `CircularDependencyException` - 循环依赖（含 `getPath()`）
- `NotFoundException` - PSR-11 未找到条目

### Model (`Bin\Model\Model`)

Models extend `Bin\Model\Model`:

```php
class User extends Model
{
    protected $table = 'users';
}

// Query with raw SQL
User::select('SELECT * FROM users WHERE id = ?', [$id]);
```

Database config in `config/db.php`:
- `driver` - Database driver (mysql)
- `resultType` - PDO fetch mode (PDO::FETCH_ASSOC)
- `connection.{driver}` - Connection credentials

### View (`Bin\View\View`)

```php
return View::make('template.php')->with('key', $value);
```

Templates are stored in `views/` directory.

### Response (`Bin\Response\Response`)

```php
// String response
new Response('text');

// JSON response
new Response(['key' => 'value']);

// View response
new Response(View::make('template.php'));

// With status code
(new Response())->setStatus(200, 'content');
```

### Facade Pattern

Classes extending `Bin\Facade\Facade` provide static access to container instances. The `Request` facade is pre-registered:

```php
use Bin\Facade\Request;

\Request::getPath();  // proxies to app('Request')->getPath()
```

### Helper Functions (`bin/Func/helpers.php`)

- `getUrl()` - Get request URI
- `getMethod()` - Get request method
- `basePath()` - Get base path constant
- `config('database.default')` - Get nested config value
- `app($class)` - Resolve from IoC container
- `abort($code)` - Die with HTTP status code
- `getKeyByArray($needle, $arr, $key)` - Search 2D array by key

### Middleware

Extend `Bin\Middleware\Middleware`:

```php
class CustomMiddleware extends Middleware
{
    protected function handle(array $param): mixed
    {
        // Return true to continue, or any other value to abort
        return true;
    }
}
```

## Development Commands

```bash
# Start PHP built-in server (development)
php -S localhost:8000 -t public

# Regenerate autoload
composer dump-autoload
```

## Testing

```bash
# Run all tests
php test

# Run specific test file
php test tests/IocContainerTest.php
php test tests/IocBehaviorTest.php

# Run with verbose output
php test --verbose

# Stop on first failure
php test --stop-on-failure

# Filter tests by name
php test --filter=testBind
```

### IoC Container Tests

**单元测试** (`tests/IocContainerTest.php`)：32 个测试，直接实例化 Container 进行测试，覆盖：
- 标签绑定（tag/tagged）
- 解析回调（resolving/afterResolving）
- 重绑定回调（rebinding）
- 方法注入（call Class@method, 数组回调, 闭包 DI）
- 作用域绑定（scoped/resetScope）
- 条件绑定（when/needs/give）
- PSR-11 兼容（has/get/异常）
- 异常体系（BindingResolution/CircularDependency）
- 条件注册（bindIf/singletonIf）

**行为测试** (`tests/IocBehaviorTest.php`)：14 个测试，启动 PHP 内置服务器通过 HTTP 请求端到端验证，对应路由：

| 路由 | 验证功能 |
|------|---------|
| `/ioc/bind` | bind 每次返回新实例 |
| `/ioc/singleton` | singleton 返回同一实例 |
| `/ioc/tagged` | 标签批量解析 |
| `/ioc/resolving` | resolving/afterResolving 回调顺序 |
| `/ioc/scoped` | scoped + resetScope |
| `/ioc/scoped-singleton` | scoped 不影响 singleton |
| `/ioc/conditional` | when/needs/give 条件绑定 |
| `/ioc/psr11` | PSR-11 get/has |
| `/ioc/circular` | 循环依赖异常 |
| `/ioc/method-injection` | call() 方法注入 |
| `/ioc/rebinding` | 重绑定回调 |
| `/ioc/extend` | extend 装饰器 |
| `/ioc/app-facade` | App 门面 singleton |
| `/ioc/bind-if` | bindIf/singletonIf |

行为测试使用端口 9876，需确保该端口可用。

## Claude Code 自动化

### Skills

| 命令 | 用途 |
|------|------|
| `/gen-test <源文件>` | 根据源文件自动生成测试骨架（如 `/gen-test bin/Cache/CacheManager.php`） |
| `/new-feature <模块名>` | 创建新模块脚手架（管理器、测试、配置等，如 `/new-feature Queue`） |

### Hooks

- **PHP 语法检查**: 编辑 `.php` 文件后自动运行 `php -l` 检查语法（配置在 `.claude/settings.json`）

### 权限配置

- 权限通配符配置在 `.claude/settings.local.json`（不提交到 git）
- `./test:*` 覆盖所有测试命令
- `Bash(php:*)`、`Bash(git:*)`、`Bash(curl:*)` 等通配符模式

## Code Conventions

- All files use `declare(strict_types=1);`
- PSR-4 autoloading: `Bin\` → `bin/`, `App\` → `app/`
- Controllers extend `BaseController`
- Use type hints on all method parameters and return types
- Framework uses singleton pattern for `RouteCollection` and `App`
- 使用TDD开发驱动
