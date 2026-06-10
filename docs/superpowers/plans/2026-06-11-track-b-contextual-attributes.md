# Track B Contextual Attributes Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add Laravel 13-inspired contextual attribute dependency resolution to First's container, including built-in attributes for implemented services and scoped lifecycle reset at request and job boundaries.

**Architecture:** Keep attribute resolution inside `Bin\Container\Container` so constructors, closures, controller actions, and job `handle()` methods share the same reflection path. Built-in attributes live under `Bin\Container\Attributes` and implement a small `Bin\Contracts\ContextualAttribute` contract. Route dispatch only passes raw route parameter context to the container; it must not inspect contextual attributes.

**Tech Stack:** PHP 8.3+, First IoC container, PHP reflection attributes, custom `php test` runner, `Bin\Testing\TestCase`, graphify.

---

## Scope

This plan implements Track B-1 from `docs/superpowers/specs/2026-06-11-track-b-contextual-attributes-design.md`, which decomposes Track B from `docs/superpowers/specs/2026-05-31-laravel13-architecture-gap-spec.md`.

In scope:

- A custom contextual attribute contract equivalent to Laravel's `ContextualAttribute`.
- Container-owned attribute resolution for constructor injection and `Container::call()` method or closure injection.
- Built-in attributes for implemented First services: config value, cache store, database connection, storage disk, log channel, default auth guard manager, route parameter, tagged services, and explicit implementation.
- Explicit call parameters continue to override method contextual attributes.
- Contextual attributes resolve before legacy contextual binding during constructor injection.
- Existing `contextual()` and `when()->needs()->give()` behavior keeps passing.
- Request and queue job boundaries reset scoped bindings.
- Targeted docs/spec notes and full verification.

Out of scope:

- Track C routing parity beyond passing raw route parameters to the container.
- Track D validation, FormRequest, and Blade expansion.
- Track E driver ecosystem expansion.
- Track F AI, MCP, and search architecture.
- Multiple named auth guards or named database connections. Existing First services expose one default auth manager and one active PDO connection, so non-default names raise clear container errors.

## File Structure

- Create: `bin/Contracts/ContextualAttribute.php`
  - Contract implemented by all container contextual attributes.
- Modify: `bin/Container/Container.php`
  - Inspects `ReflectionParameter` attributes in constructor and method resolution.
  - Owns parameter context stack used by `#[RouteParameter]`.
  - Preserves legacy contextual binding and scoped behavior.
- Create: `bin/Container/Attributes/Config.php`
  - Resolves a config value from `config()->get($key, $default)`.
- Create: `bin/Container/Attributes/Cache.php`
  - Resolves a `Bin\Cache\CacheRepository` from `CacheManager::storeFor()`.
- Create: `bin/Container/Attributes/Db.php`
  - Resolves the active `PDO` connection.
- Create: `bin/Container/Attributes/Storage.php`
  - Resolves a `Bin\Filesystem\Filesystem` disk.
- Create: `bin/Container/Attributes/Log.php`
  - Resolves a `Bin\Log\Logger` channel.
- Create: `bin/Container/Attributes/Auth.php`
  - Resolves the default `Bin\Auth\AuthManager`.
- Create: `bin/Container/Attributes/RouteParameter.php`
  - Resolves a raw route or call parameter from the current `Container::call()` context.
- Create: `bin/Container/Attributes/Tag.php`
  - Resolves `Container::tagged($tag)` as an array.
- Create: `bin/Container/Attributes/Give.php`
  - Resolves an explicit implementation through the container.
- Modify: `bin/Routing/ControllerDispatcher.php`
  - Passes raw URL route parameters under a reserved container context key.
- Modify: `bin/Route/RouteAction.php`
  - Resets scoped bindings at request dispatch boundaries.
- Modify: `bin/Queue/Worker.php`
  - Resets scoped bindings around each worker-processed job.
- Modify: `bin/Queue/Drivers/SyncQueue.php`
  - Resets scoped bindings around sync queue job execution.
- Modify: `bin/Queue/Dispatchable.php`
  - Resets scoped bindings around `dispatchSync()` job execution.
- Create: `tests/ContextualAttributeTest.php`
  - Covers custom attributes, built-in attributes, precedence, route parameter context, and errors.
- Modify: `tests/DispatcherIntegrationTest.php`
  - Covers route parameter attributes through controller and closure dispatch, plus request scoped reset.
- Modify: `tests/QueueTest.php`
  - Covers worker, sync queue, and dispatchSync scoped reset.
- Modify: `tests/QueueTestHelpers.php`
  - Adds scoped job fixtures.
- Modify: `docs/superpowers/specs/2026-06-11-track-b-contextual-attributes-design.md`
  - Adds a short implementation note after code lands.

## Task 1: Core Contextual Attribute Contract and Container Resolution

**Files:**
- Create: `tests/ContextualAttributeTest.php`
- Create: `bin/Contracts/ContextualAttribute.php`
- Modify: `bin/Container/Container.php`

- [ ] **Step 1: Write the failing core tests**

Create `tests/ContextualAttributeTest.php` with this exact content:

```php
<?php

declare(strict_types=1);

namespace Tests;

use Attribute;
use Bin\Container\Container;
use Bin\Container\Exceptions\BindingResolutionException;
use Bin\Contracts\ContextualAttribute;
use Bin\Testing\TestCase;
use ReflectionParameter;

class ContextualAttributeTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = new Container();
    }

    public function testCustomAttributeResolvesConstructorPrimitive(): void
    {
        $service = $this->container->make(ContextualAttributeTest_ConstructorTarget::class);

        $this->assertSame('from-attribute', $service->value);
    }

    public function testCustomAttributeResolvesMethodPrimitive(): void
    {
        $result = $this->container->call(function (
            #[ContextualAttributeTest_Value('method-attribute')]
            string $value
        ): string {
            return $value;
        });

        $this->assertSame('method-attribute', $result);
    }

    public function testExplicitCallParameterOverridesMethodAttribute(): void
    {
        $result = $this->container->call(function (
            #[ContextualAttributeTest_Value('method-attribute')]
            string $value
        ): string {
            return $value;
        }, ['value' => 'explicit']);

        $this->assertSame('explicit', $result);
    }

    public function testConstructorAttributeWinsBeforeLegacyContextualBinding(): void
    {
        $this->container
            ->when(ContextualAttributeTest_ContextualConflict::class)
            ->needs('value')
            ->give('from-legacy-contextual');

        $service = $this->container->make(ContextualAttributeTest_ContextualConflict::class);

        $this->assertSame('from-attribute', $service->value);
    }

    public function testLegacyContextualBindingStillWorksWithoutAttribute(): void
    {
        $this->container
            ->when(ContextualAttributeTest_LegacyContextualOnly::class)
            ->needs('value')
            ->give('from-legacy-contextual');

        $service = $this->container->make(ContextualAttributeTest_LegacyContextualOnly::class);

        $this->assertSame('from-legacy-contextual', $service->value);
    }

    public function testNonContextualPhpAttributesAreIgnored(): void
    {
        $service = $this->container->make(ContextualAttributeTest_IgnoredAttributeTarget::class);

        $this->assertSame('default-value', $service->value);
    }

    public function testAttributeResolutionFailureIncludesParameterAndAttribute(): void
    {
        try {
            $this->container->make(ContextualAttributeTest_ThrowingAttributeTarget::class);
            $this->fail('Expected BindingResolutionException');
        } catch (BindingResolutionException $exception) {
            $this->assertSame('value', $exception->getAbstract());
            $this->assertStringContainsString('ContextualAttributeTest_Explodes', $exception->getMessage());
            $this->assertStringContainsString('value', $exception->getMessage());
            $this->assertStringContainsString('boom', $exception->getMessage());
        }
    }
}

#[Attribute(Attribute::TARGET_PARAMETER)]
class ContextualAttributeTest_Value implements ContextualAttribute
{
    public function __construct(private string $value)
    {
    }

    public function resolve(Container $container, ReflectionParameter $parameter): mixed
    {
        return $this->value;
    }
}

#[Attribute(Attribute::TARGET_PARAMETER)]
class ContextualAttributeTest_IgnoredAttribute
{
}

#[Attribute(Attribute::TARGET_PARAMETER)]
class ContextualAttributeTest_Explodes implements ContextualAttribute
{
    public function resolve(Container $container, ReflectionParameter $parameter): mixed
    {
        throw new \RuntimeException('boom');
    }
}

class ContextualAttributeTest_ConstructorTarget
{
    public function __construct(
        #[ContextualAttributeTest_Value('from-attribute')]
        public string $value
    ) {
    }
}

class ContextualAttributeTest_ContextualConflict
{
    public function __construct(
        #[ContextualAttributeTest_Value('from-attribute')]
        public string $value
    ) {
    }
}

class ContextualAttributeTest_LegacyContextualOnly
{
    public function __construct(public string $value)
    {
    }
}

class ContextualAttributeTest_IgnoredAttributeTarget
{
    public function __construct(
        #[ContextualAttributeTest_IgnoredAttribute]
        public string $value = 'default-value'
    ) {
    }
}

class ContextualAttributeTest_ThrowingAttributeTarget
{
    public function __construct(
        #[ContextualAttributeTest_Explodes]
        public string $value
    ) {
    }
}
```

- [ ] **Step 2: Run the failing core test**

Run:

```bash
php test --pattern=ContextualAttributeTest.php
```

Expected: FAIL with `Interface "Bin\Contracts\ContextualAttribute" not found`.

- [ ] **Step 3: Add the contextual attribute contract**

Create `bin/Contracts/ContextualAttribute.php` with this exact content:

```php
<?php

declare(strict_types=1);

namespace Bin\Contracts;

use Bin\Container\Container;
use ReflectionParameter;

interface ContextualAttribute
{
    public function resolve(Container $container, ReflectionParameter $parameter): mixed;
}
```

- [ ] **Step 4: Add imports and state to the container**

In `bin/Container/Container.php`, add these imports with the existing imports:

```php
use Bin\Contracts\ContextualAttribute;
use ReflectionAttribute;
use Throwable;
```

Inside `class Container`, directly before the existing `$bindings` property, add:

```php
    public const ROUTE_PARAMETER_CONTEXT = '__route_parameters';
```

Directly after the existing `$resolved` property, add:

```php
    /**
     * Parameters currently being resolved by Container::call().
     *
     * @var array<int, array{parameters: array<string, mixed>, route: array<string, mixed>}>
     */
    protected array $parameterContextStack = [];
```

- [ ] **Step 5: Replace constructor dependency resolution**

In `bin/Container/Container.php`, replace the complete `resolveDependencies()` method with this implementation:

```php
    /**
     * 解析依赖
     */
    protected function resolveDependencies(array $dependencies): array
    {
        $results = [];

        foreach ($dependencies as $dependency) {
            [$hasAttribute, $attributeValue] = $this->resolveContextualAttribute($dependency);
            if ($hasAttribute) {
                $results[] = $attributeValue;
                continue;
            }

            $type = $dependency->getType();

            if ($type !== null && $type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $abstract = $type->getName();

                if (!empty($this->buildStack)) {
                    $buildingClass = end($this->buildStack);

                    if (isset($this->contextual[$buildingClass][$abstract])) {
                        $results[] = $this->resolveContextualBindingValue(
                            $this->contextual[$buildingClass][$abstract]
                        );
                        continue;
                    }
                }

                $results[] = $this->make($abstract);
                continue;
            }

            if (!empty($this->buildStack)) {
                $buildingClass = end($this->buildStack);
                $paramName = $dependency->getName();

                if (isset($this->contextual[$buildingClass][$paramName])) {
                    $results[] = $this->resolveContextualBindingValue(
                        $this->contextual[$buildingClass][$paramName]
                    );
                    continue;
                }
            }

            if ($dependency->isDefaultValueAvailable()) {
                $results[] = $dependency->getDefaultValue();
            } else {
                throw new BindingResolutionException(
                    $dependency->getName(),
                    "Unable to resolve parameter [{$dependency->getName()}]"
                );
            }
        }

        return $results;
    }
```

- [ ] **Step 6: Replace call helpers and method parameter resolution**

In `bin/Container/Container.php`, replace the complete `callClosure()`, `callMethod()`, and `resolveMethodParameters()` methods with this code:

```php
    /**
     * 调用闭包并注入依赖
     */
    protected function callClosure(\Closure $closure, array $parameters = []): mixed
    {
        $parameters = $this->pushParameterContext($parameters);

        try {
            $reflector = new ReflectionFunction($closure);
            $args = $this->resolveMethodParameters($reflector->getParameters(), $parameters);

            return $closure(...$args);
        } finally {
            $this->popParameterContext();
        }
    }

    /**
     * 调用对象方法并注入依赖
     */
    protected function callMethod(object $instance, string $method, array $parameters = []): mixed
    {
        $parameters = $this->pushParameterContext($parameters);

        try {
            $reflector = new ReflectionMethod($instance, $method);
            $args = $this->resolveMethodParameters($reflector->getParameters(), $parameters);

            return $instance->{$method}(...$args);
        } finally {
            $this->popParameterContext();
        }
    }

    /**
     * 解析方法参数
     */
    protected function resolveMethodParameters(array $dependencies, array $parameters): array
    {
        $results = [];

        foreach ($dependencies as $dependency) {
            $name = $dependency->getName();

            if (array_key_exists($name, $parameters)) {
                $results[] = $parameters[$name];
                continue;
            }

            [$hasAttribute, $attributeValue] = $this->resolveContextualAttribute($dependency);
            if ($hasAttribute) {
                $results[] = $attributeValue;
                continue;
            }

            $type = $dependency->getType();

            if ($type !== null && $type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $results[] = $this->make($type->getName());
                continue;
            }

            if ($dependency->isDefaultValueAvailable()) {
                $results[] = $dependency->getDefaultValue();
                continue;
            }

            if ($dependency->isVariadic()) {
                continue;
            }

            throw new BindingResolutionException(
                $name,
                "Unable to resolve parameter [{$name}] in method call"
            );
        }

        return $results;
    }
```

- [ ] **Step 7: Add contextual attribute helper methods**

In `bin/Container/Container.php`, add this complete block directly after `resolveMethodParameters()`:

```php
    /**
     * @return array{0: bool, 1: mixed}
     */
    protected function resolveContextualAttribute(ReflectionParameter $parameter): array
    {
        $attributes = $parameter->getAttributes(
            ContextualAttribute::class,
            ReflectionAttribute::IS_INSTANCEOF
        );

        if ($attributes === []) {
            return [false, null];
        }

        $attribute = $attributes[0];

        try {
            /** @var ContextualAttribute $instance */
            $instance = $attribute->newInstance();

            return [true, $instance->resolve($this, $parameter)];
        } catch (BindingResolutionException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            $attributeName = $attribute->getName();
            $parameterName = $parameter->getName();

            throw new BindingResolutionException(
                $parameterName,
                "Contextual attribute [{$attributeName}] failed resolving parameter [{$parameterName}]: {$exception->getMessage()}",
                0,
                $exception
            );
        }
    }

    protected function resolveContextualBindingValue(mixed $concrete): mixed
    {
        if ($concrete instanceof Closure) {
            return $concrete($this);
        }

        if (is_string($concrete) && ($this->bound($concrete) || class_exists($concrete) || interface_exists($concrete))) {
            return $this->make($concrete);
        }

        return $concrete;
    }

    /**
     * @return array<string, mixed>
     */
    protected function pushParameterContext(array $parameters): array
    {
        $routeParameters = [];

        if (array_key_exists(self::ROUTE_PARAMETER_CONTEXT, $parameters)) {
            $rawRouteParameters = $parameters[self::ROUTE_PARAMETER_CONTEXT];
            unset($parameters[self::ROUTE_PARAMETER_CONTEXT]);

            if (is_array($rawRouteParameters)) {
                $routeParameters = $rawRouteParameters;
            }
        }

        $this->parameterContextStack[] = [
            'parameters' => $parameters,
            'route' => $routeParameters,
        ];

        return $parameters;
    }

    protected function popParameterContext(): void
    {
        array_pop($this->parameterContextStack);
    }

    public function hasParameterContextValue(string $name): bool
    {
        for ($i = count($this->parameterContextStack) - 1; $i >= 0; $i--) {
            $context = $this->parameterContextStack[$i];

            if (array_key_exists($name, $context['route'])) {
                return true;
            }

            if (array_key_exists($name, $context['parameters'])) {
                return true;
            }
        }

        return false;
    }

    public function getParameterContextValue(string $name): mixed
    {
        for ($i = count($this->parameterContextStack) - 1; $i >= 0; $i--) {
            $context = $this->parameterContextStack[$i];

            if (array_key_exists($name, $context['route'])) {
                return $context['route'][$name];
            }

            if (array_key_exists($name, $context['parameters'])) {
                return $context['parameters'][$name];
            }
        }

        throw new BindingResolutionException(
            $name,
            "Parameter context value [{$name}] is not available"
        );
    }
```

- [ ] **Step 8: Reset parameter context during container flush**

In `bin/Container/Container.php`, inside `flush()`, after `$this->buildStack = [];`, add:

```php
        $this->parameterContextStack = [];
```

- [ ] **Step 9: Run the core tests**

Run:

```bash
php test --pattern=ContextualAttributeTest.php
```

Expected: PASS for the tests created in this task.

- [ ] **Step 10: Run existing container regressions**

Run:

```bash
php test --pattern=IocContainerTest.php
php test --pattern=ContainerTest.php
```

Expected: PASS. These tests verify legacy contextual binding, tagged bindings, scoped bindings, and method injection still work.

- [ ] **Step 11: Commit the core container attribute work**

```bash
git add tests/ContextualAttributeTest.php bin/Contracts/ContextualAttribute.php bin/Container/Container.php
git commit -m "feat: add contextual attribute resolution"
```

## Task 2: Built-In Contextual Attributes

**Files:**
- Modify: `tests/ContextualAttributeTest.php`
- Create: `bin/Container/Attributes/Config.php`
- Create: `bin/Container/Attributes/Cache.php`
- Create: `bin/Container/Attributes/Db.php`
- Create: `bin/Container/Attributes/Storage.php`
- Create: `bin/Container/Attributes/Log.php`
- Create: `bin/Container/Attributes/Auth.php`
- Create: `bin/Container/Attributes/RouteParameter.php`
- Create: `bin/Container/Attributes/Tag.php`
- Create: `bin/Container/Attributes/Give.php`

