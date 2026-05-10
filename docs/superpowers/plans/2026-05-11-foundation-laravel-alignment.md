# Foundation Laravel Alignment Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a Laravel-like foundation assembly API for providers, middleware, and routes while preserving First's existing runtime compatibility.

**Architecture:** Keep `HttpKernel`, `ConsoleKernel`, `ProviderRepository`, `MiddlewareStack`, and `RouteAction` as the runtime execution path. Add focused foundation configuration objects that collect application assembly intent from `bootstrap/app.php`, then let existing bootstrappers consume that configuration with legacy file fallbacks.

**Tech Stack:** PHP 8.3+, custom First framework, custom `php test` runner, existing `Bin\Foundation` bootstrappers, custom IoC container.

---

## Context and Constraints

- Spec: `docs/superpowers/specs/2026-05-11-foundation-laravel-alignment-design.md`
- Keep old projects working with:
  - `config/app.php`
  - `config/middleware.php`
  - `app/routes.php`
- Add Laravel-like support for:
  - `App::configure($basePath)->withProviders()->withRouting()->withMiddleware()->create()`
  - `bootstrap/providers.php`
  - `routes/web.php`
  - `routes/api.php`
- Do not implement resource routes, route model binding, route cache, scheduling, notifications, or new ecosystem behavior in this plan.
- Keep Console bootstrap free of HTTP-only route and middleware loading.
- Follow TDD: write a failing focused test, run it, implement the minimum code, run it again, commit.
- After code changes, run `graphify update .` before final completion because this project keeps a graphify knowledge graph.

## File Structure

- Create: `bin/Foundation/ApplicationBuilder.php`
  Responsibility: collect fluent bootstrap configuration and return a configured `App`.
- Create: `bin/Foundation/ApplicationConfiguration.php`
  Responsibility: immutable-enough storage for provider, route, and middleware bootstrap configuration.
- Create: `bin/Foundation/Configuration/MiddlewareConfigurator.php`
  Responsibility: build middleware config arrays compatible with `MiddlewareStack::loadFromConfig()`.
- Create: `bin/Foundation/Configuration/RoutingConfigurator.php`
  Responsibility: collect route file paths in deterministic loading order.
- Modify: `bin/App/App.php`
  Responsibility: expose `configure()`, base path mutation for the builder, and attached application configuration accessors.
- Modify: `bin/Foundation/Bootstrap/RegisterProviders.php`
  Responsibility: load providers from builder configuration, `bootstrap/providers.php`, and legacy `config/app.php`.
- Modify: `bin/Foundation/Bootstrap/LoadMiddlewareConfiguration.php`
  Responsibility: merge builder middleware config with legacy `config/middleware.php`.
- Modify: `bin/Foundation/Bootstrap/LoadRoutes.php`
  Responsibility: load configured route files, then fall back to `app/routes.php` only when no new route files are configured.
- Modify: `bootstrap/app.php`
  Responsibility: use the new builder with compatibility-preserving defaults.
- Create: `bootstrap/providers.php`
  Responsibility: preferred app provider list, initially empty because existing providers remain in config compatibility.
- Modify: `tests/ApplicationLifecycleTest.php`
  Responsibility: cover builder configuration, provider loading, middleware merge, routing load order, and console separation.

---

### Task 1: Application Builder and Configuration

**Files:**
- Create: `bin/Foundation/ApplicationBuilder.php`
- Create: `bin/Foundation/ApplicationConfiguration.php`
- Create: `bin/Foundation/Configuration/MiddlewareConfigurator.php`
- Create: `bin/Foundation/Configuration/RoutingConfigurator.php`
- Modify: `bin/App/App.php`
- Modify: `tests/ApplicationLifecycleTest.php`

- [ ] **Step 1: Write failing builder configuration tests**

Add these imports to `tests/ApplicationLifecycleTest.php`:

```php
use Bin\Foundation\ApplicationConfiguration;
use Bin\Foundation\Configuration\MiddlewareConfigurator;
use Bin\Middleware\RateLimitMiddleware;
```

Add these test methods after `testAppPathHelpersWithSuffix()`:

