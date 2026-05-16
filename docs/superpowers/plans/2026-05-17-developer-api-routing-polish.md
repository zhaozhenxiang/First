# Developer API Routing Polish Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Polish First's routing developer API so configured API route files get Laravel-style defaults, current route metadata is inspectable, named URLs append query parameters, and `route:list` shows registered routes.

**Architecture:** Keep the existing static `RouteCollection` model. Add typed route file entries to the foundation configuration, wrap only API route files during `LoadRoutes`, expose normalized route metadata from `RouteCollection`, and build `route:list` as a thin console command over that metadata.

**Tech Stack:** PHP 8.3+, First framework foundation bootstrappers, static `Bin\Route\RouteCollection`, custom `php test` runner, graphify knowledge graph.

---

## Spec

- Design: `docs/superpowers/specs/2026-05-17-developer-api-routing-polish-design.md`
- Follow the spec exactly: no route cache, no signed URLs, no route-list filters, no router rewrite.

## File Structure

- Modify `bin/Foundation/ApplicationConfiguration.php`
  - Responsibility: store typed route file entries while preserving `routeFiles()` path-string compatibility.
- Modify `bin/Foundation/Configuration/RoutingConfigurator.php`
  - Responsibility: collect route file paths and their type (`web`, `api`, `extra`) before `ApplicationBuilder` writes configuration.
- Modify `bin/Foundation/ApplicationBuilder.php`
  - Responsibility: mark `web:` as `web`, `api:` as `api`, and `then:` entries as `extra`.
- Modify `bin/Foundation/Bootstrap/LoadRoutes.php`
  - Responsibility: load typed route files and wrap API entries in `prefix=api` and `middleware_group=api`.
- Modify `bin/Route/RouteCollection.php`
  - Responsibility: track the current matched route and expose normalized route metadata for tooling.
- Modify `bin/Route/Route.php`
  - Responsibility: append unused named-route parameters as a query string after path parameter replacement.
- Create `bin/Console/Commands/RouteListCommand.php`
  - Responsibility: bootstrap route loading idempotently and render route metadata.
- Modify `bin/Facade/Route.php`
  - Responsibility: document the new route introspection and metadata facade methods.
- Modify `tests/ApplicationLifecycleTest.php`
  - Coverage: typed route entries, API route wrapping, legacy fallback preservation.
- Modify `tests/RouteTest.php`
  - Coverage: current route introspection, route metadata, named URL query strings.
- Modify `tests/ConsoleArtisanParityTest.php`
  - Coverage: `route:list` command discovery, output, empty table success.
- Update `graphify-out/` by running `graphify update .` after code changes.

---

### Task 1: Typed Route File Entries And API Route Wrapping

**Files:**
- Modify: `tests/ApplicationLifecycleTest.php`
- Modify: `bin/Foundation/ApplicationConfiguration.php`
- Modify: `bin/Foundation/Configuration/RoutingConfigurator.php`
- Modify: `bin/Foundation/ApplicationBuilder.php`
- Modify: `bin/Foundation/Bootstrap/LoadRoutes.php`

- [ ] **Step 1: Add failing lifecycle tests for typed route entries and API route defaults**

In `tests/ApplicationLifecycleTest.php`, add this assertion block to `testApplicationBuilderAttachesConfiguration()` immediately after the existing `routeFiles()` assertion:

```php
$this->assertSame([
    ['path' => $webRoute, 'type' => 'web'],
    ['path' => $apiRoute, 'type' => 'api'],
], $configuration->routeFileEntries());
```

In `testBootstrapAppReturnsConfiguredApplication()`, add this block after `$expectedRouteFiles` is built and before the existing assertions:

```php
$expectedRouteFileEntries = [];

foreach ([BASE_PATH . '/routes/web.php' => 'web', BASE_PATH . '/routes/api.php' => 'api'] as $routeFile => $type) {
    if (is_file($routeFile)) {
        $expectedRouteFileEntries[] = ['path' => $routeFile, 'type' => $type];
    }
}
```

Then add this assertion after the existing `routeFiles()` assertion:

```php
$this->assertSame($expectedRouteFileEntries, $configuration->routeFileEntries());
```

