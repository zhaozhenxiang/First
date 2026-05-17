# Developer API Production Routing Tools Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add production-oriented routing commands so First can cache route definitions, clear route cache files, and filter `route:list` output without changing request routing semantics.

**Architecture:** Keep `RouteCollection` as the source of truth and add a small `RouteCache` helper that writes compiled route arrays to `storage/routes.php`. `LoadRoutes` should prefer a valid route cache file, while `route:cache` rebuilds from configured route files after clearing any old cache. `route:list` filtering stays as a presentation concern over normalized `RouteCollection::routeTable()` rows.

**Tech Stack:** PHP 8.3+, First framework console commands, `Bin\Foundation\Bootstrap\LoadRoutes`, static `Bin\Route\RouteCollection`, custom `php test` runner, graphify knowledge graph.

---

## Spec

- Source spec: `docs/superpowers/specs/2026-05-17-developer-api-routing-polish-design.md`
- Follow-up phase: "Production routing tools: route cache, route clear, and route filtering."
- Reference behavior from Laravel 13 routing docs:
  - `route:list` exists for route overview.
  - `route:list --path=api` filters by URI prefix.
  - `routes/api.php` receives `/api` prefix and `api` middleware group.
- This phase intentionally does not add signed URLs, temporary signatures, route cache warming hooks, vendor route filters, route sorting, or a router rewrite.

## File Structure

- Create `bin/Route/RouteCache.php`
  - Responsibility: resolve the cache file path, validate cached payloads, atomically write compiled route payloads, and clear the cache file.
- Modify `bin/Route/RouteCollection.php`
  - Responsibility: export cacheable route definitions and restore them without requiring route files.
- Modify `bin/Foundation/Bootstrap/LoadRoutes.php`
  - Responsibility: load compiled cached routes before falling back to configured route files.
- Create `bin/Console/Commands/RouteCacheCommand.php`
  - Responsibility: rebuild route cache from configured route files and fail clearly for non-cacheable route actions.
- Create `bin/Console/Commands/RouteClearCommand.php`
  - Responsibility: remove the compiled route cache file idempotently.
- Modify `bin/Console/Commands/RouteListCommand.php`
  - Responsibility: add `--path=`, `--name=`, and `--method=` filters over normalized route metadata.
- Modify `tests/RouteTest.php`
  - Coverage: export/import route cache payloads, fallback route cache support, and rejection of non-cacheable actions.
- Modify `tests/ApplicationLifecycleTest.php`
  - Coverage: `LoadRoutes` prefers the cache file and preserves API route metadata after cached restore.
- Modify `tests/ConsoleArtisanParityTest.php`
  - Coverage: command discovery, `route:cache`, `route:clear`, cache failure output, and `route:list` filtering.
- Update `graphify-out/` by running `graphify update .` after code changes.

---

### Task 1: Route Cache Payload Export And Restore

**Files:**
- Modify: `tests/RouteTest.php`
- Modify: `bin/Route/RouteCollection.php`

- [ ] **Step 1: Add failing route cache payload tests**

In `tests/RouteTest.php`, add these tests near the existing route metadata tests:

```php
public function testRouteCollectionExportsAndRestoresCacheableRoutes(): void
{
    Route::get('/cached/{id}', 'CachedController@show')
        ->where('id', '[0-9]+')
        ->middleware(['auth', 'throttle:60,1'])
        ->middlewareGroup('api')
        ->withoutMiddleware('csrf')
        ->name('cached.show');

    $payload = Route::exportForCache();

    $this->assertSame(1, count($payload['routes']));
    $this->assertSame('/cached/{id}', $payload['routes'][0]['uri']);
    $this->assertSame('CachedController@show', $payload['routes'][0]['action']);
    $this->assertSame('cached.show', $payload['routes'][0]['name']);
    $this->assertSame(['id' => '[0-9]+'], $payload['routes'][0]['where']);
    $this->assertSame(['auth', 'throttle:60,1'], $payload['routes'][0]['middleware']);
    $this->assertSame(['api'], $payload['routes'][0]['middleware_groups']);
    $this->assertSame(['csrf'], $payload['routes'][0]['excluded_middleware']);

    Route::clear();
    Route::loadFromCache($payload);

    $routes = Route::getRoutes();

    $this->assertCount(1, $routes);
    $this->assertSame('/cached/{id}', $routes[0]->getPath());
    $this->assertSame('CachedController@show', $routes[0]->getAction());
    $this->assertSame('cached.show', $routes[0]->getName());
    $this->assertSame(['id' => '[0-9]+'], $routes[0]->getWheres());
    $this->assertSame(['auth', 'throttle:60,1'], $routes[0]->getMiddleware());
    $this->assertSame(['api'], $routes[0]->getMiddlewareGroups());
    $this->assertSame(['csrf'], $routes[0]->getExcludedMiddleware());
    $this->assertNotNull(Route::namedRoute('cached.show'));
}

public function testRouteCollectionExportsAndRestoresCachedFallbackRoute(): void
{
    Route::fallback('FallbackController@handle');

    $payload = Route::exportForCache();

    $this->assertSame([], $payload['routes']);
    $this->assertSame('GET', $payload['fallback']['method']);
    $this->assertSame('/', $payload['fallback']['uri']);
    $this->assertSame('FallbackController@handle', $payload['fallback']['action']);

    Route::clear();
    Route::loadFromCache($payload);

    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/missing-from-cache';

    $route = Route::getRoute();

    $this->assertSame('/', $route->getPath());
    $this->assertSame('FallbackController@handle', $route->getAction());
    $this->assertSame($route, Route::current());
}

public function testRouteCollectionRejectsClosureRoutesWhenExportingCache(): void
{
    Route::get('/closure-cache', static fn (): string => 'no-cache');

    $this->assertThrows(\RuntimeException::class, function (): void {
        Route::exportForCache();
    });
}
```

- [ ] **Step 2: Run route tests and verify the new tests fail**

Run:

```bash
php test tests/RouteTest.php
```

Expected: FAIL. The first failure should mention `Call to undefined method Bin\Route\RouteCollection::exportForCache()`.

- [ ] **Step 3: Implement route export and restore methods**

In `bin/Route/RouteCollection.php`, add this import near the existing imports:

```php
use RuntimeException;
```

Then add these methods immediately before `clear()`:

```php
/**
 * @return array{routes: array<int, array<string, mixed>>, fallback: array<string, mixed>|null}
 */
public static function exportForCache(): array
{
    return [
        'routes' => array_map(static fn (Route $route): array => self::exportRoute($route), self::$route),
        'fallback' => self::$fallbackRoute === null ? null : self::exportRoute(self::$fallbackRoute),
    ];
}

/**
 * @param array{routes?: array<int, array<string, mixed>>, fallback?: array<string, mixed>|null} $payload
 */
public static function loadFromCache(array $payload): void
{
    self::clear();

    foreach ($payload['routes'] ?? [] as $route) {
        self::restoreCachedRoute($route, false);
    }

    $fallback = $payload['fallback'] ?? null;

    if (is_array($fallback)) {
        self::restoreCachedRoute($fallback, true);
    }
}

/**
 * @return array<string, mixed>
 */
private static function exportRoute(Route $route): array
{
    return [
        'method' => $route->getMethod(),
        'uri' => $route->getPath(),
        'action' => self::exportCacheableAction($route->getAction(), $route->getPath()),
        'name' => $route->getName(),
        'domain' => $route->getDomain(),
        'where' => $route->getWheres(),
        'middleware' => $route->getMiddleware(),
        'middleware_groups' => $route->getMiddlewareGroups(),
        'excluded_middleware' => $route->getExcludedMiddleware(),
    ];
}

private static function exportCacheableAction(mixed $action, string $uri): string|array
{
    if (is_string($action)) {
        return $action;
    }

    if (is_array($action)) {
        $target = $action[0] ?? null;
        $method = $action[1] ?? null;

        if (is_string($target) && is_string($method)) {
            return [$target, $method];
        }
    }

    throw new RuntimeException("Unable to cache route [{$uri}] because it uses a non-cacheable action.");
}

/**
 * @param array<string, mixed> $data
 */
private static function restoreCachedRoute(array $data, bool $fallback): void
{
    $route = $fallback
        ? self::fallback($data['action'])
        : self::action((string) $data['method'], (string) $data['uri'], $data['action']);

    if (($data['domain'] ?? null) !== null) {
        $route->setDomain((string) $data['domain']);
    }

    if (($data['where'] ?? []) !== []) {
        $route->where($data['where']);
    }

    if (($data['middleware'] ?? []) !== []) {
        $route->middleware($data['middleware']);
    }

    if (($data['middleware_groups'] ?? []) !== []) {
        $route->middlewareGroup($data['middleware_groups']);
    }

    if (($data['excluded_middleware'] ?? []) !== []) {
        $route->withoutMiddleware($data['excluded_middleware']);
    }

    $name = $data['name'] ?? null;

    if (is_string($name) && $name !== '') {
        $route->name($name);
    }
}
```