```php
public function testApplicationBuilderAttachesConfiguration(): void
{
    $basePath = $this->createTempBootstrapBasePath();
    mkdir($basePath . '/routes', 0777, true);

    $webRoute = $basePath . '/routes/web.php';
    $apiRoute = $basePath . '/routes/api.php';
    file_put_contents($webRoute, "<?php\n");
    file_put_contents($apiRoute, "<?php\n");

    $app = App::configure($basePath)
        ->withProviders([\Bin\Providers\RequestServiceProvider::class])
        ->withRouting(web: $webRoute, api: $apiRoute)
        ->withMiddleware(function (MiddlewareConfigurator $middleware): void {
            $middleware->append(SessionMiddleware::class);
            $middleware->group('web', [SessionMiddleware::class, CsrfMiddleware::class]);
            $middleware->alias('throttle', RateLimitMiddleware::class);
            $middleware->priority([SessionMiddleware::class => 50]);
        })
        ->create();

    $configuration = $app->getApplicationConfiguration();

    $this->assertInstanceOf(ApplicationConfiguration::class, $configuration);
    $this->assertEquals($basePath, $app->basePath());
    $this->assertEquals($basePath, $configuration->basePath());
    $this->assertContains(\Bin\Providers\RequestServiceProvider::class, $configuration->providers());
    $this->assertEquals([$webRoute, $apiRoute], $configuration->routeFiles());
    $this->assertTrue($configuration->hasRouteConfiguration());

    $middleware = $configuration->middleware();
    $this->assertEquals([SessionMiddleware::class], $middleware['global']);
    $this->assertEquals([SessionMiddleware::class, CsrfMiddleware::class], $middleware['groups']['web']);
    $this->assertEquals(RateLimitMiddleware::class, $middleware['aliases']['throttle']);
    $this->assertEquals(50, $middleware['priority'][SessionMiddleware::class]);
}

public function testApplicationBuilderWithRoutingDefaultsToLegacyFallbackWhenRoutesDirectoryIsAbsent(): void
{
    $basePath = $this->createTempBootstrapBasePath();

    $app = App::configure($basePath)
        ->withRouting()
        ->create();

    $configuration = $app->getApplicationConfiguration();

    $this->assertSame([], $configuration->routeFiles());
    $this->assertFalse($configuration->hasRouteConfiguration());
}
```

- [ ] **Step 2: Run focused lifecycle tests and verify failure**

Run:

```bash
php test tests/ApplicationLifecycleTest.php --filter=testApplicationBuilder
```

Expected: FAIL with a missing method error:

```text
Call to undefined method Bin\App\App::configure()
```

- [ ] **Step 3: Create `ApplicationConfiguration`**

Create `bin/Foundation/ApplicationConfiguration.php`:

```php
<?php

declare(strict_types=1);

namespace Bin\Foundation;

class ApplicationConfiguration
{
    /** @var array<class-string> */
    private array $providers = [];

    /** @var array<string> */
    private array $providerFiles = [];

    /** @var array<string> */
    private array $routeFiles = [];

    private bool $hasRouteConfiguration = false;

    /** @var array{global: array<int, string>, groups: array<string, array<int, string>>, aliases: array<string, string>, priority: array<string, int>} */
    private array $middleware = [
        'global' => [],
        'groups' => [],
        'aliases' => [],
        'priority' => [],
    ];

    public function __construct(private string $basePath)
    {
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    /**
     * @param array<class-string> $providers
     */
    public function addProviders(array $providers): void
    {
        foreach ($providers as $provider) {
            if (is_string($provider) && !in_array($provider, $this->providers, true)) {
                $this->providers[] = $provider;
            }
        }
    }

    /**
     * @return array<class-string>
     */
    public function providers(): array
    {
        return $this->providers;
    }

    public function addProviderFile(string $path): void
    {
        if (!in_array($path, $this->providerFiles, true)) {
            $this->providerFiles[] = $path;
        }
    }

    /**
     * @return array<string>
     */
    public function providerFiles(): array
    {
        return $this->providerFiles;
    }

    public function addRouteFile(string $path): void
    {
        $this->hasRouteConfiguration = true;

        if (!in_array($path, $this->routeFiles, true)) {
            $this->routeFiles[] = $path;
        }
    }

    /**
     * @return array<string>
     */
    public function routeFiles(): array
    {
        return $this->routeFiles;
    }

    public function hasRouteConfiguration(): bool
    {
        return $this->hasRouteConfiguration;
    }

    /**
     * @param array{global?: array<int, string>, groups?: array<string, array<int, string>>, aliases?: array<string, string>, priority?: array<string, int>} $middleware
     */
    public function setMiddleware(array $middleware): void
    {
        $this->middleware = [
            'global' => array_values($middleware['global'] ?? []),
            'groups' => $middleware['groups'] ?? [],
            'aliases' => $middleware['aliases'] ?? [],
            'priority' => $middleware['priority'] ?? [],
        ];
    }

    /**
     * @return array{global: array<int, string>, groups: array<string, array<int, string>>, aliases: array<string, string>, priority: array<string, int>}
     */
    public function middleware(): array
    {
        return $this->middleware;
    }
}
```

- [ ] **Step 4: Create `MiddlewareConfigurator`**

Create `bin/Foundation/Configuration/MiddlewareConfigurator.php`:

```php
<?php

declare(strict_types=1);

namespace Bin\Foundation\Configuration;

class MiddlewareConfigurator
{
    /** @var array<int, string> */
    private array $global = [];

    /** @var array<string, array<int, string>> */
    private array $groups = [];

    /** @var array<string, string> */
    private array $aliases = [];

    /** @var array<string, int> */
    private array $priority = [];

    public function append(string $middleware): static
    {
        if (!in_array($middleware, $this->global, true)) {
            $this->global[] = $middleware;
        }

        return $this;
    }

    public function prepend(string $middleware): static
    {
        if (!in_array($middleware, $this->global, true)) {
            array_unshift($this->global, $middleware);
        }

        return $this;
    }

    /**
     * @param array<int, string> $middleware
     */
    public function group(string $name, array $middleware): static
    {
        $this->groups[$name] = array_values(array_unique($middleware));

        return $this;
    }

    /**
     * @param array<int, string> $middleware
     */
    public function web(array $middleware): static
    {
        return $this->group('web', $middleware);
    }

    /**
     * @param array<int, string> $middleware
     */
    public function api(array $middleware): static
    {
        return $this->group('api', $middleware);
    }

    public function appendToGroup(string $group, string $middleware): static
    {
        $this->groups[$group] ??= [];

        if (!in_array($middleware, $this->groups[$group], true)) {
            $this->groups[$group][] = $middleware;
        }

        return $this;
    }

    public function prependToGroup(string $group, string $middleware): static
    {
        $this->groups[$group] ??= [];

        if (!in_array($middleware, $this->groups[$group], true)) {
            array_unshift($this->groups[$group], $middleware);
        }

        return $this;
    }

    public function alias(string $name, string $class): static
    {
        $this->aliases[$name] = $class;

        return $this;
    }

    /**
     * @param array<string, int> $priority
     */
    public function priority(array $priority): static
    {
        $this->priority = $priority;

        return $this;
    }

    /**
     * @return array{global: array<int, string>, groups: array<string, array<int, string>>, aliases: array<string, string>, priority: array<string, int>}
     */
    public function toArray(): array
    {
        return [
            'global' => $this->global,
            'groups' => $this->groups,
            'aliases' => $this->aliases,
            'priority' => $this->priority,
        ];
    }
}
```

- [ ] **Step 5: Create `RoutingConfigurator`**

Create `bin/Foundation/Configuration/RoutingConfigurator.php`:

```php
<?php

declare(strict_types=1);

namespace Bin\Foundation\Configuration;

class RoutingConfigurator
{
    /** @var array<int, string> */
    private array $files = [];

    public function add(string $path): static
    {
        if (!in_array($path, $this->files, true)) {
            $this->files[] = $path;
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
}
```

- [ ] **Step 6: Create `ApplicationBuilder`**

Create `bin/Foundation/ApplicationBuilder.php`:

```php
<?php

declare(strict_types=1);

namespace Bin\Foundation;

use Bin\App\App;
use Bin\Foundation\Configuration\MiddlewareConfigurator;
use Bin\Foundation\Configuration\RoutingConfigurator;

class ApplicationBuilder
{
    private ApplicationConfiguration $configuration;

    public function __construct(private ?string $basePath = null)
    {
        $basePath ??= defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
        $this->basePath = $basePath;
        $this->configuration = new ApplicationConfiguration($basePath);
    }

    /**
     * @param array<class-string>|class-string|null $providers
     */
    public function withProviders(array|string|null $providers = null, ?string $path = null): static
    {
        $providerPath = $path ?? $this->basePath . '/bootstrap/providers.php';
        $this->configuration->addProviderFile($providerPath);

        if (is_string($providers)) {
            $providers = [$providers];
        }

        if (is_array($providers)) {
            $this->configuration->addProviders($providers);
        }

        return $this;
    }

    /**
     * @param array<int, string> $then
     */
    public function withRouting(?string $web = null, ?string $api = null, array $then = []): static
    {
        $routing = new RoutingConfigurator();

        if ($web === null && $api === null && $then === []) {
            $defaultWeb = $this->basePath . '/routes/web.php';
            $defaultApi = $this->basePath . '/routes/api.php';

            if (is_file($defaultWeb)) {
                $routing->add($defaultWeb);
            }

            if (is_file($defaultApi)) {
                $routing->add($defaultApi);
            }
        } else {
            if ($web !== null) {
                $routing->add($web);
            }

            if ($api !== null) {
                $routing->add($api);
            }

            foreach ($then as $file) {
                $routing->add($file);
            }
        }

        foreach ($routing->files() as $file) {
            $this->configuration->addRouteFile($file);
        }

        return $this;
    }

    public function withMiddleware(?callable $callback = null): static
    {
        $middleware = new MiddlewareConfigurator();

        if ($callback !== null) {
            $callback($middleware);
        }

        $this->configuration->setMiddleware($middleware->toArray());

        return $this;
    }

    public function create(): App
    {
        $app = App::getInstance();
        $app->setBasePath($this->basePath);
        $app->setApplicationConfiguration($this->configuration);

        return $app;
    }
}
```