- [ ] **Step 1: Add failing built-in attribute tests**

In `tests/ContextualAttributeTest.php`, add these imports below the existing imports:

```php
use Bin\Auth\AuthManager;
use Bin\Cache\CacheManager;
use Bin\Cache\CacheRepository;
use Bin\Container\Attributes\Auth as AuthAttribute;
use Bin\Container\Attributes\Cache as CacheAttribute;
use Bin\Container\Attributes\Config as ConfigAttribute;
use Bin\Container\Attributes\Db as DbAttribute;
use Bin\Container\Attributes\Give;
use Bin\Container\Attributes\Log as LogAttribute;
use Bin\Container\Attributes\RouteParameter;
use Bin\Container\Attributes\Storage as StorageAttribute;
use Bin\Container\Attributes\Tag;
use Bin\Database\ConnectionManager;
use Bin\Filesystem\Filesystem;
use Bin\Filesystem\StorageManager;
use Bin\Log\Logger;
use Bin\Log\LogManager;
use PDO;
```

Inside `ContextualAttributeTest`, after `testAttributeResolutionFailureIncludesParameterAndAttribute()`, add these test methods:

```php
    public function testConfigAttributeInjectsConfiguredValue(): void
    {
        \config(['app.contextual_attribute_test' => 'configured']);

        $result = $this->container->call(function (
            #[ConfigAttribute('app.contextual_attribute_test')]
            string $value
        ): string {
            return $value;
        });

        $this->assertSame('configured', $result);
    }

    public function testConfigAttributeInjectsDefaultForMissingKey(): void
    {
        $result = $this->container->call(function (
            #[ConfigAttribute('app.contextual_attribute_missing', 'fallback')]
            string $value
        ): string {
            return $value;
        });

        $this->assertSame('fallback', $result);
    }

    public function testCacheAttributeInjectsNamedStore(): void
    {
        CacheManager::resetInstance();
        CacheManager::getInstance()->setConfigFor([
            'array' => ['driver' => 'array'],
        ]);
        CacheManager::getInstance()->setDefaultStoreFor('array');

        $cache = $this->container->call(function (
            #[CacheAttribute('array')]
            CacheRepository $cache
        ): CacheRepository {
            return $cache;
        });

        $this->assertInstanceOf(CacheRepository::class, $cache);
    }

    public function testDbAttributeInjectsActivePdoConnection(): void
    {
        $pdo = new PDO('sqlite::memory:');
        ConnectionManager::setConnection($pdo);

        try {
            $resolved = $this->container->call(function (
                #[DbAttribute]
                PDO $connection
            ): PDO {
                return $connection;
            });
        } finally {
            ConnectionManager::reset();
        }

        $this->assertSame($pdo, $resolved);
    }

    public function testStorageAttributeInjectsDisk(): void
    {
        StorageManager::resetInstance();
        StorageManager::getInstance()
            ->setConfig([
                'local' => [
                    'driver' => 'local',
                    'root' => sys_get_temp_dir(),
                ],
            ])
            ->setDefaultDisk('local');

        $disk = $this->container->call(function (
            #[StorageAttribute('local')]
            Filesystem $disk
        ): Filesystem {
            return $disk;
        });

        $this->assertInstanceOf(Filesystem::class, $disk);
    }

    public function testLogAttributeInjectsChannel(): void
    {
        LogManager::resetInstance();

        $logger = $this->container->call(function (
            #[LogAttribute('audit')]
            Logger $logger
        ): Logger {
            return $logger;
        });

        $this->assertSame(LogManager::getInstance()->channelFor('audit'), $logger);
    }

    public function testAuthAttributeInjectsDefaultAuthManager(): void
    {
        AuthManager::resetInstance();

        $auth = $this->container->call(function (
            #[AuthAttribute]
            AuthManager $auth
        ): AuthManager {
            return $auth;
        });

        $this->assertSame(AuthManager::getInstance(), $auth);
    }

    public function testRouteParameterAttributeReadsCallContext(): void
    {
        $result = $this->container->call(function (
            #[RouteParameter('slug')]
            string $slug
        ): string {
            return $slug;
        }, ['slug' => 'from-call-context']);

        $this->assertSame('from-call-context', $result);
    }

    public function testRouteParameterAttributeUsesDefaultWhenMissing(): void
    {
        $result = $this->container->call(function (
            #[RouteParameter('missing')]
            string $value = 'fallback'
        ): string {
            return $value;
        });

        $this->assertSame('fallback', $result);
    }

    public function testRouteParameterAttributeThrowsForMissingRequiredValue(): void
    {
        try {
            $this->container->call(function (
                #[RouteParameter('missing')]
                string $value
            ): string {
                return $value;
            });
            $this->fail('Expected BindingResolutionException');
        } catch (BindingResolutionException $exception) {
            $this->assertSame('value', $exception->getAbstract());
            $this->assertStringContainsString('route parameter [missing]', $exception->getMessage());
        }
    }

    public function testTagAttributeInjectsTaggedServices(): void
    {
        $this->container->bind('contextual.handler.one', ContextualAttributeTest_TaggedOne::class);
        $this->container->bind('contextual.handler.two', ContextualAttributeTest_TaggedTwo::class);
        $this->container->tag(['contextual.handler.one', 'contextual.handler.two'], 'contextual.handlers');

        $handlers = $this->container->call(function (
            #[Tag('contextual.handlers')]
            array $handlers
        ): array {
            return $handlers;
        });

        $this->assertCount(2, $handlers);
        $this->assertInstanceOf(ContextualAttributeTest_TaggedOne::class, $handlers[0]);
        $this->assertInstanceOf(ContextualAttributeTest_TaggedTwo::class, $handlers[1]);
    }

    public function testGiveAttributeInjectsExplicitImplementation(): void
    {
        $service = $this->container->call(function (
            #[Give(ContextualAttributeTest_GivenImplementation::class)]
            ContextualAttributeTest_GivenContract $service
        ): ContextualAttributeTest_GivenContract {
            return $service;
        });

        $this->assertInstanceOf(ContextualAttributeTest_GivenImplementation::class, $service);
    }

    public function testNonDefaultAuthGuardFailsClearly(): void
    {
        try {
            $this->container->call(function (
                #[AuthAttribute('admin')]
                AuthManager $auth
            ): AuthManager {
                return $auth;
            });
            $this->fail('Expected BindingResolutionException');
        } catch (BindingResolutionException $exception) {
            $this->assertStringContainsString('Auth guard [admin]', $exception->getMessage());
        }
    }

    public function testNamedDbConnectionFailsClearly(): void
    {
        try {
            $this->container->call(function (
                #[DbAttribute('analytics')]
                PDO $connection
            ): PDO {
                return $connection;
            });
            $this->fail('Expected BindingResolutionException');
        } catch (BindingResolutionException $exception) {
            $this->assertStringContainsString('Database connection [analytics]', $exception->getMessage());
        }
    }
```