Replace the path assertion in `testLoadRoutesUsesConfiguredRouteFilesInOrder()` with:

```php
$this->assertEquals(['/from-web', '/api/from-api', '/from-extra'], $paths);
```

Add this new test method below `testLoadRoutesUsesConfiguredRouteFilesInOrder()`:

```php
public function testLoadRoutesWrapsApiRouteFilesWithPrefixAndMiddlewareGroup(): void
{
    $basePath = $this->createTempBootstrapBasePath();
    mkdir($basePath . '/routes', 0777, true);

    file_put_contents($basePath . '/routes/web.php', <<<'PHP'
<?php

declare(strict_types=1);

use Bin\Route\RouteCollection as Route;

Route::get('/dashboard', static fn (): string => 'web')->name('dashboard');
PHP);

    file_put_contents($basePath . '/routes/api.php', <<<'PHP'
<?php

declare(strict_types=1);

use Bin\Route\RouteCollection as Route;

Route::get('/users', static fn (): string => 'api')->name('users.index');
PHP);

    $app = App::configure($basePath)
        ->withRouting(
            web: $basePath . '/routes/web.php',
            api: $basePath . '/routes/api.php',
        )
        ->create();

    (new LoadRoutes())->bootstrap($app);

    $routes = Route::getRoutes();

    $this->assertCount(2, $routes);
    $this->assertSame('/dashboard', $routes[0]->getPath());
    $this->assertSame([], $routes[0]->getMiddlewareGroups());
    $this->assertSame('/api/users', $routes[1]->getPath());
    $this->assertSame(['api'], $routes[1]->getMiddlewareGroups());
    $this->assertNotNull(Route::namedRoute('users.index'));
}
```

- [ ] **Step 2: Run lifecycle tests and verify the new tests fail**

Run:

```bash
php test tests/ApplicationLifecycleTest.php
```

Expected: FAIL. The first failure should mention `Call to undefined method Bin\Foundation\ApplicationConfiguration::routeFileEntries()` or the route order assertion should still show `/from-api` instead of `/api/from-api`.

- [ ] **Step 3: Implement typed route file entries**

In `bin/Foundation/ApplicationConfiguration.php`, add this property after `$routeFiles`:

```php
/**
 * @var array<int, array{path: string, type: string}>
 */
private array $routeFileEntries = [];
```

Replace `addRouteFile()` with:

```php
public function addRouteFile(string $path, string $type = 'extra'): void
{
    $this->hasRouteConfiguration = true;

    if (!in_array($path, $this->routeFiles, true)) {
        $this->routeFiles[] = $path;
    }

    foreach ($this->routeFileEntries as $entry) {
        if ($entry['path'] === $path) {
            return;
        }
    }

    $this->routeFileEntries[] = [
        'path' => $path,
        'type' => $type,
    ];
}
```

Add this accessor after `routeFiles()`:

```php
/**
 * @return array<int, array{path: string, type: string}>
 */
public function routeFileEntries(): array
{
    return $this->routeFileEntries;
}
```

In `bin/Foundation/Configuration/RoutingConfigurator.php`, replace the class body with:

```php
/** @var array<int, string> */
private array $files = [];

/**
 * @var array<int, array{path: string, type: string}>
 */
private array $entries = [];

public function add(string $path, string $type = 'extra'): static
{
    if (!in_array($path, $this->files, true)) {
        $this->files[] = $path;
        $this->entries[] = [
            'path' => $path,
            'type' => $type,
        ];
    }

    return $this;
}

/**
 * @return array<int, string>
 */
public function files(): array
{
    return $this->files;
}

/**
 * @return array<int, array{path: string, type: string}>
 */
public function entries(): array
{
    return $this->entries;
}
```

In `bin/Foundation/ApplicationBuilder.php`, update `withRouting()` so the route additions use typed calls:

```php
if ($web === null && $api === null && $then === []) {
    $defaultWeb = $this->joinBasePath('routes/web.php');
    $defaultApi = $this->joinBasePath('routes/api.php');

    if (is_file($defaultWeb)) {
        $routing->add($defaultWeb, 'web');
    }

    if (is_file($defaultApi)) {
        $routing->add($defaultApi, 'api');
    }
} else {
    if ($web !== null) {
        $routing->add($web, 'web');
    }

    if ($api !== null) {
        $routing->add($api, 'api');
    }

    foreach ($then as $file) {
        $routing->add($file, 'extra');
    }
}

foreach ($routing->entries() as $entry) {
    $this->configuration->addRouteFile($entry['path'], $entry['type']);
}
```