- [ ] **Step 7: Add builder API to `App`**

In `bin/App/App.php`, add this import:

```php
use Bin\Foundation\ApplicationBuilder;
use Bin\Foundation\ApplicationConfiguration;
```

Add this property near `$basePath`:

```php
private ?ApplicationConfiguration $applicationConfiguration = null;
```

Add these methods after `basePath()`:

```php
public static function configure(?string $basePath = null): ApplicationBuilder
{
    return new ApplicationBuilder($basePath);
}

public function setBasePath(string $basePath): static
{
    $this->basePath = rtrim($basePath, '/');

    return $this;
}

public function setApplicationConfiguration(ApplicationConfiguration $configuration): static
{
    $this->applicationConfiguration = $configuration;

    return $this;
}

public function getApplicationConfiguration(): ApplicationConfiguration
{
    if ($this->applicationConfiguration === null) {
        $this->applicationConfiguration = new ApplicationConfiguration($this->basePath());
    }

    return $this->applicationConfiguration;
}
```

- [ ] **Step 8: Run builder tests and verify pass**

Run:

```bash
php test tests/ApplicationLifecycleTest.php --filter=testApplicationBuilder
```

Expected: PASS for the two builder tests.

- [ ] **Step 9: Commit Task 1**

Run:

```bash
git add bin/Foundation/ApplicationBuilder.php bin/Foundation/ApplicationConfiguration.php bin/Foundation/Configuration/MiddlewareConfigurator.php bin/Foundation/Configuration/RoutingConfigurator.php bin/App/App.php tests/ApplicationLifecycleTest.php
git commit -m "feat: add foundation application builder"
```

---

### Task 2: Provider Loading From New and Legacy Sources

**Files:**
- Modify: `bin/Foundation/Bootstrap/RegisterProviders.php`
- Modify: `tests/ApplicationLifecycleTest.php`

- [ ] **Step 1: Add provider fixture classes to lifecycle tests**

Append these classes to `tests/ApplicationLifecycleTest.php` after the `ApplicationLifecycleTest` class:

```php
class LifecycleBootstrapProvider extends \Bin\Providers\ServiceProvider
{
    public static int $registered = 0;
    public static int $booted = 0;

    public function register(): void
    {
        self::$registered++;
        $this->app->instance('lifecycle.bootstrap.provider', new \stdClass());
    }

    public function boot(): void
    {
        self::$booted++;
    }

    public static function resetCounts(): void
    {
        self::$registered = 0;
        self::$booted = 0;
    }
}

class LifecycleLegacyProvider extends \Bin\Providers\ServiceProvider
{
    public static int $registered = 0;
    public static int $booted = 0;

    public function register(): void
    {
        self::$registered++;
        $this->app->instance('lifecycle.legacy.provider', new \stdClass());
    }

    public function boot(): void
    {
        self::$booted++;
    }

    public static function resetCounts(): void
    {
        self::$registered = 0;
        self::$booted = 0;
    }
}
```

- [ ] **Step 2: Write failing provider loading tests**

Add these methods after `testRegisterProvidersBootstrap()`:

```php
public function testRegisterProvidersLoadsBootstrapProvidersAndLegacyProvidersOnce(): void
{
    LifecycleBootstrapProvider::resetCounts();
    LifecycleLegacyProvider::resetCounts();

    $basePath = $this->createTempBootstrapBasePath();
    mkdir($basePath . '/bootstrap', 0777, true);

    file_put_contents($basePath . '/bootstrap/providers.php', <<<'PHP'
<?php

declare(strict_types=1);

return [
    \Tests\LifecycleBootstrapProvider::class,
    \Tests\LifecycleBootstrapProvider::class,
];
PHP);

    file_put_contents($basePath . '/config/app.php', <<<'PHP'
<?php

declare(strict_types=1);

return [
    'providers' => [
        \Tests\LifecycleBootstrapProvider::class,
        \Tests\LifecycleLegacyProvider::class,
    ],
];
PHP);

    $app = App::configure($basePath)
        ->withProviders([\Tests\LifecycleBootstrapProvider::class])
        ->create();

    (new RegisterProviders())->bootstrap($app);
    (new BootProviders())->bootstrap($app);

    $this->assertTrue($app->bound('lifecycle.bootstrap.provider'));
    $this->assertTrue($app->bound('lifecycle.legacy.provider'));
    $this->assertEquals(1, LifecycleBootstrapProvider::$registered);
    $this->assertEquals(1, LifecycleBootstrapProvider::$booted);
    $this->assertEquals(1, LifecycleLegacyProvider::$registered);
    $this->assertEquals(1, LifecycleLegacyProvider::$booted);
}

public function testRegisterProvidersAllowsMissingBootstrapProvidersFile(): void
{
    LifecycleLegacyProvider::resetCounts();

    $basePath = $this->createTempBootstrapBasePath();
    file_put_contents($basePath . '/config/app.php', <<<'PHP'
<?php

declare(strict_types=1);

return [
    'providers' => [
        \Tests\LifecycleLegacyProvider::class,
    ],
];
PHP);

    $app = App::configure($basePath)
        ->withProviders()
        ->create();

    (new RegisterProviders())->bootstrap($app);
    (new BootProviders())->bootstrap($app);

    $this->assertTrue($app->bound('lifecycle.legacy.provider'));
    $this->assertEquals(1, LifecycleLegacyProvider::$registered);
    $this->assertEquals(1, LifecycleLegacyProvider::$booted);
}
```

