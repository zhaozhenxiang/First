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
  - `Route/` - Route collection, routing, and action dispatch
  - `Request/` - HTTP request handling with ArrayAccess/Iterator
  - `Response/` - HTTP response formatting (string/array/view)
  - `View/` - Template rendering with compiler
  - `Model/` - Database model base and query builder
  - `Middleware/` - Abstract middleware class
  - `Reflection/` - Dependency injection via reflection
  - `Facade/` - Static proxy pattern
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

### IoC Container (`Bin\App\App`)

```php
// Resolve from container
$instance = App::make(ClassName::class);
$instance = app('ClassName');  // helper function

// Registered aliases: 'Request', 'Response', 'Route'
```

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
- `config('db:driver')` - Get nested config value
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

## Code Conventions

- All files use `declare(strict_types=1);`
- PSR-4 autoloading: `Bin\` → `bin/`, `App\` → `app/`
- Controllers extend `BaseController`
- Use type hints on all method parameters and return types
- Framework uses singleton pattern for `RouteCollection` and `App`