In `bin/Foundation/Bootstrap/LoadRoutes.php`, add this import:

```php
use Bin\Route\RouteCollection;
```

Then replace `bootstrap()` and `loadRouteFiles()` with:

```php
public function bootstrap(App $app): void
{
    $configuration = $app->getApplicationConfiguration();
    $routeFileEntries = $configuration->routeFileEntries();

    if ($routeFileEntries !== []) {
        $this->loadRouteFileEntries($routeFileEntries);
        return;
    }

    $routeFiles = $configuration->routeFiles();

    if ($routeFiles !== []) {
        $this->loadRouteFiles($routeFiles);
        return;
    }

    if ($configuration->hasRouteConfiguration()) {
        return;
    }

    $legacy = $app->appPath('routes.php');

    if (is_file($legacy)) {
        require $legacy;
    }
}

/**
 * @param array<int, array{path: string, type: string}> $routeFileEntries
 */
private function loadRouteFileEntries(array $routeFileEntries): void
{
    foreach ($routeFileEntries as $entry) {
        $routeFile = $entry['path'];

        if (!is_file($routeFile)) {
            continue;
        }

        if (($entry['type'] ?? 'extra') === 'api') {
            RouteCollection::group([
                'prefix' => 'api',
                'middleware_group' => 'api',
            ], static function () use ($routeFile): void {
                require $routeFile;
            });
            continue;
        }

        require $routeFile;
    }
}

/**
 * @param array<int, string> $routeFiles
 */
private function loadRouteFiles(array $routeFiles): void
{
    foreach ($routeFiles as $routeFile) {
        if (is_file($routeFile)) {
            require $routeFile;
        }
    }
}
```

- [ ] **Step 4: Run lifecycle tests and verify they pass**

Run:

```bash
php test tests/ApplicationLifecycleTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit typed route loading**

Run:

```bash
git add tests/ApplicationLifecycleTest.php bin/Foundation/ApplicationConfiguration.php bin/Foundation/Configuration/RoutingConfigurator.php bin/Foundation/ApplicationBuilder.php bin/Foundation/Bootstrap/LoadRoutes.php
git commit -m "feat: apply api route file defaults"
```

Expected: commit succeeds.

---

### Task 2: Current Route Introspection And Route Metadata

**Files:**
- Modify: `tests/RouteTest.php`
- Modify: `bin/Route/RouteCollection.php`
- Modify: `bin/Facade/Route.php`

- [ ] **Step 1: Add failing route introspection and metadata tests**

In `tests/RouteTest.php`, add this helper method before the final closing brace of `RouteTest`:

```php
private function withServerRequest(string $method, string $uri, callable $callback): mixed
{
    $oldMethod = $_SERVER['REQUEST_METHOD'] ?? null;
    $oldUri = $_SERVER['REQUEST_URI'] ?? null;

    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['REQUEST_URI'] = $uri;

    try {
        return $callback();
    } finally {
        if ($oldMethod === null) {
            unset($_SERVER['REQUEST_METHOD']);
        } else {
            $_SERVER['REQUEST_METHOD'] = $oldMethod;
        }

        if ($oldUri === null) {
            unset($_SERVER['REQUEST_URI']);
        } else {
            $_SERVER['REQUEST_URI'] = $oldUri;
        }
    }
}
```

Add these test methods before the helper:

```php
public function testCurrentRouteIsNullBeforeMatch(): void
{
    $this->assertNull(Route::current());
    $this->assertNull(Route::currentRouteName());
    $this->assertNull(Route::currentRouteAction());
}

public function testCurrentRouteIsSetForStaticMatch(): void
{
    $action = static fn (): string => 'ok';
    Route::get('/current', $action)->name('current.show');

    $matched = $this->withServerRequest('GET', '/current', static fn () => Route::getRoute());

    $this->assertSame($matched, Route::current());
    $this->assertSame('current.show', Route::currentRouteName());
    $this->assertSame($action, Route::currentRouteAction());
}

