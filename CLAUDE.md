# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Custom PHP 8.3+ MVC framework inspired by Laravel. Implements IoC/DI container, routing, middleware, Eloquent-style ORM, event system, authentication/authorization, caching, logging, validation, console commands, migrations, and pagination.

## Commands

```bash
php test                              # Run all tests (1085 tests)
php test tests/QueryBuilderTest.php   # Run single test file
php test --filter=testBind            # Filter tests by name
php test --verbose                    # Verbose output
php test --stop-on-failure            # Stop on first failure
php -l <file>                         # Syntax check a PHP file
composer dump-autoload                # Regenerate autoloader
php -S localhost:8000 -t public       # Dev server
php migrate migrate                   # Run migrations
php command db:seed                   # Run seeders
php command list                      # List CLI commands
```

## Architecture

### Request Flow

```
public/index.php → autoload → app/routes.php
  → RouteAction::action() → RouteCollection::getRoute()
  → Middleware chain (must return true to continue)
  → Controller method / Closure (with DI via reflection)
  → Response echoed
```

### Namespace Mapping

| Namespace | Directory |
|-----------|-----------|
| `Bin\` | `bin/` |
| `App\` | `app/` |

### Core Modules

| Module | Location | Key Classes |
|--------|----------|-------------|
| **IoC Container** | `bin/Container/` | `Container` (底层), `ContextualBindingBuilder` |
| **App Facade** | `bin/App/` | `App` extends `Container`，22+ 透传方法标 `@deprecated` |
| **Router** | `bin/Route/` | `RouteCollection`, `RouteAction` |
| **Request** | `bin/Request/` | `Request` (ArrayAccess + Iterator) |
| **Response** | `bin/Response/` | `Response` (string/array/view) |
| **View** | `bin/View/` | `View` (template compiler) |
| **Database ORM** | `bin/Database/` | See ORM section below |
| **Auth** | `bin/Auth/` | `AuthManager`, `Gate`, `Rbac`, `HashManager`, `RateLimiter` |
| **Cache** | `bin/Cache/` | `CacheManager`, `FileStore`, `RedisStore`, `ArrayStore` |
| **Log** | `bin/Log/` | `LogManager`, `Logger` |
| **Session** | `bin/Session/` | `SessionManager`, handlers for file/db/redis |
| **Validation** | `bin/Validation/` | `ValidationManager`, `Validator` |
| **Cookie** | `bin/Cookie/` | `CookieManager` (AES-256-CBC encryption) |
| **Events** | `bin/Events/` | `EventDispatcher`, `Event`, `Subscriber` |
| **Console** | `bin/Console/` | `Kernel`, `Command`, `Input`, `Output`, `ProgressBar` |
| **Config** | `bin/Config/` | `ConfigRepository` (dot-notation, compiled cache) |
| **Middleware** | `bin/Middleware/` | `Middleware` base, `AuthMiddleware`, `RateLimitMiddleware` |
| **Facade** | `bin/Facade/` | `Facade` (static proxy to container) |
| **Helpers** | `bin/Func/helpers/` | 17 domain-split files loaded via glob |

### ORM Architecture

```
Bin\Database\Model (665 lines, abstract base)
  ├── use HasAttributes        (421 lines) — 属性访问/修改/类型转换/fillable/guarded/hidden/visible
  ├── use HasEvents            (168 lines) — 模型事件 + Observer + withoutEvents
  ├── use HasRelationships     (421 lines) — 11 种关联 + eager loading + morph map
  ├── use HasTimestamps        (65 lines)  — created_at/updated_at
  └── use HasSerialization     (85 lines)  — toArray/toJson/append

Bin\Database\QueryBuilder (1996 lines)
  └── use CompilesQueries      (252 lines) — SQL 语法编译 (WHERE/JOIN/GROUP/HAVING/ORDER/LIMIT/OFFSET/UNION/LOCK)