- [ ] **Step 3: Run provider tests and verify failure**

Run:

```bash
php test tests/ApplicationLifecycleTest.php --filter=testRegisterProviders
```

Expected: FAIL because `RegisterProviders` does not read `ApplicationConfiguration` or `bootstrap/providers.php`.

- [ ] **Step 4: Implement provider collection in `RegisterProviders`**

Replace `bin/Foundation/Bootstrap/RegisterProviders.php` with:

```php
<?php

declare(strict_types=1);

namespace Bin\Foundation\Bootstrap;

use Bin\App\App;
use Bin\Foundation\Contracts\Bootstrapper as BootstrapperContract;

/**
 * 注册服务提供者
 *
 * 将应用配置的服务提供者注册到容器中。
 */
class RegisterProviders implements BootstrapperContract
{
    public function bootstrap(App $app): void
    {
        foreach ($this->providers($app) as $provider) {
            $app->register($provider);
        }
    }

    /**
     * @return array<class-string>
     */
    private function providers(App $app): array
    {
        $providers = [];
        $configuration = $app->getApplicationConfiguration();

        $providers = array_merge($providers, $configuration->providers());

        foreach ($configuration->providerFiles() as $providerFile) {
            $providers = array_merge($providers, $this->loadProviderFile($providerFile));
        }

        $providers = array_merge($providers, $this->legacyProviders($app));

        return $this->uniqueProviders($providers);
    }

    /**
     * @return array<class-string>
     */
    private function loadProviderFile(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $providers = require $path;

        if (!is_array($providers)) {
            throw new \RuntimeException("Provider file [{$path}] must return an array.");
        }

        return array_values(array_filter($providers, 'is_string'));
    }

    /**
     * @return array<class-string>
     */
    private function legacyProviders(App $app): array
    {
        $configPath = $app->configPath('app.php');

        if (!is_file($configPath)) {
            return [];
        }

        $config = require $configPath;

        if (!is_array($config)) {
            throw new \RuntimeException("Config file [{$configPath}] must return an array.");
        }

        return array_values(array_filter($config['providers'] ?? [], 'is_string'));
    }

    /**
     * @param array<int, string> $providers
     * @return array<class-string>
     */
    private function uniqueProviders(array $providers): array
    {
        $unique = [];

        foreach ($providers as $provider) {
            if (!in_array($provider, $unique, true)) {
                $unique[] = $provider;
            }
        }

        return $unique;
    }
}
```

- [ ] **Step 5: Run provider tests and verify pass**

Run:

```bash
php test tests/ApplicationLifecycleTest.php --filter=testRegisterProviders
```

Expected: PASS for provider bootstrap tests.

- [ ] **Step 6: Commit Task 2**

Run:

```bash
git add bin/Foundation/Bootstrap/RegisterProviders.php tests/ApplicationLifecycleTest.php
git commit -m "feat: load providers from bootstrap configuration"
```

---

### Task 3: Middleware Configuration Merge

**Files:**
- Modify: `bin/Foundation/Bootstrap/LoadMiddlewareConfiguration.php`
- Modify: `tests/ApplicationLifecycleTest.php`

- [ ] **Step 1: Write failing middleware merge tests**

Add these methods after `testHttpBootstrapLoadsSessionBeforeCsrfInWebGroup()`:

```php
public function testLoadMiddlewareConfigurationMergesBuilderAndLegacyConfig(): void
{
    $basePath = $this->createTempBootstrapBasePath();

    file_put_contents($basePath . '/config/middleware.php', <<<'PHP'
<?php

declare(strict_types=1);

use Bin\Middleware\AuthMiddleware;
use Bin\Middleware\CsrfMiddleware;
use Bin\Middleware\RateLimitMiddleware;

return [
    'global' => [
        CsrfMiddleware::class,
    ],
    'groups' => [
        'web' => [
            CsrfMiddleware::class,
        ],
        'api' => [
            RateLimitMiddleware::class,
        ],
    ],
    'aliases' => [
        'auth' => AuthMiddleware::class,
        'legacy' => CsrfMiddleware::class,
    ],
    'priority' => [
        'legacy' => 5,
    ],
];
PHP);

    $app = App::configure($basePath)
        ->withMiddleware(function (MiddlewareConfigurator $middleware): void {
            $middleware->append(SessionMiddleware::class);
            $middleware->group('web', [SessionMiddleware::class]);
            $middleware->alias('auth', SessionMiddleware::class);
            $middleware->priority(['auth' => 40]);
        })
        ->create();

    (new LoadMiddlewareConfiguration())->bootstrap($app);

    $stack = MiddlewareStack::getInstance();

    $this->assertEquals([CsrfMiddleware::class, SessionMiddleware::class], $stack->getGlobals());
    $this->assertEquals([SessionMiddleware::class], $stack->getGroup('web'));
    $this->assertEquals([RateLimitMiddleware::class], $stack->getGroup('api'));
    $this->assertEquals(SessionMiddleware::class, $stack->getAliases()['auth']);
    $this->assertEquals(CsrfMiddleware::class, $stack->getAliases()['legacy']);
}

public function testLoadMiddlewareConfigurationStillLoadsLegacyConfigWithoutBuilderOverrides(): void
{
    $basePath = $this->createTempBootstrapBasePath();
    $app = App::configure($basePath)->create();

    (new LoadMiddlewareConfiguration())->bootstrap($app);

    $stack = MiddlewareStack::getInstance();

    $this->assertEquals(AuthMiddleware::class, $stack->getAliases()['auth']);
    $this->assertContains(CsrfMiddleware::class, $stack->getGroup('web'));
}
```

- [ ] **Step 2: Run middleware tests and verify failure**

Run:

```bash
php test tests/ApplicationLifecycleTest.php --filter=testLoadMiddlewareConfiguration
```

Expected: FAIL because builder middleware configuration is not merged into `MiddlewareStack`.

- [ ] **Step 3: Implement middleware config merge**

Replace `bin/Foundation/Bootstrap/LoadMiddlewareConfiguration.php` with:

```php
<?php

declare(strict_types=1);

namespace Bin\Foundation\Bootstrap;

use Bin\App\App;
use Bin\Foundation\Contracts\Bootstrapper as BootstrapperContract;
use Bin\Middleware\MiddlewareStack;

class LoadMiddlewareConfiguration implements BootstrapperContract
{
    public function bootstrap(App $app): void
    {
        $legacy = $this->legacyConfig($app);
        $builder = $app->getApplicationConfiguration()->middleware();

        $config = $this->mergeConfig($legacy, $builder);

        MiddlewareStack::loadFromConfig($config);
    }

    /**
     * @return array{global: array<int, string>, groups: array<string, array<int, string>>, aliases: array<string, string>, priority: array<string, int>}
     */
    private function legacyConfig(App $app): array
    {
        $configPath = $app->configPath('middleware.php');

        if (!is_file($configPath)) {
            return $this->emptyConfig();
        }

        $config = require $configPath;

        if (!is_array($config)) {
            throw new \RuntimeException("Middleware config file [{$configPath}] must return an array.");
        }

        return [
            'global' => array_values($config['global'] ?? []),
            'groups' => $config['groups'] ?? [],
            'aliases' => $config['aliases'] ?? [],
            'priority' => $config['priority'] ?? [],
        ];
    }

    /**
     * @param array{global?: array<int, string>, groups?: array<string, array<int, string>>, aliases?: array<string, string>, priority?: array<string, int>} $legacy
     * @param array{global?: array<int, string>, groups?: array<string, array<int, string>>, aliases?: array<string, string>, priority?: array<string, int>} $builder
     * @return array{global: array<int, string>, groups: array<string, array<int, string>>, aliases: array<string, string>, priority: array<string, int>}
     */
    private function mergeConfig(array $legacy, array $builder): array
    {
        return [
            'global' => array_values(array_unique(array_merge(
                $legacy['global'] ?? [],
                $builder['global'] ?? []
            ))),
            'groups' => array_merge(
                $legacy['groups'] ?? [],
                $builder['groups'] ?? []
            ),
            'aliases' => array_merge(
                $legacy['aliases'] ?? [],
                $builder['aliases'] ?? []
            ),
            'priority' => array_merge(
                $legacy['priority'] ?? [],
                $builder['priority'] ?? []
            ),
        ];
    }

    /**
     * @return array{global: array<int, string>, groups: array<string, array<int, string>>, aliases: array<string, string>, priority: array<string, int>}
     */
    private function emptyConfig(): array
    {
        return [
            'global' => [],
            'groups' => [],
            'aliases' => [],
            'priority' => [],
        ];
    }
}
```