public function testCurrentRouteIsSetForDynamicMatch(): void
{
    Route::get('/current/{id}', static fn (string $id): string => $id)->name('current.dynamic');

    $matched = $this->withServerRequest('GET', '/current/42', static fn () => Route::getRoute());

    $this->assertSame($matched, Route::current());
    $this->assertSame('/current/{id}', Route::current()->getPath());
    $this->assertSame('current.dynamic', Route::currentRouteName());
}

public function testCurrentRouteIsSetForFallbackMatch(): void
{
    $fallback = Route::fallback(static fn (): string => 'fallback')->name('fallback');

    $matched = $this->withServerRequest('GET', '/missing', static fn () => Route::getRoute());

    $this->assertSame($fallback, $matched);
    $this->assertSame($fallback, Route::current());
    $this->assertSame('fallback', Route::currentRouteName());
}

public function testClearResetsCurrentRoute(): void
{
    Route::get('/current', static fn (): string => 'ok');

    $this->withServerRequest('GET', '/current', static fn () => Route::getRoute());
    $this->assertNotNull(Route::current());

    Route::clear();

    $this->assertNull(Route::current());
}

public function testRouteTableReturnsNormalizedMetadata(): void
{
    Route::group(['middleware' => ['auth'], 'middleware_group' => 'api'], function (): void {
        Route::get('/users/{id}', 'UserController@show')->name('users.show');
    });

    $rows = Route::routeTable();

    $this->assertCount(1, $rows);
    $this->assertEquals([
        'method' => 'GET',
        'uri' => '/users/{id}',
        'name' => 'users.show',
        'action' => 'UserController@show',
        'middleware' => 'auth, api',
    ], $rows[0]);
}
```

- [ ] **Step 2: Run route tests and verify they fail**

Run:

```bash
php test tests/RouteTest.php
```

Expected: FAIL. The first new failure should mention `Call to undefined method Bin\Route\RouteCollection::current()`.

- [ ] **Step 3: Implement current route and route table metadata**

In `bin/Route/RouteCollection.php`, add this property after `$fallbackRoute`:

```php
/** @var Route|null 当前匹配路由 */
private static ?Route $currentRoute = null;
```

Replace `resolve()` with:

```php
private static function resolve(string $method, string $path): Route
{
    // 静态路由直接索引查找 O(1)
    $key = $method . ':' . $path;
    if (isset(self::$staticRoutes[$key])) {
        return self::setCurrentRoute(self::$staticRoutes[$key]);
    }

    // 动态路由遍历匹配
    foreach (self::$dynamicRoutes as $route) {
        if ($route->getMethod() === $method && $route->withSuccess($path)) {
            return self::setCurrentRoute($route);
        }
    }

    // 兜底路由
    if (self::$fallbackRoute !== null) {
        return self::setCurrentRoute(self::$fallbackRoute);
    }

    throw new NotFoundHttpException('Route not found');
}
```

Add this method after `resolve()`:

```php
private static function setCurrentRoute(Route $route): Route
{
    self::$currentRoute = $route;

    return $route;
}
```

Add these public methods after `url()`:

```php
public static function current(): ?Route
{
    return self::$currentRoute;
}

public static function currentRouteName(): ?string
{
    return self::$currentRoute?->getName();
}

public static function currentRouteAction(): mixed
{
    return self::$currentRoute?->getAction();
}

/**
 * @return array<int, array{method: string, uri: string, name: string, action: string, middleware: string}>
 */
public static function routeTable(): array
{
    return array_map(static function (Route $route): array {
        $middleware = array_values(array_unique(array_merge(
            $route->getMiddleware(),
            $route->getMiddlewareGroups()
        )));

        return [
            'method' => $route->getMethod(),
            'uri' => $route->getPath(),
            'name' => $route->getName() ?? '',
            'action' => self::describeAction($route->getAction()),
            'middleware' => implode(', ', $middleware),
        ];
    }, self::$route);
}