After the existing helper classes at the bottom of the file, add:

```php
class ContextualAttributeTest_TaggedOne
{
}

class ContextualAttributeTest_TaggedTwo
{
}

interface ContextualAttributeTest_GivenContract
{
}

class ContextualAttributeTest_GivenImplementation implements ContextualAttributeTest_GivenContract
{
}
```

- [ ] **Step 2: Run the built-in tests and verify failure**

Run:

```bash
php test --pattern=ContextualAttributeTest.php
```

Expected: FAIL with class-not-found errors for `Bin\Container\Attributes\Config`, `Cache`, `Db`, `Storage`, `Log`, `Auth`, `RouteParameter`, `Tag`, and `Give`.

- [ ] **Step 3: Add the `Config` attribute**

Create `bin/Container/Attributes/Config.php`:

```php
<?php

declare(strict_types=1);

namespace Bin\Container\Attributes;

use Attribute;
use Bin\Container\Container;
use Bin\Contracts\ContextualAttribute;
use ReflectionParameter;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class Config implements ContextualAttribute
{
    public function __construct(
        private string $key,
        private mixed $default = null
    ) {
    }

    public function resolve(Container $container, ReflectionParameter $parameter): mixed
    {
        return \config()->get($this->key, $this->default);
    }
}
```

- [ ] **Step 4: Add the `Cache` attribute**

Create `bin/Container/Attributes/Cache.php`:

```php
<?php

declare(strict_types=1);

namespace Bin\Container\Attributes;

use Attribute;
use Bin\Cache\CacheManager;
use Bin\Container\Container;
use Bin\Contracts\ContextualAttribute;
use ReflectionParameter;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class Cache implements ContextualAttribute
{
    public function __construct(private ?string $store = null)
    {
    }

    public function resolve(Container $container, ReflectionParameter $parameter): mixed
    {
        return CacheManager::getInstance()->storeFor($this->store);
    }
}
```

- [ ] **Step 5: Add the `Db` attribute**

Create `bin/Container/Attributes/Db.php`:

```php
<?php

declare(strict_types=1);

namespace Bin\Container\Attributes;

use Attribute;
use Bin\Container\Container;
use Bin\Container\Exceptions\BindingResolutionException;
use Bin\Contracts\ContextualAttribute;
use Bin\Database\ConnectionManager;
use ReflectionParameter;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class Db implements ContextualAttribute
{
    public function __construct(private ?string $connection = null)
    {
    }

    public function resolve(Container $container, ReflectionParameter $parameter): mixed
    {
        if ($this->connection !== null && $this->connection !== 'default') {
            throw new BindingResolutionException(
                $parameter->getName(),
                "Database connection [{$this->connection}] is not available; First currently exposes the active default connection"
            );
        }

        if ($container->bound('db.connection')) {
            return $container->make('db.connection');
        }

        return ConnectionManager::getConnection();
    }
}
```

- [ ] **Step 6: Add the `Storage` attribute**

Create `bin/Container/Attributes/Storage.php`:

```php
<?php

declare(strict_types=1);

namespace Bin\Container\Attributes;

use Attribute;
use Bin\Container\Container;
use Bin\Contracts\ContextualAttribute;
use Bin\Filesystem\StorageManager;
use ReflectionParameter;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class Storage implements ContextualAttribute
{
    public function __construct(private ?string $disk = null)
    {
    }

    public function resolve(Container $container, ReflectionParameter $parameter): mixed
    {
        return StorageManager::getInstance()->disk($this->disk);
    }
}
```

- [ ] **Step 7: Add the `Log` attribute**

Create `bin/Container/Attributes/Log.php`:

```php
<?php

declare(strict_types=1);

namespace Bin\Container\Attributes;

use Attribute;
use Bin\Container\Container;
use Bin\Contracts\ContextualAttribute;
use Bin\Log\LogManager;
use ReflectionParameter;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class Log implements ContextualAttribute
{
    public function __construct(private ?string $channel = null)
    {
    }

    public function resolve(Container $container, ReflectionParameter $parameter): mixed
    {
        return LogManager::getInstance()->channelFor($this->channel);
    }
}
```

- [ ] **Step 8: Add the `Auth` attribute**

Create `bin/Container/Attributes/Auth.php`:

```php
<?php

declare(strict_types=1);

namespace Bin\Container\Attributes;

use Attribute;
use Bin\Auth\AuthManager;
use Bin\Container\Container;
use Bin\Container\Exceptions\BindingResolutionException;
use Bin\Contracts\ContextualAttribute;
use ReflectionParameter;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class Auth implements ContextualAttribute
{
    public function __construct(private ?string $guard = null)
    {
    }

    public function resolve(Container $container, ReflectionParameter $parameter): mixed
    {
        if ($this->guard !== null && $this->guard !== 'default') {
            throw new BindingResolutionException(
                $parameter->getName(),
                "Auth guard [{$this->guard}] is not available; First currently exposes the default AuthManager"
            );
        }

        return AuthManager::getInstance();
    }
}
```

- [ ] **Step 9: Add the `RouteParameter` attribute**

Create `bin/Container/Attributes/RouteParameter.php`:

```php
<?php

declare(strict_types=1);

namespace Bin\Container\Attributes;

use Attribute;
use Bin\Container\Container;
use Bin\Container\Exceptions\BindingResolutionException;
use Bin\Contracts\ContextualAttribute;
use ReflectionParameter;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class RouteParameter implements ContextualAttribute
{
    public function __construct(private string $name)
    {
    }

    public function resolve(Container $container, ReflectionParameter $parameter): mixed
    {
        if ($container->hasParameterContextValue($this->name)) {
            return $container->getParameterContextValue($this->name);
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        if ($parameter->allowsNull()) {
            return null;
        }

        throw new BindingResolutionException(
            $parameter->getName(),
            "Unable to resolve route parameter [{$this->name}] for parameter [{$parameter->getName()}]"
        );
    }
}
```

- [ ] **Step 10: Add the `Tag` attribute**

Create `bin/Container/Attributes/Tag.php`:

```php
<?php

declare(strict_types=1);

namespace Bin\Container\Attributes;

use Attribute;
use Bin\Container\Container;
use Bin\Contracts\ContextualAttribute;
use ReflectionParameter;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class Tag implements ContextualAttribute
{
    public function __construct(private string $tag)
    {
    }

    public function resolve(Container $container, ReflectionParameter $parameter): mixed
    {
        return $container->tagged($this->tag);
    }
}
```

- [ ] **Step 11: Add the `Give` attribute**

Create `bin/Container/Attributes/Give.php`:

```php
<?php

declare(strict_types=1);

namespace Bin\Container\Attributes;

use Attribute;
use Bin\Container\Container;
use Bin\Contracts\ContextualAttribute;
use ReflectionParameter;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class Give implements ContextualAttribute
{
    public function __construct(private string $abstract)
    {
    }

    public function resolve(Container $container, ReflectionParameter $parameter): mixed
    {
        return $container->make($this->abstract);
    }
}
```

- [ ] **Step 12: Run built-in attribute tests**

Run:

```bash
php test --pattern=ContextualAttributeTest.php
```

Expected: PASS.

- [ ] **Step 13: Commit built-in attributes**

```bash
git add tests/ContextualAttributeTest.php bin/Container/Attributes
git commit -m "feat: add built in contextual attributes"
```

## Task 3: Route Dispatcher Parameter Context

**Files:**
- Modify: `bin/Routing/ControllerDispatcher.php`
- Modify: `tests/DispatcherIntegrationTest.php`

- [ ] **Step 1: Add failing dispatcher route parameter attribute tests**

In `tests/DispatcherIntegrationTest.php`, add this import with the existing imports:

```php
use Bin\Container\Attributes\RouteParameter;
```

After `testDispatcherClosureWithUrlParam()`, add:

```php
    public function testDispatcherClosureRouteParameterAttributeReadsDifferentUrlKey(): void
    {
        $request = \Bin\Request\Request::capture();
        $request->setUrlParam(['post' => '42']);
        Container::getInstance()->instance(\Bin\Request\Request::class, $request);

        $closure = fn (
            #[RouteParameter('post')]
            string $postId
        ): string => "post={$postId}";

        $route = new Route('GET', '/posts/{post}', $closure);
        $result = $this->dispatcher->dispatchClosure($closure, $route);

        $this->assertEquals('post=42', $result->getContent());
    }

    public function testDispatcherControllerRouteParameterAttributeReadsDifferentUrlKey(): void
    {
        $request = \Bin\Request\Request::capture();
        $request->setUrlParam(['post' => '84']);
        Container::getInstance()->instance(\Bin\Request\Request::class, $request);

        $controller = new class {
            public function show(
                #[RouteParameter('post')]
                string $postId
            ): string {
                return "post={$postId}";
            }
        };

        $className = get_class($controller);
        Container::getInstance()->instance($className, $controller);

        $route = new Route('GET', '/posts/{post}', $className . '@show');
        $result = $this->dispatcher->dispatch($className, 'show', $route);

        $this->assertEquals('post=84', $result->getContent());
    }
```

- [ ] **Step 2: Run dispatcher tests and verify failure**

Run:

```bash
php test --pattern=DispatcherIntegrationTest.php --filter=RouteParameterAttribute
```

Expected: FAIL because `#[RouteParameter('post')]` cannot read raw route parameter `post` when the action parameter is named `$postId`.

- [ ] **Step 3: Pass raw route parameters through the dispatcher**

In `bin/Routing/ControllerDispatcher.php`, inside `buildParameterMap()`, replace:

```php
        $urlParams = $request->getUrlParam() ?? [];
        $parameters = [];
```

with:

```php
        $urlParams = $request->getUrlParam() ?? [];
        $parameters = [
            \Bin\Container\Container::ROUTE_PARAMETER_CONTEXT => $urlParams,
        ];
```

In the same method, replace:

```php
            if (isset($urlParams[$name])) {
                $parameters[$name] = $urlParams[$name];
            }
```

with:

```php
            if (array_key_exists($name, $urlParams)) {
                $parameters[$name] = $urlParams[$name];
            }
```

Also replace these two object parameter checks so null route values are handled consistently:

```php
                if (RouteBinding::hasBinding($name) && isset($urlParams[$name])) {
```

with:

```php
                if (RouteBinding::hasBinding($name) && array_key_exists($name, $urlParams)) {
```

and:

```php
                if (class_exists($typeName) && is_subclass_of($typeName, Model::class) && isset($urlParams[$name])) {
```

with:

```php
                if (class_exists($typeName) && is_subclass_of($typeName, Model::class) && array_key_exists($name, $urlParams)) {
```

- [ ] **Step 4: Run dispatcher route parameter tests**

Run:

```bash
php test --pattern=DispatcherIntegrationTest.php --filter=RouteParameterAttribute
```

Expected: PASS.

- [ ] **Step 5: Run dispatcher integration regression**

Run:

```bash
php test --pattern=DispatcherIntegrationTest.php
```

Expected: PASS.

- [ ] **Step 6: Commit dispatcher parameter context**

```bash
git add bin/Routing/ControllerDispatcher.php tests/DispatcherIntegrationTest.php
git commit -m "feat: pass route parameter context to container"
```

## Task 4: Scoped Reset at Request and Job Boundaries