- [ ] **Step 4: Run middleware tests and verify pass**

Run:

```bash
php test tests/ApplicationLifecycleTest.php --filter=testLoadMiddlewareConfiguration
```

Expected: PASS for middleware configuration tests.

- [ ] **Step 5: Commit Task 3**

Run:

```bash
git add bin/Foundation/Bootstrap/LoadMiddlewareConfiguration.php tests/ApplicationLifecycleTest.php
git commit -m "feat: merge bootstrap middleware configuration"
```

---

### Task 4: Route File Configuration and Legacy Fallback

**Files:**
- Modify: `bin/Foundation/Bootstrap/LoadRoutes.php`
- Modify: `tests/ApplicationLifecycleTest.php`

- [ ] **Step 1: Write failing route loading tests**

Add these methods after `testHttpBootstrapRunsHttpOnlyStagesAfterConsoleBootstrap()`:

```php
public function testLoadRoutesUsesConfiguredRouteFilesInOrder(): void
{
    $basePath = $this->createTempBootstrapBasePath();
    mkdir($basePath . '/routes', 0777, true);

    file_put_contents($basePath . '/routes/web.php', <<<'PHP'
<?php

declare(strict_types=1);

use Bin\Route\RouteCollection as Route;

Route::get('/from-web', static fn (): string => 'web');
PHP);

    file_put_contents($basePath . '/routes/api.php', <<<'PHP'
<?php

declare(strict_types=1);

use Bin\Route\RouteCollection as Route;

Route::get('/from-api', static fn (): string => 'api');
PHP);

    file_put_contents($basePath . '/routes/extra.php', <<<'PHP'
<?php

declare(strict_types=1);

use Bin\Route\RouteCollection as Route;

Route::get('/from-extra', static fn (): string => 'extra');
PHP);

    $app = App::configure($basePath)
        ->withRouting(
            web: $basePath . '/routes/web.php',
            api: $basePath . '/routes/api.php',
            then: [$basePath . '/routes/extra.php']
        )
        ->create();

    (new LoadRoutes())->bootstrap($app);

    $paths = array_map(static fn ($route): string => $route->getPath(), Route::getRoutes());

    $this->assertEquals(['/from-web', '/from-api', '/from-extra'], $paths);
}

public function testLoadRoutesFallsBackToAppRoutesWhenNoNewRouteFilesAreConfigured(): void
{
    $basePath = $this->createTempBootstrapBasePath();

    $app = App::configure($basePath)
        ->withRouting()
        ->create();

    (new LoadRoutes())->bootstrap($app);

    $routes = Route::getRoutes();

    $this->assertCount(1, $routes);
    $this->assertEquals('/bootstrap/test-route', $routes[0]->getPath());
}

public function testLoadRoutesSkipsMissingConfiguredRouteFilesWithoutLegacyFallback(): void
{
    $basePath = $this->createTempBootstrapBasePath();

    $app = App::configure($basePath)
        ->withRouting(web: $basePath . '/routes/missing-web.php')
        ->create();

    (new LoadRoutes())->bootstrap($app);

    $this->assertSame([], Route::getRoutes());
}
```

- [ ] **Step 2: Run route tests and verify failure**

Run:

```bash
php test tests/ApplicationLifecycleTest.php --filter=testLoadRoutes
```

Expected: FAIL because `LoadRoutes` only loads `app/routes.php`.

- [ ] **Step 3: Implement configured route loading**

Replace `bin/Foundation/Bootstrap/LoadRoutes.php` with:

```php
<?php

declare(strict_types=1);

namespace Bin\Foundation\Bootstrap;

use Bin\App\App;
use Bin\Foundation\Contracts\Bootstrapper as BootstrapperContract;

class LoadRoutes implements BootstrapperContract
{
    public function bootstrap(App $app): void
    {
        $configuration = $app->getApplicationConfiguration();
        $routeFiles = $configuration->routeFiles();

        if ($routeFiles !== []) {
            $this->loadRouteFiles($routeFiles);
            return;
        }

        if ($configuration->hasRouteConfiguration()) {
            return;
        }

        $legacy = $app->basePath() . '/app/routes.php';

        if (is_file($legacy)) {
            require $legacy;
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
}
```

- [ ] **Step 4: Run route tests and verify pass**

Run:

```bash
php test tests/ApplicationLifecycleTest.php --filter=testLoadRoutes
```

Expected: PASS for configured and legacy route tests.

- [ ] **Step 5: Commit Task 4**

Run:

```bash
git add bin/Foundation/Bootstrap/LoadRoutes.php tests/ApplicationLifecycleTest.php
git commit -m "feat: load routes from bootstrap configuration"
```

---

### Task 5: Default Bootstrap Entry and Provider File

**Files:**
- Modify: `bootstrap/app.php`
- Create: `bootstrap/providers.php`
- Modify: `tests/ApplicationLifecycleTest.php`

- [ ] **Step 1: Write failing bootstrap entry test**

Add this method after `testApplicationBuilderWithRoutingDefaultsToLegacyFallbackWhenRoutesDirectoryIsAbsent()`:

```php
public function testBootstrapAppReturnsConfiguredApplication(): void
{
    App::setInstance(null);

    $app = require BASE_PATH . '/bootstrap/app.php';

    $this->assertInstanceOf(App::class, $app);
    $this->assertInstanceOf(ApplicationConfiguration::class, $app->getApplicationConfiguration());
    $this->assertEquals(BASE_PATH, $app->basePath());
}
```

- [ ] **Step 2: Run bootstrap entry test and verify failure**

Run:

```bash
php test tests/ApplicationLifecycleTest.php --filter=testBootstrapAppReturnsConfiguredApplication
```

Expected: FAIL because the current `bootstrap/app.php` returns an `App` without attached builder configuration.

- [ ] **Step 3: Update `bootstrap/app.php` to use the builder**

Replace `bootstrap/app.php` with:

```php
<?php

declare(strict_types=1);

/**
 * 应用装配入口
 *
 * 此文件创建并配置 App 实例，供 HTTP/CLI Kernel 使用。
 */

require_once __DIR__ . '/../bin/autoload.php';

use Bin\App\App;

return App::configure(dirname(__DIR__))
    ->withProviders()
    ->withRouting()
    ->withMiddleware()
    ->create();
```

- [ ] **Step 4: Add empty preferred provider file**

Create `bootstrap/providers.php`:

```php
<?php

declare(strict_types=1);

return [
];
```

- [ ] **Step 5: Run bootstrap entry test and verify pass**

Run:

```bash
php test tests/ApplicationLifecycleTest.php --filter=testBootstrapAppReturnsConfiguredApplication
```

Expected: PASS.

- [ ] **Step 6: Commit Task 5**

Run:

```bash
git add bootstrap/app.php bootstrap/providers.php tests/ApplicationLifecycleTest.php
git commit -m "feat: configure application from bootstrap entry"
```

---

### Task 6: Lifecycle Regression Verification

**Files:**
- Modify only if a preceding verification command exposes a concrete regression.

- [ ] **Step 1: Run lifecycle test file**

Run:

```bash
php test tests/ApplicationLifecycleTest.php
```

Expected: PASS for all lifecycle tests.

- [ ] **Step 2: Run middleware pipeline regression tests**

Run:

```bash
php test tests/MiddlewarePipelineTest.php
```

Expected: PASS.

- [ ] **Step 3: Run console parity regression tests**

Run:

```bash
php test tests/ConsoleArtisanParityTest.php
```

Expected: PASS. This confirms Console bootstrap still avoids HTTP-only route and middleware loading.

- [ ] **Step 4: Run full test suite**

Run:

```bash
php test
```

Expected: PASS for the full test suite.

- [ ] **Step 5: Update graphify knowledge graph**

Run:

```bash
graphify update .
```

Expected: command exits 0 and updates `graphify-out/` metadata as needed.

- [ ] **Step 6: Inspect changed files**

Run:

```bash
git status --short
```

Expected: only files touched by this plan are changed.

- [ ] **Step 7: Commit verification and graph update**

If `graphify update .` changed graph files, run:

```bash
git add graphify-out
git commit -m "chore: update graphify after foundation alignment"
```

If `graphify update .` made no changes, do not create this commit.

## Self-Review Checklist

- Spec coverage:
  - Builder API: Task 1 and Task 5
  - Attached application configuration: Task 1
  - `bootstrap/providers.php`: Task 2 and Task 5
  - Legacy `config/app.php['providers']`: Task 2
  - `routes/web.php`, `routes/api.php`, and extra route files: Task 4
  - Legacy `app/routes.php` fallback: Task 4
  - Middleware builder configuration: Task 1 and Task 3
  - Legacy `config/middleware.php`: Task 3
  - Console avoids HTTP-only bootstrappers: Task 6
- Placeholder scan:
  - No placeholder markers or open-ended implementation steps.
- Type consistency:
  - `App::configure()` returns `ApplicationBuilder`.
  - `ApplicationBuilder::create()` returns `App`.
  - `App::getApplicationConfiguration()` returns `ApplicationConfiguration`.
  - Middleware config keys match `MiddlewareStack::loadFromConfig()`.