- [ ] **Step 4: Run route tests and verify they pass**

Run:

```bash
php test tests/RouteTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit route cache export support**

Run:

```bash
git add tests/RouteTest.php bin/Route/RouteCollection.php
git commit -m "feat: export route cache payloads"
```

Expected: commit succeeds.

---

### Task 2: Route Cache File Helper

**Files:**
- Create: `bin/Route/RouteCache.php`
- Modify: `tests/RouteTest.php`

- [ ] **Step 1: Add failing route cache file helper tests**

In `tests/RouteTest.php`, add these tests below the cache payload tests from Task 1:

```php
public function testRouteCacheWritesLoadsAndClearsPayload(): void
{
    $previousApp = \Bin\App\App::getInstance();
    \Bin\App\App::setInstance(null);
    $basePath = sys_get_temp_dir() . '/first-route-cache-helper-' . bin2hex(random_bytes(6));
    mkdir($basePath . '/storage', 0777, true);

    $app = \Bin\App\App::configure($basePath)->create();

    try {
        $payload = [
            'routes' => [
                [
                    'method' => 'GET',
                    'uri' => '/cached',
                    'action' => 'CachedController@index',
                    'name' => 'cached.index',
                    'domain' => null,
                    'where' => [],
                    'middleware' => [],
                    'middleware_groups' => [],
                    'excluded_middleware' => [],
                ],
            ],
            'fallback' => null,
        ];

        \Bin\Route\RouteCache::write($payload, $app);

        $this->assertTrue(\Bin\Route\RouteCache::exists($app));
        $this->assertSame($payload, \Bin\Route\RouteCache::load($app));
        $this->assertTrue(\Bin\Route\RouteCache::clear($app));
        $this->assertFalse(\Bin\Route\RouteCache::exists($app));
        $this->assertNull(\Bin\Route\RouteCache::load($app));
        $this->assertFalse(\Bin\Route\RouteCache::clear($app));
    } finally {
        \Bin\App\App::setInstance($previousApp);
        $this->deleteDirectory($basePath);
    }
}

public function testRouteCacheRejectsMalformedCacheFile(): void
{
    $previousApp = \Bin\App\App::getInstance();
    \Bin\App\App::setInstance(null);
    $basePath = sys_get_temp_dir() . '/first-route-cache-malformed-' . bin2hex(random_bytes(6));
    mkdir($basePath . '/storage', 0777, true);

    $app = \Bin\App\App::configure($basePath)->create();
    file_put_contents($basePath . '/storage/routes.php', "<?php\n\nreturn 'not-an-array';\n");

    try {
        $this->assertThrows(\RuntimeException::class, function () use ($app): void {
            \Bin\Route\RouteCache::load($app);
        });
    } finally {
        \Bin\App\App::setInstance($previousApp);
        $this->deleteDirectory($basePath);
    }
}
```

If `tests/RouteTest.php` does not already have a recursive delete helper, add this private method near the bottom of the class:

```php
private function deleteDirectory(string $path): void
{
    if (!is_dir($path)) {
        return;
    }

    $items = scandir($path);

    if ($items === false) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $target = $path . DIRECTORY_SEPARATOR . $item;

        if (is_dir($target)) {
            $this->deleteDirectory($target);
            continue;
        }

        unlink($target);
    }

    rmdir($path);
}
```

- [ ] **Step 2: Run route tests and verify the new tests fail**

Run:

```bash
php test tests/RouteTest.php
```

Expected: FAIL because `Bin\Route\RouteCache` does not exist.

- [ ] **Step 3: Create the route cache helper**

Create `bin/Route/RouteCache.php` with this content:

```php
<?php

declare(strict_types=1);