Bin\Database\ConnectionManager (79 lines) — PDO 连接管理，替代遗留 Bin\Model\Model
Bin\Database\ModelEventDispatcher — 模型事件委托到 EventDispatcher
```

**关联类型**：`hasOne`, `hasMany`, `belongsTo`, `belongsToMany`, `hasOneThrough`, `hasManyThrough`, `morphOne`, `morphMany`, `morphToMany`, `morphTo`, `morphedByMany`

**分页**：`paginate()` (LengthAwarePaginator), `simplePaginate()` (Paginator), `cursorPaginate()` (CursorPaginator)

**软删除**：`Bin\Database\SoftDeletes` trait

### Helper Functions

`bin/Func/helpers.php` is a glob loader; actual functions live in `bin/Func/helpers/*.php`:

| File | Key Functions |
|------|---------------|
| `array.php` | `data_get`, `data_set`, `data_has` |
| `http.php` | `getUrl`, `getMethod`, `abort`, `response`, `redirect`, `back`, `is_ajax` |
| `config.php` | `config`, `env` |
| `container.php` | `app` |
| `session.php` | `session`, `session_*` (11 functions) |
| `auth.php` | `auth`, `auth_*`, `csrf_token`, `csrf_field` |
| `cache.php` | `cache`, `remember`, `cache_forever`, `cache_forget` |
| `cookie.php` | `cookie`, `cookie_*` |
| `validation.php` | `validate` (delegates to ValidationManager), `escape` |
| `authorization.php` | `gate`, `can`, `cannot`, `allows`, `denies` |
| `log.php` | `logger`, `info`, `error` |
| `debug.php` | `db_debug*`, `profiler*` (20 functions) |

All functions use `function_exists()` guards for safe loading.

### Static Managers (Migration in Progress)

AuthManager, CacheManager, LogManager, and Gate have been converted to instance-based with singleton pattern. Static methods are retained as `@deprecated` compatibility layer delegating to `getInstance()`:

```php
// Legacy (still works, @deprecated)
AuthManager::user();

// New approach
AuthManager::getInstance()->userFor();
```

### Container API

```php
// Bindings
$container->bind('service', ClassName::class);
$container->singleton('service', ClassName::class);
$container->instance('service', new ClassName());
$container->scoped('service', ClassName::class);  // resetScope() re-creates

// Tags
$container->tag(['redis', 'file'], 'cache');
$container->tagged('cache');

// Contextual
$container->when(AuditService::class)->needs(LoggerInterface::class)->give(fn() => new CloudLogger());

// Resolving callbacks
$container->resolving(function ($obj, $container) { /* ... */ });
$container->rebinding('cache', function ($container, $instance) { /* ... */ });

// Method injection
$container->call(ServiceImpl::class . '@handle', ['message' => 'hello']);
```

### PHP 8.5 Constraint

PHP 8.5 不允许对已有实例方法使用静态调用，`__callStatic` 不会拦截。所有静态调用需通过 `App::getInstance()` 获取实例后调用。

## Testing

1085 tests across 50 test files. Custom test runner (`php test`).

**IoC behavior tests** use PHP built-in server on port 9876 (`tests/IocBehaviorTest.php`).

### Test Conventions

- TDD driven: write tests first, then implement
- Test files in `tests/` follow `<Module>Test.php` naming
- All helpers (session, cache, auth, etc.) use test mode flags to avoid side effects
- `Bin\Database\Model::setConnection()` injects in-memory SQLite for database tests

## Code Conventions

- `declare(strict_types=1)` in all files
- PSR-4 autoloading
- Type hints on all public method parameters and return types
- `@deprecated` annotations for backward-compatible method transitions (not deleted immediately)

## Claude Code Automation

### Skills

| Command | Purpose |
|---------|---------|
| `/gen-test <source>` | Generate test skeleton from source file |
| `/new-feature <module>` | Create module scaffold (manager, tests, config) |

### Hooks

PHP syntax check auto-runs on `.php` file edits (configured in `.claude/settings.json`).

### Permissions

Wildcard patterns in `.claude/settings.local.json` (not committed): `./test:*`, `Bash(php:*)`, `Bash(git:*)`, `Bash(curl:*)`