private static function describeAction(mixed $action): string
{
    if (is_string($action)) {
        return $action;
    }

    if ($action instanceof \Closure) {
        return 'Closure';
    }

    if (is_array($action)) {
        $target = $action[0] ?? '';
        $method = $action[1] ?? '';
        $class = is_object($target) ? $target::class : (string) $target;

        return $method !== '' ? $class . '@' . $method : $class;
    }

    if (is_object($action)) {
        return $action::class;
    }

    return get_debug_type($action);
}
```

In `clear()`, add this line before resetting the group stack:

```php
self::$currentRoute = null;
```

In `bin/Facade/Route.php`, add these docblock method lines after `@method static void bind(...)`:

```php
 * @method static \Bin\Route\Route|null current()
 * @method static string|null currentRouteName()
 * @method static mixed currentRouteAction()
 * @method static array routeTable()
```

- [ ] **Step 4: Run route tests and verify they pass**

Run:

```bash
php test tests/RouteTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit route introspection**

Run:

```bash
git add tests/RouteTest.php bin/Route/RouteCollection.php bin/Facade/Route.php
git commit -m "feat: expose current route metadata"
```

Expected: commit succeeds.

---

### Task 3: Named URL Query String Generation

**Files:**
- Modify: `tests/RouteTest.php`
- Modify: `bin/Route/Route.php`

- [ ] **Step 1: Add failing URL query string tests**

In `tests/RouteTest.php`, add these tests near the existing `RouteUrl` tests:

```php
public function testRouteUrlAppendsUnusedParametersAsQueryString(): void
{
    $route = Route::get('/users/{user}', static fn (): string => 'ok');

    $this->assertEquals('/users/5?tab=posts&sort=recent', $route->url([
        'user' => 5,
        'tab' => 'posts',
        'sort' => 'recent',
    ]));
}

public function testRouteUrlOmitsNullUnusedQueryParameters(): void
{
    $route = Route::get('/users/{user}', static fn (): string => 'ok');

    $this->assertEquals('/users/5?tab=posts', $route->url([
        'user' => 5,
        'tab' => 'posts',
        'empty' => null,
    ]));
}

public function testNamedRouteUrlAppendsUnusedParametersAsQueryString(): void
{
    Route::get('/teams/{team}/users/{user}', static fn (): string => 'ok')->name('teams.users.show');

    $this->assertEquals('/teams/acme/users/7?tab=posts', Route::url('teams.users.show', [
        'team' => 'acme',
        'user' => 7,
        'tab' => 'posts',
    ]));
}
```

- [ ] **Step 2: Run route tests and verify the query string tests fail**

Run:

```bash
php test tests/RouteTest.php
```

Expected: FAIL. The new URL assertions should show actual values without `?tab=...`.

- [ ] **Step 3: Implement query string generation in `Route::url()`**

In `bin/Route/Route.php`, replace `url()` with:

```php
public function url(array $params = []): string
{
    $segments = explode('/', trim($this->getPath(), '/'));
    $missing = [];
    $resolvedSegments = [];
    $usedParams = [];

    foreach ($segments as $segment) {
        if ($segment === '') {
            continue;
        }

        if (preg_match('/\{([^}]+)\}/', $segment) !== 1) {
            $resolvedSegments[] = $segment;
            continue;
        }

        $resolvedSegment = preg_replace_callback('/\{([^}]+)\}/', function (array $matches) use ($params, &$missing, &$usedParams): string {
            $raw = $matches[1];
            $optional = str_ends_with($raw, '?');
            $name = rtrim($raw, '?');

            if (array_key_exists($name, $params) && $params[$name] !== null) {
                $usedParams[] = $name;
                return (string) $params[$name];
            }

            if ($optional) {
                return '';
            }

            $missing[] = $name;
            return $matches[0];
        }, $segment);

        if ($resolvedSegment !== null && $resolvedSegment !== '') {
            $resolvedSegments[] = $resolvedSegment;
        }
    }

    if ($missing !== []) {
        throw \Bin\Exception\UrlGenerationException::forMissingParameters($this->getPath(), $missing);
    }

    $path = $resolvedSegments === [] ? '/' : '/' . implode('/', $resolvedSegments);
    $queryParams = [];

    foreach ($params as $key => $value) {
        if ($value === null || in_array((string) $key, $usedParams, true)) {
            continue;
        }

        $queryParams[$key] = $value;
    }

    if ($queryParams === []) {
        return $path;
    }

    $query = http_build_query($queryParams);

    return $query === '' ? $path : $path . '?' . $query;
}
```