namespace Bin\Route;

use Bin\App\App;
use RuntimeException;

class RouteCache
{
    private const CACHE_FILE = 'storage/routes.php';

    public static function path(?App $app = null): string
    {
        $app ??= App::getInstance();

        return $app->basePath(self::CACHE_FILE);
    }

    public static function exists(?App $app = null): bool
    {
        return is_file(self::path($app));
    }

    /**
     * @return array{routes: array<int, array<string, mixed>>, fallback: array<string, mixed>|null}|null
     */
    public static function load(?App $app = null): ?array
    {
        $path = self::path($app);

        if (!is_file($path)) {
            return null;
        }

        $payload = require $path;

        if (!is_array($payload)) {
            throw new RuntimeException('Route cache file is invalid: ' . $path);
        }

        if (!array_key_exists('routes', $payload) || !is_array($payload['routes'])) {
            throw new RuntimeException('Route cache file is missing a routes array: ' . $path);
        }

        return [
            'routes' => $payload['routes'],
            'fallback' => $payload['fallback'] ?? null,
        ];
    }

    /**
     * @param array{routes: array<int, array<string, mixed>>, fallback: array<string, mixed>|null} $payload
     */
    public static function write(array $payload, ?App $app = null): void
    {
        $path = self::path($app);
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $temporary = $path . '.tmp';
        file_put_contents($temporary, self::compile($payload));
        rename($temporary, $path);
    }

    public static function clear(?App $app = null): bool
    {
        $path = self::path($app);

        if (!is_file($path)) {
            return false;
        }

        unlink($path);

        return true;
    }

    /**
     * @param array{routes: array<int, array<string, mixed>>, fallback: array<string, mixed>|null} $payload
     */
    private static function compile(array $payload): string
    {
        return "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . 'return ' . var_export($payload, true) . ";\n";
    }
}
```

- [ ] **Step 4: Run route tests and verify they pass**

Run:

```bash
php test tests/RouteTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit route cache helper**

Run:

```bash
git add tests/RouteTest.php bin/Route/RouteCache.php
git commit -m "feat: add route cache file helper"
```

Expected: commit succeeds.

---

### Task 3: Route Cache And Clear Commands

**Files:**
- Create: `bin/Console/Commands/RouteCacheCommand.php`
- Create: `bin/Console/Commands/RouteClearCommand.php`
- Modify: `tests/ConsoleArtisanParityTest.php`

- [ ] **Step 1: Add failing command discovery and route cache tests**

In `tests/ConsoleArtisanParityTest.php`, add these imports near the existing command imports:

```php
use Bin\Console\Commands\RouteCacheCommand;
use Bin\Console\Commands\RouteClearCommand;
use Bin\Route\RouteCache;
```

Add these tests below `testRouteListCommandIsDiscovered()`:

```php
public function testRouteCacheAndClearCommandsAreDiscovered(): void
{
    Kernel::discover();

    $this->assertTrue(Kernel::hasCommand('route:cache'));
    $this->assertTrue(Kernel::hasCommand('route:clear'));
    $this->assertInstanceOf(RouteCacheCommand::class, Kernel::getCommand('route:cache'));
    $this->assertInstanceOf(RouteClearCommand::class, Kernel::getCommand('route:clear'));
}

public function testRouteCacheCommandWritesCompiledRoutes(): void
{
    $previousApp = App::getInstance();
    App::setInstance(null);
    $basePath = sys_get_temp_dir() . '/first-route-cache-command-' . bin2hex(random_bytes(6));
    mkdir($basePath . '/routes', 0777, true);

    file_put_contents($basePath . '/routes/web.php', <<<'PHP'
<?php

declare(strict_types=1);

use Bin\Route\RouteCollection as Route;

Route::get('/cache-command/{id}', 'CacheCommandController@show')
    ->where('id', '[0-9]+')
    ->middleware('auth')
    ->name('cache.command.show');
PHP);

    $app = App::configure($basePath)
        ->withRouting(web: $basePath . '/routes/web.php')
        ->create();

    try {
        $command = new RouteCacheCommand();
        $command->parseSignature();

        ob_start();
        $exitCode = $command->run(new Input(['script', 'route:cache']), new Output());
        $output = ob_get_clean();

        $this->assertSame(0, $exitCode);
        $this->assertTrue(RouteCache::exists($app));
        $this->assertStringContainsString('Route cache generated', $output);

        $payload = RouteCache::load($app);

        $this->assertSame('/cache-command/{id}', $payload['routes'][0]['uri']);
        $this->assertSame('CacheCommandController@show', $payload['routes'][0]['action']);
        $this->assertSame('cache.command.show', $payload['routes'][0]['name']);
    } finally {
        App::setInstance($previousApp);
        Route::clear();
        $this->deleteDirectory($basePath);
    }
}

public function testRouteCacheCommandFailsForClosureRoutes(): void
{
    $previousApp = App::getInstance();
    App::setInstance(null);
    $basePath = sys_get_temp_dir() . '/first-route-cache-closure-' . bin2hex(random_bytes(6));
    mkdir($basePath . '/routes', 0777, true);

    file_put_contents($basePath . '/routes/web.php', <<<'PHP'
<?php

declare(strict_types=1);

use Bin\Route\RouteCollection as Route;

Route::get('/closure-route', static fn (): string => 'closure');
PHP);

    $app = App::configure($basePath)
        ->withRouting(web: $basePath . '/routes/web.php')
        ->create();

    try {
        $command = new RouteCacheCommand();
        $command->parseSignature();

        ob_start();
        $exitCode = $command->run(new Input(['script', 'route:cache']), new Output());
        $output = ob_get_clean();

        $this->assertSame(1, $exitCode);
        $this->assertFalse(RouteCache::exists($app));
        $this->assertStringContainsString('Unable to cache route [/closure-route]', $output);
    } finally {
        App::setInstance($previousApp);
        Route::clear();
        $this->deleteDirectory($basePath);
    }
}

public function testRouteClearCommandRemovesCompiledRoutesIdempotently(): void
{
    $previousApp = App::getInstance();
    App::setInstance(null);
    $basePath = sys_get_temp_dir() . '/first-route-clear-command-' . bin2hex(random_bytes(6));
    mkdir($basePath . '/storage', 0777, true);

    $app = App::configure($basePath)->create();
    RouteCache::write(['routes' => [], 'fallback' => null], $app);

    try {
        $command = new RouteClearCommand();
        $command->parseSignature();

        ob_start();
        $firstExitCode = $command->run(new Input(['script', 'route:clear']), new Output());
        $firstOutput = ob_get_clean();

        ob_start();
        $secondExitCode = $command->run(new Input(['script', 'route:clear']), new Output());
        $secondOutput = ob_get_clean();

        $this->assertSame(0, $firstExitCode);
        $this->assertSame(0, $secondExitCode);
        $this->assertFalse(RouteCache::exists($app));
        $this->assertStringContainsString('Route cache cleared', $firstOutput);
        $this->assertStringContainsString('No route cache to clear', $secondOutput);
    } finally {
        App::setInstance($previousApp);
        $this->deleteDirectory($basePath);
    }
}
```

Add this private helper near the bottom of `ConsoleArtisanParityTest` if it is not already present:

```php
private function deleteDirectory(string $path): void
{
    if (!is_dir($path)) {
        return;
    }

    $items = scandir($path);

    if ($items === false) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $target = $path . DIRECTORY_SEPARATOR . $item;

        if (is_dir($target)) {
            $this->deleteDirectory($target);
            continue;
        }

        unlink($target);
    }

    rmdir($path);
}
```

- [ ] **Step 2: Run console tests and verify the new tests fail**

Run:

```bash
php test tests/ConsoleArtisanParityTest.php
```

Expected: FAIL because `RouteCacheCommand` and `RouteClearCommand` do not exist.

- [ ] **Step 3: Create route cache command**

Create `bin/Console/Commands/RouteCacheCommand.php` with this content:

```php
<?php

declare(strict_types=1);

namespace Bin\Console\Commands;

use Bin\App\App;
use Bin\Console\Command;
use Bin\Foundation\Bootstrap\LoadRoutes;
use Bin\Route\RouteCache;
use Bin\Route\RouteCollection;
use RuntimeException;

class RouteCacheCommand extends Command
{
    protected string $signature = 'route:cache';

    protected string $description = 'Create a route cache file for faster route registration';

    public function execute(): int
    {
        $app = App::getInstance();

        RouteCache::clear($app);
        RouteCollection::clear();

        try {
            (new LoadRoutes())->bootstrap($app);
            $payload = RouteCollection::exportForCache();
            RouteCache::write($payload, $app);
        } catch (RuntimeException $exception) {
            RouteCache::clear($app);
            $this->error($exception->getMessage());

            return 1;
        }

        $count = count($payload['routes']) + ($payload['fallback'] === null ? 0 : 1);
        $this->success("Route cache generated ({$count} routes).");

        return 0;
    }
}
```