**Files:**
- Modify: `bin/Route/RouteAction.php`
- Modify: `bin/Queue/Worker.php`
- Modify: `bin/Queue/Drivers/SyncQueue.php`
- Modify: `bin/Queue/Dispatchable.php`
- Modify: `tests/DispatcherIntegrationTest.php`
- Modify: `tests/QueueTest.php`
- Modify: `tests/QueueTestHelpers.php`

- [ ] **Step 1: Add failing request boundary scoped reset test**

In `tests/DispatcherIntegrationTest.php`, after `testRouteActionDispatchUsesProvidedRequestInstance()`, add:

```php
    public function testRouteActionDispatchResetsScopedBindingsBetweenRequests(): void
    {
        $originalServer = $_SERVER;
        $seen = [];

        try {
            App::getInstance()->scoped(DispatcherIntegrationScopedProbe::class);

            \Bin\Route\RouteCollection::get('/scoped', function (DispatcherIntegrationScopedProbe $probe) use (&$seen): string {
                $seen[] = spl_object_id($probe);

                return 'ok';
            });

            $_SERVER['REQUEST_METHOD'] = 'GET';
            $_SERVER['REQUEST_URI'] = '/scoped';
            RouteAction::dispatch(Request::capture());

            $_SERVER['REQUEST_METHOD'] = 'GET';
            $_SERVER['REQUEST_URI'] = '/scoped';
            RouteAction::dispatch(Request::capture());

            $this->assertCount(2, $seen);
            $this->assertNotSame($seen[0], $seen[1]);
        } finally {
            $_SERVER = $originalServer;
            App::getInstance()->forget(DispatcherIntegrationScopedProbe::class);
        }
    }
```

At the bottom of `tests/DispatcherIntegrationTest.php`, before `class DispatcherIntegrationAuthUser`, add:

```php
class DispatcherIntegrationScopedProbe
{
}
```

- [ ] **Step 2: Add failing queue boundary scoped reset tests**

In `tests/QueueTest.php`, inside `setUp()`, after `\QueueTest_InjectedJob::resetState();`, add:

```php
        \QueueTest_ScopedJob::resetState();
```

In `tests/QueueTest.php`, after `testWorkerInvokesJobHandleThroughContainer()`, add:

```php
    public function testWorkerProcessResetsScopedBindingsBetweenJobs(): void
    {
        App::getInstance()->scoped(\QueueTest_ScopedDependency::class);

        $manager = new QueueManager();
        $manager->setConfig([
            'sync' => ['driver' => 'sync'],
        ]);

        try {
            $worker = new Worker($manager);
            $worker->process(new \QueueTest_ScopedJob(), 'sync', 'default', 3);
            $worker->process(new \QueueTest_ScopedJob(), 'sync', 'default', 3);

            $this->assertCount(2, \QueueTest_ScopedJob::$dependencyIds);
            $this->assertNotSame(\QueueTest_ScopedJob::$dependencyIds[0], \QueueTest_ScopedJob::$dependencyIds[1]);
        } finally {
            App::getInstance()->getContainer()->forget(\QueueTest_ScopedDependency::class);
        }
    }
```

After `testDispatchSyncInvokesJobHandleThroughContainer()`, add:

```php
    public function testSyncQueueResetsScopedBindingsBetweenJobs(): void
    {
        App::getInstance()->scoped(\QueueTest_ScopedDependency::class);

        try {
            $queue = new SyncQueue();
            $queue->push(new \QueueTest_ScopedJob());
            $queue->push(new \QueueTest_ScopedJob());

            $this->assertCount(2, \QueueTest_ScopedJob::$dependencyIds);
            $this->assertNotSame(\QueueTest_ScopedJob::$dependencyIds[0], \QueueTest_ScopedJob::$dependencyIds[1]);
        } finally {
            App::getInstance()->getContainer()->forget(\QueueTest_ScopedDependency::class);
        }
    }

    public function testDispatchSyncResetsScopedBindingsBetweenJobs(): void
    {
        App::getInstance()->scoped(\QueueTest_ScopedDependency::class);

        try {
            \QueueTest_ScopedJob::dispatchSync();
            \QueueTest_ScopedJob::dispatchSync();

            $this->assertCount(2, \QueueTest_ScopedJob::$dependencyIds);
            $this->assertNotSame(\QueueTest_ScopedJob::$dependencyIds[0], \QueueTest_ScopedJob::$dependencyIds[1]);
        } finally {
            App::getInstance()->getContainer()->forget(\QueueTest_ScopedDependency::class);
        }
    }
```

In `tests/QueueTestHelpers.php`, after `QueueTest_InjectedJob`, add:

```php
class QueueTest_ScopedDependency
{
}

class QueueTest_ScopedJob extends Job
{
    use Dispatchable;

    /** @var int[] */
    public static array $dependencyIds = [];

    public function handle(?QueueTest_ScopedDependency $dependency = null): void
    {
        if ($dependency === null) {
            throw new \RuntimeException('Scoped dependency was not injected');
        }

        self::$dependencyIds[] = spl_object_id($dependency);
    }

    public static function resetState(): void
    {
        self::$dependencyIds = [];
    }
}
```

- [ ] **Step 3: Run scoped boundary tests and verify failure**

Run:

```bash
php test --pattern=DispatcherIntegrationTest.php --filter=ScopedBindingsBetweenRequests
php test --pattern=QueueTest.php --filter=ScopedBindingsBetweenJobs
```

Expected: FAIL because scoped instances are reused across request and job boundaries.

- [ ] **Step 4: Reset scope at request dispatch boundary**

In `bin/Route/RouteAction.php`, replace the beginning of `dispatch(Request $request)`:

```php
    public static function dispatch(Request $request): mixed
    {
        AuthManager::resetUser();
        $request->setUserResolver(fn (): ?object => AuthManager::user());
        App::getInstance()->instance(Request::class, $request);
```

with:

```php
    public static function dispatch(Request $request): mixed
    {
        $app = App::getInstance();
        $app->resetScope();

        AuthManager::resetUser();
        $request->setUserResolver(fn (): ?object => AuthManager::user());
        $app->instance(Request::class, $request);
```

- [ ] **Step 5: Reset scope around worker job execution**

In `bin/Queue/Worker.php`, replace `process()` with:

```php
    /**
     * 处理单个任务
     */
    public function process(Job $job, string $connection, string $queue, int $tries = 3): void
    {
        $container = App::getInstance()->getContainer();
        $container->resetScope();

        try {
            $container->call([$job, 'handle']);
            $this->manager->connection($connection)->delete($job);
        } catch (\Throwable $e) {
            $this->handleFailure($job, $connection, $queue, $e, $tries);
        } finally {
            $container->resetScope();
        }
    }
```

- [ ] **Step 6: Reset scope around sync queue execution**

In `bin/Queue/Drivers/SyncQueue.php`, replace this block in `push()`:

```php
        if ($job instanceof Job) {
            $job->setAttempts($job->getAttempts() + 1);
            App::getInstance()->getContainer()->call([$job, 'handle']);
        }
```

with:

```php
        if ($job instanceof Job) {
            $job->setAttempts($job->getAttempts() + 1);

            $container = App::getInstance()->getContainer();
            $container->resetScope();

            try {
                $container->call([$job, 'handle']);
            } finally {
                $container->resetScope();
            }
        }
```

- [ ] **Step 7: Reset scope around dispatchSync execution**

In `bin/Queue/Dispatchable.php`, replace `dispatchSync()` with:

```php
    /**
     * 同步执行（不入队）
     */
    public static function dispatchSync(mixed ...$args): void
    {
        $job = new static(...$args);
        $container = \Bin\App\App::getInstance()->getContainer();
        $container->resetScope();

        try {
            $container->call([$job, 'handle']);
        } finally {
            $container->resetScope();
        }
    }
```

- [ ] **Step 8: Run scoped boundary tests**

Run:

```bash
php test --pattern=DispatcherIntegrationTest.php --filter=ScopedBindingsBetweenRequests
php test --pattern=QueueTest.php --filter=ScopedBindingsBetweenJobs
```

Expected: PASS.

- [ ] **Step 9: Run routing and queue regressions**

Run:

```bash
php test --pattern=DispatcherIntegrationTest.php
php test --pattern=QueueTest.php
```

Expected: PASS.

- [ ] **Step 10: Commit scoped boundary resets**

```bash
git add bin/Route/RouteAction.php bin/Queue/Worker.php bin/Queue/Drivers/SyncQueue.php bin/Queue/Dispatchable.php tests/DispatcherIntegrationTest.php tests/QueueTest.php tests/QueueTestHelpers.php
git commit -m "feat: reset scoped bindings at runtime boundaries"
```

## Task 5: Documentation and Spec Completion Note

**Files:**
- Modify: `docs/superpowers/specs/2026-06-11-track-b-contextual-attributes-design.md`

- [ ] **Step 1: Add the implementation note**

Append this section to `docs/superpowers/specs/2026-06-11-track-b-contextual-attributes-design.md`:

```markdown
## Implementation Notes

Implemented by plan `docs/superpowers/plans/2026-06-11-track-b-contextual-attributes.md`.

- Container parameter resolution owns contextual attributes for constructors and `Container::call()`.
- Built-ins cover implemented First services: config, cache, database, storage, log, default auth manager, route parameter, tagged services, and explicit implementation.
- Route dispatch passes raw route parameters to the container through `Container::ROUTE_PARAMETER_CONTEXT`; dispatchers do not inspect contextual attributes.
- Scoped bindings reset at direct `resetScope()`, request dispatch, worker job, sync queue, and `dispatchSync()` boundaries.
- Non-default auth guards and named database connections produce explicit `BindingResolutionException` messages because First does not yet expose those runtime abstractions.
```

- [ ] **Step 2: Commit docs**

```bash
git add docs/superpowers/specs/2026-06-11-track-b-contextual-attributes-design.md
git commit -m "docs: record contextual attribute implementation notes"
```

## Task 6: Final Verification and Graph Refresh

**Files:**
- Modify: `graphify-out/` generated files if graphify updates tracked files

- [ ] **Step 1: Run targeted tests**

Run:

```bash
php test --pattern=ContextualAttributeTest.php
php test --pattern=IocContainerTest.php
php test --pattern=ContainerTest.php
php test --pattern=DispatcherIntegrationTest.php
php test --pattern=QueueTest.php
php test --pattern=RuntimeCompatibilityTest.php
```

Expected: all commands PASS.

- [ ] **Step 2: Run the full suite**

Run:

```bash
php test
```

Expected: PASS with all tests green.

- [ ] **Step 3: Refresh graphify after code changes**

Run:

```bash
graphify update .
```

Expected: command completes successfully. If graphify reports no tracked changes, do not commit generated output.

- [ ] **Step 4: Check git status**

Run:

```bash
git status --short
```

Expected: clean working tree. If `graphify update .` changed tracked graph files, inspect them and commit with:

```bash
git add graphify-out
git commit -m "docs: refresh graph after contextual attributes"
```

- [ ] **Step 5: Record final commit list**

Run:

```bash
git log --oneline master..HEAD
```

Expected: commits for core contextual resolution, built-in attributes, route parameter context, scoped boundary resets, and docs.

## Self-Review

Spec coverage:

- Container-owned attribute resolution: Task 1.
- Custom `ContextualAttribute` contract: Task 1.
- Constructor and method/closure injection: Task 1.
- Explicit parameters override method attributes: Task 1.
- Attributes before legacy contextual binding: Task 1.
- Built-ins for implemented First services: Task 2.
- Route parameter context without dispatcher attribute inspection: Task 3.
- Existing contextual binding APIs still passing: Task 1 and Task 6.
- Scoped request and job lifecycle reset: Task 4.
- Clear error messages for missing route parameters, non-default auth guards, named database connections, and attribute failures: Tasks 1 and 2.

Placeholder scan:

- No placeholder markers or vague test-only steps remain.
- Every code-changing step names exact files and contains the code block or exact replacement.

Type consistency:

- The contract signature is `resolve(Container $container, ReflectionParameter $parameter): mixed`.
- Every built-in attribute implements `Bin\Contracts\ContextualAttribute`.
- The reserved route parameter key is `Container::ROUTE_PARAMETER_CONTEXT` with value `__route_parameters`.
- Scoped boundary tests use `scoped()` and `resetScope()` already present in `App` and `Container`.