- [ ] **Step 4: Run route tests and verify existing placeholder edge cases still pass**

Run:

```bash
php test tests/RouteTest.php
```

Expected: PASS. Existing tests named `testRouteUrlPreservesValueThatLooksLikeOptionalPlaceholder`, `testRouteUrlPreservesCompositePlaceholderLookingValue`, and `testRouteUrlPreservesSlugValueWhenOptionalPlaceholderMissingInSameSegment` must still pass.

- [ ] **Step 5: Commit URL query string generation**

Run:

```bash
git add tests/RouteTest.php bin/Route/Route.php
git commit -m "feat: append query parameters to named urls"
```

Expected: commit succeeds.

---

### Task 4: Basic `route:list` Command

**Files:**
- Create: `bin/Console/Commands/RouteListCommand.php`
- Modify: `tests/ConsoleArtisanParityTest.php`

- [ ] **Step 1: Add failing console tests for `route:list`**

In `tests/ConsoleArtisanParityTest.php`, add these imports after the existing `use` statements:

```php
use Bin\App\App;
use Bin\Console\Commands\RouteListCommand;
use Bin\Route\RouteCollection as Route;
```

Add this cleanup line to both `setUp()` and `tearDown()` after `Kernel::clear();`:

```php
Route::clear();
```

Add these test methods before the `LazyTestCommand` helper class:

```php
public function testRouteListCommandIsDiscovered(): void
{
    Kernel::discover();

    $this->assertTrue(Kernel::hasCommand('route:list'));
    $this->assertInstanceOf(RouteListCommand::class, Kernel::getCommand('route:list'));
}

public function testRouteListCommandOutputsRouteMetadata(): void
{
    $previousApp = App::getInstance();
    App::setInstance(null);
    $basePath = sys_get_temp_dir() . '/first-route-list-' . bin2hex(random_bytes(6));
    mkdir($basePath . '/routes', 0777, true);

    file_put_contents($basePath . '/routes/web.php', <<<'PHP'
<?php

declare(strict_types=1);

use Bin\Route\RouteCollection as Route;

Route::get('/route-list/{id}', 'RouteListController@show')
    ->middleware('auth')
    ->middlewareGroup('api')
    ->name('route.list.show');
PHP);

    App::configure($basePath)
        ->withRouting(web: $basePath . '/routes/web.php')
        ->create();

    try {
        $command = new RouteListCommand();
        $command->parseSignature();

        ob_start();
        $exitCode = $command->run(new Input(['script', 'route:list']), new Output());
        $output = ob_get_clean();
    } finally {
        App::setInstance($previousApp);
        Route::clear();
    }

    $this->assertSame(0, $exitCode);
    $this->assertStringContainsString('Method', $output);
    $this->assertStringContainsString('URI', $output);
    $this->assertStringContainsString('Name', $output);
    $this->assertStringContainsString('Action', $output);
    $this->assertStringContainsString('Middleware', $output);
    $this->assertStringContainsString('GET', $output);
    $this->assertStringContainsString('/route-list/{id}', $output);
    $this->assertStringContainsString('route.list.show', $output);
    $this->assertStringContainsString('RouteListController@show', $output);
    $this->assertStringContainsString('auth, api', $output);
}

public function testRouteListCommandReturnsSuccessForEmptyRouteTable(): void
{
    $previousApp = App::getInstance();
    App::setInstance(null);
    $basePath = sys_get_temp_dir() . '/first-route-list-empty-' . bin2hex(random_bytes(6));
    mkdir($basePath . '/routes', 0777, true);

    App::configure($basePath)
        ->withRouting(web: $basePath . '/routes/missing.php')
        ->create();

    try {
        $command = new RouteListCommand();
        $command->parseSignature();

        ob_start();
        $exitCode = $command->run(new Input(['script', 'route:list']), new Output());
        $output = ob_get_clean();
    } finally {
        App::setInstance($previousApp);
        Route::clear();
    }

    $this->assertSame(0, $exitCode);
    $this->assertStringContainsString('Method', $output);
    $this->assertStringContainsString('URI', $output);
    $this->assertStringContainsString('Middleware', $output);
}
```