- [ ] **Step 4: Create route clear command**

Create `bin/Console/Commands/RouteClearCommand.php` with this content:

```php
<?php

declare(strict_types=1);

namespace Bin\Console\Commands;

use Bin\App\App;
use Bin\Console\Command;
use Bin\Route\RouteCache;

class RouteClearCommand extends Command
{
    protected string $signature = 'route:clear';

    protected string $description = 'Remove the route cache file';

    public function execute(): int
    {
        if (!RouteCache::clear(App::getInstance())) {
            $this->info('No route cache to clear.');

            return 0;
        }

        $this->success('Route cache cleared.');

        return 0;
    }
}
```

- [ ] **Step 5: Run console tests and verify they pass**

Run:

```bash
php test tests/ConsoleArtisanParityTest.php
```

Expected: PASS.

- [ ] **Step 6: Verify CLI discovery**

Run:

```bash
php command list
```

Expected: PASS exit code and output contains `route:cache`, `route:clear`, and `route:list`.

- [ ] **Step 7: Commit route cache commands**

Run:

```bash
git add tests/ConsoleArtisanParityTest.php bin/Console/Commands/RouteCacheCommand.php bin/Console/Commands/RouteClearCommand.php
git commit -m "feat: add route cache commands"
```

Expected: commit succeeds.

---

### Task 4: Load Cached Routes During Bootstrap

**Files:**
- Modify: `tests/ApplicationLifecycleTest.php`
- Modify: `bin/Foundation/Bootstrap/LoadRoutes.php`

- [ ] **Step 1: Add failing lifecycle test for cached route loading**

In `tests/ApplicationLifecycleTest.php`, add this test near the `LoadRoutes` tests:

```php
public function testLoadRoutesPrefersCompiledRouteCache(): void
{
    $basePath = $this->createTempBootstrapBasePath();
    mkdir($basePath . '/routes', 0777, true);

    file_put_contents($basePath . '/routes/web.php', <<<'PHP'
<?php

declare(strict_types=1);

use Bin\Route\RouteCollection as Route;

Route::get('/from-source', 'SourceController@index')->name('source.index');
PHP);

    $app = App::configure($basePath)
        ->withRouting(web: $basePath . '/routes/web.php')
        ->create();

    \Bin\Route\RouteCache::write([
        'routes' => [
            [
                'method' => 'GET',
                'uri' => '/from-cache',
                'action' => 'CachedController@index',
                'name' => 'cached.index',
                'domain' => null,
                'where' => [],
                'middleware' => ['auth'],
                'middleware_groups' => ['api'],
                'excluded_middleware' => [],
            ],
        ],
        'fallback' => null,
    ], $app);

    (new LoadRoutes())->bootstrap($app);

    $routes = Route::getRoutes();

    $this->assertCount(1, $routes);
    $this->assertSame('/from-cache', $routes[0]->getPath());
    $this->assertSame('CachedController@index', $routes[0]->getAction());
    $this->assertSame('cached.index', $routes[0]->getName());
    $this->assertSame(['auth'], $routes[0]->getMiddleware());
    $this->assertSame(['api'], $routes[0]->getMiddlewareGroups());
    $this->assertNotNull(Route::namedRoute('cached.index'));
}
```

- [ ] **Step 2: Run lifecycle tests and verify the new test fails**

Run:

```bash
php test tests/ApplicationLifecycleTest.php
```

Expected: FAIL because `LoadRoutes` still loads `/from-source` instead of `/from-cache`.

- [ ] **Step 3: Make LoadRoutes prefer cached route payloads**

In `bin/Foundation/Bootstrap/LoadRoutes.php`, add this import:

```php
use Bin\Route\RouteCache;
```

Then add this block at the top of `bootstrap()` after the method opens:

```php
$cachedRoutes = RouteCache::load($app);

if ($cachedRoutes !== null) {
    RouteCollection::loadFromCache($cachedRoutes);
    return;
}
```

The beginning of `bootstrap()` should look like this:

```php
public function bootstrap(App $app): void
{
    $cachedRoutes = RouteCache::load($app);

    if ($cachedRoutes !== null) {
        RouteCollection::loadFromCache($cachedRoutes);
        return;
    }

    $configuration = $app->getApplicationConfiguration();
    $routeFileEntries = $configuration->routeFileEntries();
```

- [ ] **Step 4: Run lifecycle and console route cache tests**

Run:

```bash
php test tests/ApplicationLifecycleTest.php
php test tests/ConsoleArtisanParityTest.php
```

Expected: both commands PASS.

- [ ] **Step 5: Commit cached route bootstrap**

Run:

```bash
git add tests/ApplicationLifecycleTest.php bin/Foundation/Bootstrap/LoadRoutes.php
git commit -m "feat: load compiled route cache"
```

Expected: commit succeeds.

---

### Task 5: Route List Filtering

**Files:**
- Modify: `tests/ConsoleArtisanParityTest.php`
- Modify: `bin/Console/Commands/RouteListCommand.php`

- [ ] **Step 1: Add failing route list filter tests**

In `tests/ConsoleArtisanParityTest.php`, add this test below `testRouteListCommandOutputsRouteMetadata()`:

```php
public function testRouteListCommandFiltersByPathNameAndMethod(): void
{
    $previousApp = App::getInstance();
    App::setInstance(null);
    $basePath = sys_get_temp_dir() . '/first-route-list-filter-' . bin2hex(random_bytes(6));
    mkdir($basePath . '/routes', 0777, true);

    file_put_contents($basePath . '/routes/web.php', <<<'PHP'
<?php

declare(strict_types=1);

use Bin\Route\RouteCollection as Route;

Route::get('/api/users', 'UserController@index')->name('api.users.index');
Route::post('/api/users', 'UserController@store')->name('api.users.store');
Route::get('/admin/reports', 'ReportController@index')->name('admin.reports.index');
PHP);

    App::configure($basePath)
        ->withRouting(web: $basePath . '/routes/web.php')
        ->create();

    try {
        $command = new RouteListCommand();
        $command->parseSignature();

        ob_start();
        $exitCode = $command->run(new Input([
            'script',
            'route:list',
            '--path=api',
            '--name=users',
            '--method=POST',
        ]), new Output());
        $output = ob_get_clean();
    } finally {
        App::setInstance($previousApp);
        Route::clear();
        $this->deleteDirectory($basePath);
    }

    $this->assertSame(0, $exitCode);
    $this->assertStringContainsString('/api/users', $output);
    $this->assertStringContainsString('api.users.store', $output);
    $this->assertStringContainsString('POST', $output);
    $this->assertStringNotContainsString('api.users.index', $output);
    $this->assertStringNotContainsString('/admin/reports', $output);
}
```

- [ ] **Step 2: Run console tests and verify the new test fails**

Run:

```bash
php test tests/ConsoleArtisanParityTest.php
```

Expected: FAIL because `route:list` ignores the filter options and shows all routes.

- [ ] **Step 3: Update RouteListCommand signature and filtering**