- [ ] **Step 2: Run console tests and verify they fail**

Run:

```bash
php test tests/ConsoleArtisanParityTest.php
```

Expected: FAIL. The first new failure should mention that class `Bin\Console\Commands\RouteListCommand` is not found or that `route:list` is not discovered.

- [ ] **Step 3: Implement `RouteListCommand`**

Create `bin/Console/Commands/RouteListCommand.php` with:

```php
<?php

declare(strict_types=1);

namespace Bin\Console\Commands;

use Bin\App\App;
use Bin\Console\Command;
use Bin\Foundation\Bootstrap\LoadRoutes;
use Bin\Route\RouteCollection;

class RouteListCommand extends Command
{
    protected string $signature = 'route:list';

    protected string $description = 'List registered routes';

    public function execute(): int
    {
        $app = App::getInstance();

        if (!$app->hasBeenBootstrappedBy(LoadRoutes::class)) {
            $app->bootstrapWith([LoadRoutes::class]);
        }

        $rows = array_map(static fn (array $route): array => [
            $route['method'],
            $route['uri'],
            $route['name'],
            $route['action'],
            $route['middleware'],
        ], RouteCollection::routeTable());

        $this->table(['Method', 'URI', 'Name', 'Action', 'Middleware'], $rows);

        return 0;
    }
}
```

- [ ] **Step 4: Run console tests and verify they pass**

Run:

```bash
php test tests/ConsoleArtisanParityTest.php
```

Expected: PASS.

- [ ] **Step 5: Verify route command discovery from the CLI entrypoint**

Run:

```bash
php command list
```

Expected: PASS exit code and output contains `route:list`.

Run:

```bash
php command route:list
```

Expected: PASS exit code and output contains table headers `Method`, `URI`, `Name`, `Action`, and `Middleware`.

- [ ] **Step 6: Commit route list command**

Run:

```bash
git add tests/ConsoleArtisanParityTest.php bin/Console/Commands/RouteListCommand.php
git commit -m "feat: add route list command"
```

Expected: commit succeeds.

---

### Task 5: Focused Regression, Full Verification, And Graph Update

**Files:**
- Update: `graphify-out/graph.json`
- Update: `graphify-out/GRAPH_REPORT.md`
- Update any additional files written by `graphify update .`

- [ ] **Step 1: Run focused verification**

Run:

```bash
php test tests/ApplicationLifecycleTest.php
php test tests/RouteTest.php
php test tests/RouteEnhancementTest.php
php test tests/ConsoleArtisanParityTest.php
```

Expected: all four commands PASS.

- [ ] **Step 2: Run full test suite**

Run:

```bash
php test
```

Expected: PASS.

- [ ] **Step 3: Update graphify knowledge graph**

Run:

```bash
graphify update .
```

Expected: command exits 0 and updates `graphify-out/` if the graph detects code changes.

- [ ] **Step 4: Inspect graph and working tree changes**

Run:

```bash
git status --short
```

Expected: only graphify output files should be unstaged. If no graphify files changed, the working tree should be clean.

- [ ] **Step 5: Commit graphify updates if present**

If `git status --short` shows changed files under `graphify-out/`, run:

```bash
git add graphify-out
git commit -m "chore: update graphify after routing polish"
```

Expected: commit succeeds. If there are no `graphify-out/` changes, skip this commit.

---

## Final Verification Checklist

- `routes/api.php` routes are registered under `/api`.
- API route file routes receive the `api` middleware group.
- `routes/web.php` and `app/routes.php` compatibility tests pass.
- `RouteCollection::current()`, `currentRouteName()`, and `currentRouteAction()` work after route resolution.
- `RouteCollection::routeTable()` returns normalized metadata strings.
- `Route::url()` appends unused non-null parameters through `http_build_query()`.
- `route:list` is auto-discovered and renders method, URI, name, action, middleware.
- `php test` passes.
- `graphify update .` has been run after code changes.