Replace `bin/Console/Commands/RouteListCommand.php` with this content:

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
    protected string $signature = 'route:list {--path=} {--name=} {--method=}';

    protected string $description = 'List registered routes';

    public function execute(): int
    {
        $app = App::getInstance();

        if (!$app->hasBeenBootstrappedBy(LoadRoutes::class)) {
            $app->bootstrapWith([LoadRoutes::class]);
        }

        $routes = array_values(array_filter(
            RouteCollection::routeTable(),
            fn (array $route): bool => $this->matchesFilters($route)
        ));

        $rows = array_map(static fn (array $route): array => [
            $route['method'],
            $route['uri'],
            $route['name'],
            $route['action'],
            $route['middleware'],
        ], $routes);

        $this->table(['Method', 'URI', 'Name', 'Action', 'Middleware'], $rows);

        return 0;
    }

    /**
     * @param array{method: string, uri: string, name: string, action: string, middleware: string} $route
     */
    private function matchesFilters(array $route): bool
    {
        $path = $this->option('path');

        if (is_string($path) && $path !== '' && !$this->matchesPath($route['uri'], $path)) {
            return false;
        }

        $name = $this->option('name');

        if (is_string($name) && $name !== '' && !str_contains($route['name'], $name)) {
            return false;
        }

        $method = $this->option('method');

        if (is_string($method) && $method !== '' && strtoupper($route['method']) !== strtoupper($method)) {
            return false;
        }

        return true;
    }

    private function matchesPath(string $uri, string $path): bool
    {
        $normalizedUri = '/' . ltrim($uri, '/');
        $normalizedPath = '/' . trim($path, '/');

        return str_starts_with($normalizedUri, $normalizedPath);
    }
}
```

- [ ] **Step 4: Run console tests and verify they pass**

Run:

```bash
php test tests/ConsoleArtisanParityTest.php
```

Expected: PASS.

- [ ] **Step 5: Verify CLI help exposes filter options**

Run:

```bash
php command help route:list
```

Expected: PASS exit code and output contains `--path`, `--name`, and `--method`.

- [ ] **Step 6: Commit route list filters**

Run:

```bash
git add tests/ConsoleArtisanParityTest.php bin/Console/Commands/RouteListCommand.php
git commit -m "feat: filter route list output"
```

Expected: commit succeeds.

---

### Task 6: Focused Verification, Full Suite, And Graph Update

**Files:**
- Update: `graphify-out/graph.json`
- Update: `graphify-out/GRAPH_REPORT.md`
- Update any additional files written by `graphify update .`

- [ ] **Step 1: Run focused verification**

Run:

```bash
php test tests/RouteTest.php
php test tests/ApplicationLifecycleTest.php
php test tests/ConsoleArtisanParityTest.php
php test tests/RouteEnhancementTest.php
php test tests/MiddlewarePipelineTest.php
```

Expected: all five commands PASS.

- [ ] **Step 2: Run CLI smoke checks**

Run:

```bash
php command list
php command help route:list
php command route:list --path=api
php command route:clear
```

Expected:
- `php command list` exits 0 and shows `route:cache`, `route:clear`, and `route:list`.
- `php command help route:list` exits 0 and shows `--path`, `--name`, and `--method`.
- `php command route:list --path=api` exits 0 and renders the route table headers.
- `php command route:clear` exits 0 whether or not a route cache file exists.

- [ ] **Step 3: Run full test suite**

Run:

```bash
php test
```

Expected: PASS. Existing PHP 8.5 deprecation notices may appear, but the command must exit 0 with zero failures.

- [ ] **Step 4: Update graphify knowledge graph**

Run:

```bash
graphify update .
```

Expected: command exits 0 and updates `graphify-out/` if the graph detects code changes.

- [ ] **Step 5: Inspect graph and working tree changes**

Run:

```bash
git status --short
```

Expected: only `graphify-out/` files should be unstaged. If no graphify files changed, the working tree should be clean.

- [ ] **Step 6: Commit graphify updates if present**

If `git status --short` shows changed files under `graphify-out/`, run:

```bash
git add graphify-out
git commit -m "chore: update graphify after route tools"
```

Expected: commit succeeds. If there are no `graphify-out/` changes, skip this commit.

---

## Final Verification Checklist

- `RouteCollection::exportForCache()` exports method, URI, action, name, domain, where constraints, middleware, middleware groups, excluded middleware, and fallback route data.
- `RouteCollection::loadFromCache()` restores static, dynamic, named, middleware, where, domain, and fallback route behavior.
- Closure routes and object-callable routes fail `route:cache` with a clear message and no leftover cache file.
- `RouteCache` writes `storage/routes.php`, loads only valid array payloads, and clears the file idempotently.
- `LoadRoutes` prefers a compiled route cache over route files.
- `route:cache` and `route:clear` are auto-discovered by `php command list`.
- `route:cache` rebuilds from route files after clearing old cache state.
- `route:clear` returns success when the cache file is absent.
- `route:list --path=`, `--name=`, and `--method=` filter normalized route table rows.
- Existing API route prefix and middleware group behavior remain unchanged.
- Existing route enhancement, lifecycle, middleware pipeline, console parity, and full test suite pass.
- `graphify update .` has been run after code changes.
