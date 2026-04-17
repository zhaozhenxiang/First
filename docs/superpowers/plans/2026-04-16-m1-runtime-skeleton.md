# M1 Runtime Skeleton Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make bootstrapping, composition, request/console execution, exception handling, response normalization, and route infrastructure deterministic under one runtime skeleton.

**Architecture:** Keep the public API mostly stable, but move the runtime onto explicit bootstrappers, container-managed assembly, a single request capture path, a shared exception handler contract, and a response factory. Drive every change from focused failing tests in the existing lifecycle, dispatcher, exception, response, console, and route suites.

**Tech Stack:** PHP 8.3+, custom Bin framework runtime, custom `php test` runner, service providers, HTTP/Console kernels, route collection/binding, response helpers.

---

## File Structure

### Runtime Bootstrapping

- Modify: `bin/App/App.php`
  Responsibility: shared application state, container ownership, provider repository access, kernel accessors.
- Modify: `bin/Foundation/HttpKernel.php`
  Responsibility: HTTP bootstrap orchestration, request capture, response return.
- Modify: `bin/Foundation/ConsoleKernel.php`
  Responsibility: Console bootstrap orchestration, command execution, console exception exit path.
- Create: `bin/Foundation/Bootstrap/LoadMiddlewareConfiguration.php`
  Responsibility: load `config/middleware.php` into `MiddlewareStack` as an HTTP-only bootstrapper.
- Create: `bin/Foundation/Bootstrap/LoadRoutes.php`
  Responsibility: require `app/routes.php` as an HTTP-only bootstrapper.

### Container and Assembly

- Modify: `bin/Providers/ProviderRepository.php`
  Responsibility: create providers through `App`/container instead of hard-coding constructor assembly.
- Modify: `bin/Route/RouteAction.php`
  Responsibility: resolve `ControllerDispatcher` through the app container and execute one explicit request path.
- Modify: `bin/Routing/ControllerDispatcher.php`
  Responsibility: normalize controller/closure results through a response factory instead of ad hoc `new Response(...)`.

### Exception and Response Output

- Modify: `bin/Foundation/Bootstrap/HandleExceptions.php`
  Responsibility: resolve one exception handler instance and use it consistently for uncaught exceptions.
- Modify: `bin/Exception/ExceptionHandler.php`
  Responsibility: report/render HTTP errors, render console errors, and centralize status / payload selection.
- Modify: `bin/Providers/ResponseServiceProvider.php`
  Responsibility: register `ResponseFactory`.
- Create: `bin/Response/ResponseFactory.php`
  Responsibility: normalize strings, arrays, `Response`, redirect targets, and JSON payloads into `Response`.
- Modify: `bin/Func/helpers/http.php`
  Responsibility: route `response()` and `redirect()` helpers through `ResponseFactory`.

### Route Infrastructure

- Modify: `bin/Route/Route.php`
  Responsibility: deterministic named-route URL generation, required/optional parameter handling.
- Modify: `bin/Route/RouteCollection.php`
  Responsibility: keep named route generation delegated through the hardened `Route::url()`.
- Modify: `bin/Route/RouteBinding.php`
  Responsibility: translate missing bound models into HTTP-aware not-found exceptions.
- Create: `bin/Exception/UrlGenerationException.php`
  Responsibility: explicit exception type for missing required route parameters.

### Tests

- Modify: `tests/ApplicationLifecycleTest.php`
- Modify: `tests/AppTest.php`
- Modify: `tests/DispatcherIntegrationTest.php`
- Modify: `tests/ExceptionHandlerTest.php`
- Modify: `tests/ResponseTest.php`
- Modify: `tests/ConsoleArtisanParityTest.php`
- Modify: `tests/RouteEnhancementTest.php`

---

### Task 1: Promote HTTP-Only Setup Into the Bootstrap Pipeline

**Files:**
- Create: `bin/Foundation/Bootstrap/LoadMiddlewareConfiguration.php`
- Create: `bin/Foundation/Bootstrap/LoadRoutes.php`
- Modify: `bin/Foundation/HttpKernel.php`
- Modify: `bin/Foundation/ConsoleKernel.php`
- Test: `tests/ApplicationLifecycleTest.php`

- [ ] **Step 1: Write the failing tests**

Add these methods to `tests/ApplicationLifecycleTest.php`:

```php
public function testHttpKernelIncludesHttpOnlyBootstrappers(): void
{
    $app = App::getInstance();
    $kernel = new HttpKernel($app);

    $bootstrappers = $kernel->getBootstrappers();

    $this->assertContains(\Bin\Foundation\Bootstrap\LoadMiddlewareConfiguration::class, $bootstrappers);
    $this->assertContains(\Bin\Foundation\Bootstrap\LoadRoutes::class, $bootstrappers);
}

public function testHttpBootstrapRunsHttpOnlyStagesAfterConsoleBootstrap(): void
{
    $app = App::getInstance();
    $console = new ConsoleKernel($app);
    $http = new HttpKernel($app);

    $console->bootstrap();
    $this->assertFalse($app->hasBeenBootstrappedBy(\Bin\Foundation\Bootstrap\SetRequestContext::class));

    $reflection = new \ReflectionMethod(HttpKernel::class, 'bootstrap');
    $reflection->setAccessible(true);
    $reflection->invoke($http);

    $this->assertTrue($app->hasBeenBootstrappedBy(\Bin\Foundation\Bootstrap\SetRequestContext::class));
    $this->assertTrue($app->hasBeenBootstrappedBy(\Bin\Foundation\Bootstrap\LoadMiddlewareConfiguration::class));
    $this->assertTrue($app->hasBeenBootstrappedBy(\Bin\Foundation\Bootstrap\LoadRoutes::class));
}
```

- [ ] **Step 2: Run the lifecycle tests to verify they fail**

Run: `php test tests/ApplicationLifecycleTest.php`

Expected: FAIL with bootstrapper assertions such as:

```text
Failed asserting that an array contains 'Bin\Foundation\Bootstrap\LoadMiddlewareConfiguration'
```

- [ ] **Step 3: Implement the minimal bootstrap pipeline changes**

Create `bin/Foundation/Bootstrap/LoadMiddlewareConfiguration.php`:

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
        $configPath = $app->configPath('middleware.php');

        if (!file_exists($configPath)) {
            return;
        }

        $config = require $configPath;
        MiddlewareStack::loadFromConfig($config);
    }
}
```

Create `bin/Foundation/Bootstrap/LoadRoutes.php`:

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
        $routeFile = $app->basePath() . '/app/routes.php';

        if (file_exists($routeFile)) {
            require_once $routeFile;
        }
    }
}
```

Update `bin/Foundation/HttpKernel.php`:

```php
protected array $bootstrappers = [
    \Bin\Foundation\Bootstrap\LoadEnvironmentVariables::class,
    \Bin\Foundation\Bootstrap\HandleExceptions::class,
    \Bin\Foundation\Bootstrap\LoadConfiguration::class,
    \Bin\Foundation\Bootstrap\SetRequestContext::class,
    \Bin\Foundation\Bootstrap\RegisterProviders::class,
    \Bin\Foundation\Bootstrap\BootProviders::class,
    \Bin\Foundation\Bootstrap\LoadMiddlewareConfiguration::class,
    \Bin\Foundation\Bootstrap\LoadRoutes::class,
];

protected function bootstrap(): void
{
    $this->app->bootstrapWith($this->bootstrappers);
}
```

Update `bin/Foundation/ConsoleKernel.php`:

```php
public function bootstrap(): void
{
    if ($this->bootstrapped) {
        return;
    }

    $this->app->bootstrapWith($this->bootstrappers);
    $this->bootstrapped = true;
}
```

- [ ] **Step 4: Run the lifecycle tests again**

Run: `php test tests/ApplicationLifecycleTest.php`

Expected: PASS with `Passed:` summary and `Failed: 0`.

- [ ] **Step 5: Commit the bootstrap pipeline change**

```bash
git add tests/ApplicationLifecycleTest.php \
  bin/Foundation/HttpKernel.php \
  bin/Foundation/ConsoleKernel.php \
  bin/Foundation/Bootstrap/LoadMiddlewareConfiguration.php \
  bin/Foundation/Bootstrap/LoadRoutes.php
git commit -m "refactor: move http setup into bootstrap pipeline"
```

### Task 2: Make the App Container the Single Assembly Point

**Files:**
- Modify: `bin/App/App.php`
- Modify: `bin/Providers/ProviderRepository.php`
- Modify: `bin/Route/RouteAction.php`
- Test: `tests/AppTest.php`
- Test: `tests/DispatcherIntegrationTest.php`

- [ ] **Step 1: Write the failing container-assembly tests**

Add these methods to `tests/AppTest.php`:

```php
public function testAppResolvesHttpKernelThroughContainer(): void
{
    $custom = new \Bin\Foundation\HttpKernel($this->app);
    $this->app->instance(\Bin\Foundation\HttpKernel::class, $custom);

    $this->assertSame($custom, $this->app->getHttpKernel());
}

public function testProviderRepositoryBuildsProvidersThroughContainer(): void
{
    $dependency = new ProviderDependency();
    $dependency->name = 'from-container';

    $this->app->instance(ProviderDependency::class, $dependency);
    $this->app->register(ContainerAwareProvider::class, true);
    $this->app->make('provider.dependency');

    $resolved = $this->app->make('provider.dependency');

    $this->assertSame($dependency, $resolved);
    $this->assertEquals('from-container', $resolved->name);
}
```

Append these helper classes to the bottom of `tests/AppTest.php`:

```php
class ProviderDependency
{
    public string $name = 'default';
}

class ContainerAwareProvider extends ServiceProvider
{
    public function __construct(\Bin\App\App $app, private ProviderDependency $dependency)
    {
        parent::__construct($app);
    }

    public function register(): void
    {
        $this->instance('provider.dependency', $this->dependency);
    }

    public function provides(): array
    {
        return ['provider.dependency'];
    }
}
```

Add this method to `tests/DispatcherIntegrationTest.php`:

```php
public function testRouteActionResolvesDispatcherFromContainer(): void
{
    $custom = new ControllerDispatcher();
    \Bin\App\App::getInstance()->instance(ControllerDispatcher::class, $custom);

    $property = new \ReflectionProperty(RouteAction::class, 'dispatcher');
    $property->setAccessible(true);
    $property->setValue(null, null);

    $this->assertSame($custom, RouteAction::getDispatcher());
}
```

- [ ] **Step 2: Run the focused test files and verify they fail**

Run:

```bash
php test tests/AppTest.php
php test tests/DispatcherIntegrationTest.php
```

Expected:

- `tests/AppTest.php` FAIL with provider constructor argument errors or kernel identity mismatch.
- `tests/DispatcherIntegrationTest.php` FAIL because `RouteAction::getDispatcher()` does not resolve from the container.

- [ ] **Step 3: Implement container-managed assembly**

Update `bin/App/App.php` in `registerCoreServices()` and the kernel accessors:

```php
private function registerCoreServices(): void
{
    $this->container->instance(self::class, $this);
    $this->container->instance(Container::class, $this->container);

    foreach (self::$coreAliases as $alias => $class) {
        if (!$this->container->bound($alias)) {
            $this->container->singleton($alias, $class);
        }

        if (!$this->container->bound($class)) {
            $this->container->singleton($class, $class);
        }
    }

    $this->providerRepository ??= new ProviderRepository($this);
    $this->container->instance(ProviderRepository::class, $this->providerRepository);

    $this->container->singleton(\Bin\Foundation\HttpKernel::class, fn () => new \Bin\Foundation\HttpKernel($this));
    $this->container->singleton(\Bin\Foundation\ConsoleKernel::class, fn () => new \Bin\Foundation\ConsoleKernel($this));
    $this->container->singleton(\Bin\Routing\ControllerDispatcher::class, \Bin\Routing\ControllerDispatcher::class);
    $this->container->singleton(\Bin\Exception\ExceptionHandler::class, fn () => new \Bin\Exception\ExceptionHandler((bool) env('APP_DEBUG', false)));

    $this->registerDefaultProviders();
}

public function getHttpKernel(): \Bin\Foundation\HttpKernel
{
    /** @var \Bin\Foundation\HttpKernel $kernel */
    $kernel = $this->httpKernel ??= $this->make(\Bin\Foundation\HttpKernel::class);
    return $kernel;
}

public function getConsoleKernel(): \Bin\Foundation\ConsoleKernel
{
    /** @var \Bin\Foundation\ConsoleKernel $kernel */
    $kernel = $this->consoleKernel ??= $this->make(\Bin\Foundation\ConsoleKernel::class);
    return $kernel;
}
```

Update `bin/Providers/ProviderRepository.php`:

```php
protected function createProvider(string $providerClass): ServiceProvider
{
    try {
        /** @var ServiceProvider $provider */
        $provider = $this->app->make($providerClass);
        return $provider;
    } catch (\Throwable) {
        return new $providerClass($this->app);
    }
}
```

Update `bin/Route/RouteAction.php`:

```php
public static function getDispatcher(): ControllerDispatcher
{
    if (static::$dispatcher === null) {
        $app = App::getInstance();

        try {
            static::$dispatcher = $app->make(ControllerDispatcher::class);
        } catch (\Throwable) {
            static::$dispatcher = new ControllerDispatcher();
        }
    }

    return static::$dispatcher;
}
```

- [ ] **Step 4: Run the assembly tests again**

Run:

```bash
php test tests/AppTest.php
php test tests/DispatcherIntegrationTest.php
```

Expected: both files PASS with `Failed: 0`.

- [ ] **Step 5: Commit the container-assembly change**

```bash
git add tests/AppTest.php \
  tests/DispatcherIntegrationTest.php \
  bin/App/App.php \
  bin/Providers/ProviderRepository.php \
  bin/Route/RouteAction.php
git commit -m "refactor: route runtime assembly through app container"
```

### Task 3: Collapse HTTP Execution Onto One Captured Request

**Files:**
- Modify: `bin/Foundation/HttpKernel.php`
- Modify: `bin/Route/RouteAction.php`
- Modify: `bin/Routing/ControllerDispatcher.php`
- Test: `tests/DispatcherIntegrationTest.php`

- [ ] **Step 1: Write the failing request-lifecycle test**

Add this method to `tests/DispatcherIntegrationTest.php`:

```php
public function testRouteActionDispatchUsesProvidedRequestInstance(): void
{
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/users/42';

    \Bin\Route\RouteCollection::get('/users/{id}', fn (string $id): string => "id={$id}");

    $request = \Bin\Request\Request::capture();
    \Bin\App\App::getInstance()->instance(\Bin\Request\Request::class, $request);

    $response = RouteAction::dispatch($request);

    $this->assertInstanceOf(Response::class, $response);
    $this->assertEquals('id=42', $response->getContent());
    $this->assertSame($request, \Bin\App\App::getInstance()->make(\Bin\Request\Request::class));
}
```

- [ ] **Step 2: Run the dispatcher integration tests and verify they fail**

Run: `php test tests/DispatcherIntegrationTest.php`

Expected: FAIL with:

```text
Call to undefined method Bin\Route\RouteAction::dispatch()
```

- [ ] **Step 3: Implement one explicit request path**

Update `bin/Route/RouteAction.php`:

```php
public static function dispatch(\Bin\Request\Request $request): mixed
{
    App::getInstance()->instance(\Bin\Request\Request::class, $request);

    $route = RouteCollection::getRoute();

    $stack = MiddlewareStack::getInstance();
    $middleware = $stack->collectRouteMiddleware(
        $route->getMiddleware(),
        $route->getMiddlewareGroups(),
        $route->getExcludedMiddleware()
    );

    if ($middleware === [] && $route->getMiddle() !== null) {
        $middleware = static::legacyMiddleware($route);
    }

    $aliases = $stack->getAliases();
    $resolved = MiddlewareNameResolver::resolveAll($middleware, $aliases);

    $pipeline = new Pipeline();
    $pipeline->send($request)
        ->through(static::buildMiddlewareInstances($resolved));

    static::$lastPipeline = $pipeline;

    return $pipeline->then(function () use ($route): mixed {
        return static::dispatchRoute($route);
    });
}

public static function action(): mixed
{
    return static::dispatch(\Bin\Request\Request::capture());
}

private static function dispatchRoute(Route $route): mixed
{
    $action = $route->getAction();
    $dispatcher = static::getDispatcher();

    return match (true) {
        is_callable($action) => $dispatcher->dispatchClosure($action, $route),
        is_string($action) => static::dispatchController($action, $route, $dispatcher),
        default => abort(404),
    };
}
```

Update `bin/Foundation/HttpKernel.php`:

```php
public function handle(): Response
{
    $this->bootstrap();

    $this->currentRequest = Request::capture();
    $this->app->instance(Request::class, $this->currentRequest);

    $response = RouteAction::dispatch($this->currentRequest);

    $this->currentResponse = $response instanceof Response
        ? $response
        : new Response((string) $response);

    return $this->currentResponse;
}
```

Update `bin/Routing/ControllerDispatcher.php` so it always consults the container request instance that `HttpKernel` stored:

```php
$container = App::getInstance();
if ($container->has(\Bin\Request\Request::class)) {
    $request = $container->make(\Bin\Request\Request::class);
} else {
    $request = Request::capture();
}
```

Keep the rest of `buildParameterMap()` unchanged in this task.

- [ ] **Step 4: Run the dispatcher integration tests again**

Run: `php test tests/DispatcherIntegrationTest.php`

Expected: PASS with `Failed: 0`.

- [ ] **Step 5: Commit the single-request lifecycle change**

```bash
git add tests/DispatcherIntegrationTest.php \
  bin/Foundation/HttpKernel.php \
  bin/Route/RouteAction.php \
  bin/Routing/ControllerDispatcher.php
git commit -m "refactor: unify http execution around one request instance"
```

### Task 4: Split HTTP and Console Exception Rendering

**Files:**
- Modify: `bin/Foundation/Bootstrap/HandleExceptions.php`
- Modify: `bin/Exception/ExceptionHandler.php`
- Modify: `bin/Foundation/ConsoleKernel.php`
- Test: `tests/ExceptionHandlerTest.php`
- Test: `tests/ConsoleArtisanParityTest.php`

- [ ] **Step 1: Write the failing exception tests**

Add this method to `tests/ExceptionHandlerTest.php`:

```php
public function testRenderForConsoleIncludesExceptionMessage(): void
{
    $output = $this->handler->renderForConsole(new \RuntimeException('console boom'));

    $this->assertStringContainsString('console boom', $output);
}
```

Add this method to `tests/ConsoleArtisanParityTest.php`:

```php
public function testConsoleKernelRendersThrowableThroughExceptionHandler(): void
{
    $app = \Bin\App\App::getInstance();
    $kernel = new ConsoleKernel($app);

    $handler = new class(false) extends \Bin\Exception\ExceptionHandler {
        public bool $reported = false;

        public function report(\Throwable $e): void
        {
            $this->reported = true;
        }

        public function renderForConsole(\Throwable $e): string
        {
            return 'console: ' . $e->getMessage() . PHP_EOL;
        }
    };

    $app->instance(\Bin\Exception\ExceptionHandler::class, $handler);

    Kernel::command('boom:test', function () {
        throw new \RuntimeException('boom');
    });

    ob_start();
    $exitCode = $kernel->call('boom:test');
    $output = ob_get_clean();

    $this->assertSame(1, $exitCode);
    $this->assertTrue($handler->reported);
    $this->assertStringContainsString('console: boom', $output);
}
```

- [ ] **Step 2: Run the exception and console test files to verify they fail**

Run:

```bash
php test tests/ExceptionHandlerTest.php
php test tests/ConsoleArtisanParityTest.php
```

Expected:

- `tests/ExceptionHandlerTest.php` FAIL with `Call to undefined method ... renderForConsole()`.
- `tests/ConsoleArtisanParityTest.php` FAIL because thrown exceptions bypass the handler.

- [ ] **Step 3: Implement shared exception handler behavior**

Update `bin/Exception/ExceptionHandler.php`:

```php
public function renderForConsole(\Throwable $e): string
{
    if ($this->debug) {
        return sprintf(
            "%s: %s in %s:%d\n%s\n",
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        );
    }

    return $e->getMessage() . PHP_EOL;
}
```

Update `bin/Foundation/ConsoleKernel.php`:

```php
public function handle(): int
{
    $this->bootstrap();

    ConsoleKernelBase::setConsoleKernel($this);

    try {
        return ConsoleKernelBase::handle();
    } catch (\Throwable $e) {
        return $this->handleThrowable($e);
    }
}

public function call(string $command, array $arguments = []): int
{
    $this->bootstrap();

    try {
        return ConsoleKernelBase::call($command, $arguments);
    } catch (\Throwable $e) {
        return $this->handleThrowable($e);
    }
}

private function handleThrowable(\Throwable $e): int
{
    try {
        $handler = $this->app->make(\Bin\Exception\ExceptionHandler::class);
        $handler->report($e);
        echo $handler->renderForConsole($e);
    } catch (\Throwable) {
        echo $e->getMessage() . PHP_EOL;
    }

    return 1;
}
```

Update `bin/Foundation/Bootstrap/HandleExceptions.php` to resolve one handler instance:

```php
private function resolveHandler(App $app): \Bin\Exception\ExceptionHandler
{
    if ($app->bound(\Bin\Exception\ExceptionHandler::class)) {
        return $app->make(\Bin\Exception\ExceptionHandler::class);
    }

    $handler = new \Bin\Exception\ExceptionHandler((bool) env('APP_DEBUG', false));
    $app->instance(\Bin\Exception\ExceptionHandler::class, $handler);

    return $handler;
}
```

Use it inside the `set_exception_handler()` closure:

```php
$handler = $this->resolveHandler($app);
$handler->report($e);
$response = $handler->render($e);
if ($response !== null) {
    $response->send();
}
```

- [ ] **Step 4: Run the exception and console tests again**

Run:

```bash
php test tests/ExceptionHandlerTest.php
php test tests/ConsoleArtisanParityTest.php
```

Expected: both files PASS with `Failed: 0`.

- [ ] **Step 5: Commit the exception-handling change**

```bash
git add tests/ExceptionHandlerTest.php \
  tests/ConsoleArtisanParityTest.php \
  bin/Foundation/Bootstrap/HandleExceptions.php \
  bin/Exception/ExceptionHandler.php \
  bin/Foundation/ConsoleKernel.php
git commit -m "refactor: unify http and console exception rendering"
```

### Task 5: Introduce ResponseFactory and Replace Ad Hoc Response Wrapping

**Files:**
- Create: `bin/Response/ResponseFactory.php`
- Modify: `bin/Providers/ResponseServiceProvider.php`
- Modify: `bin/Routing/ControllerDispatcher.php`
- Modify: `bin/Foundation/HttpKernel.php`
- Modify: `bin/Exception/ExceptionHandler.php`
- Modify: `bin/Func/helpers/http.php`
- Test: `tests/ResponseTest.php`
- Test: `tests/DispatcherIntegrationTest.php`

- [ ] **Step 1: Write the failing response tests**

Add this method to `tests/ResponseTest.php`:

```php
public function testResponseHelperAddsJsonHeaderForArrays(): void
{
    $response = response(['ok' => true], 201);

    $this->assertEquals(201, $response->getStatusCode());
    $this->assertEquals('application/json', $response->getHeader('Content-Type'));
}
```

Add this method to `tests/DispatcherIntegrationTest.php`:

```php
public function testDispatcherUsesResponseFactoryForArrayResults(): void
{
    $controller = new class {
        public function index(): array
        {
            return ['status' => 'ok'];
        }
    };

    $className = get_class($controller);
    \Bin\Container\Container::getInstance()->instance($className, $controller);

    $route = new \Bin\Route\Route('GET', '/array', $className . '@index');
    $result = $this->dispatcher->dispatch($className, 'index', $route);

    $this->assertInstanceOf(Response::class, $result);
    $this->assertEquals('application/json', $result->getHeader('Content-Type'));
    $this->assertEquals(['status' => 'ok'], json_decode($result->getContent(), true));
}
```

- [ ] **Step 2: Run the response-focused test files and verify they fail**

Run:

```bash
php test tests/ResponseTest.php
php test tests/DispatcherIntegrationTest.php
```

Expected:

- `tests/ResponseTest.php` FAIL because `Content-Type` is currently `null`.
- `tests/DispatcherIntegrationTest.php` FAIL because controller arrays are wrapped without JSON headers.

- [ ] **Step 3: Implement ResponseFactory and wire it into the runtime**

Create `bin/Response/ResponseFactory.php`:

```php
<?php

declare(strict_types=1);

namespace Bin\Response;

class ResponseFactory
{
    public function make(mixed $payload = '', int $status = 200, array $headers = []): Response
    {
        if ($payload instanceof Response) {
            foreach ($headers as $name => $value) {
                $payload->setHeader($name, $value);
            }

            return $payload;
        }

        if (is_array($payload) && !isset($headers['Content-Type'])) {
            $headers['Content-Type'] = 'application/json';
        }

        return new Response($payload, $status, $headers);
    }

    public function json(array $payload, int $status = 200, array $headers = []): Response
    {
        return $this->make($payload, $status, ['Content-Type' => 'application/json'] + $headers);
    }

    public function redirect(string $url, int $status = 302, array $headers = []): Response
    {
        return $this->make('', $status, ['Location' => $url] + $headers);
    }
}
```

Update `bin/Providers/ResponseServiceProvider.php`:

```php
public function register(): void
{
    $this->singleton(\Bin\Response\ResponseFactory::class, \Bin\Response\ResponseFactory::class);
    $this->alias(\Bin\Response\ResponseFactory::class, 'response.factory');

    $this->bind('response', Response::class);
    $this->alias(Response::class, 'response');
}

public function provides(): array
{
    return ['response', Response::class, 'response.factory', \Bin\Response\ResponseFactory::class];
}
```

Update `bin/Routing/ControllerDispatcher.php`:

```php
private function responseFactory(): \Bin\Response\ResponseFactory
{
    return App::getInstance()->make(\Bin\Response\ResponseFactory::class);
}

public function dispatch(string $controller, string $method, Route $route): mixed
{
    $app = App::getInstance();
    $instance = $app->make($controller);
    $parameters = $this->resolveMethodParameters($controller, $method, $route);

    $result = $app->getContainer()->call([$instance, $method], $parameters);

    return $this->responseFactory()->make($result);
}

public function dispatchClosure(callable $closure, Route $route): mixed
{
    $app = App::getInstance();
    $parameters = $this->resolveClosureParameters($closure, $route);

    $result = $app->getContainer()->call($closure, $parameters);

    return $this->responseFactory()->make($result);
}
```

Update `bin/Foundation/HttpKernel.php`:

```php
$factory = $this->app->make(\Bin\Response\ResponseFactory::class);
$this->currentResponse = $factory->make($response);
```

Update `bin/Exception/ExceptionHandler.php` in `renderJson()` and `renderValidationRedirect()`:

```php
$factory = \Bin\App\App::getInstance()->make(\Bin\Response\ResponseFactory::class);
return $factory->json($data, $status);
```

```php
$factory = \Bin\App\App::getInstance()->make(\Bin\Response\ResponseFactory::class);
return $factory->redirect($referer, 302);
```

Update `bin/Func/helpers/http.php`:

```php
function response(mixed $data = '', int $status = 200): \Bin\Response\Response
{
    $app = \Bin\App\App::getInstance();

    if ($app->bound(\Bin\Response\ResponseFactory::class)) {
        return $app->make(\Bin\Response\ResponseFactory::class)->make($data, $status);
    }

    return new \Bin\Response\Response($data, $status);
}

function redirect(string $url, int $status = 302): \Bin\Response\Response
{
    $app = \Bin\App\App::getInstance();

    if ($app->bound(\Bin\Response\ResponseFactory::class)) {
        return $app->make(\Bin\Response\ResponseFactory::class)->redirect($url, $status);
    }

    return new \Bin\Response\Response('', $status, ['Location' => $url]);
}
```

- [ ] **Step 4: Run the response-focused test files again**

Run:

```bash
php test tests/ResponseTest.php
php test tests/DispatcherIntegrationTest.php
```

Expected: both files PASS with `Failed: 0`.

- [ ] **Step 5: Commit the response-normalization change**

```bash
git add tests/ResponseTest.php \
  tests/DispatcherIntegrationTest.php \
  bin/Response/ResponseFactory.php \
  bin/Providers/ResponseServiceProvider.php \
  bin/Routing/ControllerDispatcher.php \
  bin/Foundation/HttpKernel.php \
  bin/Exception/ExceptionHandler.php \
  bin/Func/helpers/http.php
git commit -m "feat: add response factory for runtime normalization"
```

### Task 6: Harden Route URL Generation and Binding Failures

**Files:**
- Create: `bin/Exception/UrlGenerationException.php`
- Modify: `bin/Route/Route.php`
- Modify: `bin/Route/RouteBinding.php`
- Test: `tests/RouteEnhancementTest.php`

- [ ] **Step 1: Write the failing route tests**

Add these methods to `tests/RouteEnhancementTest.php`:

```php
public function testNamedRouteUrlThrowsWhenRequiredParameterMissing(): void
{
    RouteCollection::get('/posts/{post}', fn () => 'ok')->name('posts.show');

    $this->assertThrows(\Bin\Exception\UrlGenerationException::class, function () {
        RouteCollection::url('posts.show');
    });
}

public function testNamedRouteUrlOmitsOptionalParameterWhenMissing(): void
{
    RouteCollection::get('/reports/{year}/{month?}', fn () => 'ok')->name('reports.show');

    $this->assertEquals('/reports/2026', RouteCollection::url('reports.show', ['year' => 2026]));
}

public function testModelBindingMissingRecordThrowsNotFoundHttpException(): void
{
    RouteBinding::model('user', MissingBoundModel::class);

    $this->assertThrows(\Bin\Exception\NotFoundHttpException::class, function () {
        RouteBinding::resolve('user', '404');
    });
}
```

Append this helper class to the bottom of `tests/RouteEnhancementTest.php`:

```php
class MissingBoundModel
{
    public static function find(string $id): ?self
    {
        return null;
    }
}
```

- [ ] **Step 2: Run the route enhancement tests and verify they fail**

Run: `php test tests/RouteEnhancementTest.php`

Expected:

- FAIL because no `UrlGenerationException` exists.
- FAIL because `Route::url()` leaves `{post}` unreplaced.
- FAIL because `RouteBinding::resolve()` currently throws `RuntimeException` instead of `NotFoundHttpException`.

- [ ] **Step 3: Implement required-parameter and binding failure handling**

Create `bin/Exception/UrlGenerationException.php`:

```php
<?php

declare(strict_types=1);

namespace Bin\Exception;

class UrlGenerationException extends \InvalidArgumentException
{
    public static function forMissingParameters(string $routePath, array $missing): self
    {
        return new self(
            sprintf(
                'Missing required parameters for route [%s]: %s',
                $routePath,
                implode(', ', $missing)
            )
        );
    }
}
```

Update `bin/Route/Route.php`:

```php
public function url(array $params = []): string
{
    $url = $this->getPath();
    preg_match_all('/\{([^}]+)\}/', $url, $matches);

    $missing = [];

    foreach ($matches[1] as $raw) {
        $optional = str_ends_with($raw, '?');
        $name = rtrim($raw, '?');

        if (array_key_exists($name, $params)) {
            $url = str_replace('{' . $raw . '}', (string) $params[$name], $url);
            continue;
        }

        if ($optional) {
            $url = str_replace('/{' . $raw . '}', '', $url);
            $url = str_replace('{' . $raw . '}', '', $url);
            continue;
        }

        $missing[] = $name;
    }

    if ($missing !== []) {
        throw \Bin\Exception\UrlGenerationException::forMissingParameters($this->getPath(), $missing);
    }

    return '/' . trim((string) preg_replace('#//+#', '/', $url), '/');
}
```

Update `bin/Route/RouteBinding.php`:

```php
protected static function resolveFromClass(string $class, mixed $value): mixed
{
    try {
        if (method_exists($class, 'findOrFail')) {
            return $class::findOrFail($value);
        }

        if (method_exists($class, 'find')) {
            $result = $class::find($value);

            if ($result === null) {
                throw new \Bin\Exception\NotFoundHttpException("{$class} with ID {$value} not found");
            }

            return $result;
        }
    } catch (\Bin\Exception\NotFoundHttpException $e) {
        throw $e;
    } catch (\Throwable $e) {
        throw new \Bin\Exception\NotFoundHttpException($e->getMessage(), $e);
    }

    return new $class();
}
```

- [ ] **Step 4: Run the route enhancement tests again**

Run: `php test tests/RouteEnhancementTest.php`

Expected: PASS with `Failed: 0`.

- [ ] **Step 5: Commit the route-infrastructure hardening change**

```bash
git add tests/RouteEnhancementTest.php \
  bin/Exception/UrlGenerationException.php \
  bin/Route/Route.php \
  bin/Route/RouteBinding.php
git commit -m "feat: harden route url generation and binding failures"
```

### Task 7: Run the M1 Skeleton Regression Sweep

**Files:**
- Test: `tests/ApplicationLifecycleTest.php`
- Test: `tests/AppTest.php`
- Test: `tests/DispatcherIntegrationTest.php`
- Test: `tests/ExceptionHandlerTest.php`
- Test: `tests/ResponseTest.php`
- Test: `tests/ConsoleArtisanParityTest.php`
- Test: `tests/RouteEnhancementTest.php`

- [ ] **Step 1: Run the lifecycle and container regression set**

Run:

```bash
php test tests/ApplicationLifecycleTest.php
php test tests/AppTest.php
php test tests/DispatcherIntegrationTest.php
```

Expected: all three files PASS with `Failed: 0`.

- [ ] **Step 2: Run the output and console regression set**

Run:

```bash
php test tests/ExceptionHandlerTest.php
php test tests/ResponseTest.php
php test tests/ConsoleArtisanParityTest.php
```

Expected: all three files PASS with `Failed: 0`.

- [ ] **Step 3: Run the route regression set**

Run:

```bash
php test tests/RouteEnhancementTest.php
```

Expected: PASS with `Failed: 0`.

- [ ] **Step 4: Run the full suite once before handing off**

Run: `php test`

Expected: global test summary with exit code `0`.

- [ ] **Step 5: Commit the verified M1 milestone**

```bash
git add .
git commit -m "feat: complete m1 runtime skeleton"
```

## Spec Coverage Check

- Bootstrap lifecycle: Task 1
- Container assembly: Task 2
- HTTP main path: Task 3
- Exception unification: Task 4
- Response normalization: Task 5
- Route binding / URL generation: Task 6
- Regression evidence for the M1 acceptance bar: Task 7

## Placeholder Scan

- No unresolved placeholders remain anywhere in the task list.
- Every code-changing step includes concrete code to add or change.
- Every verification step includes an exact command and expected result.

## Type Consistency Check

- New bootstrapper names are consistent: `LoadMiddlewareConfiguration`, `LoadRoutes`.
- New response type is consistent: `ResponseFactory`.
- New route exception type is consistent: `UrlGenerationException`.
- New console rendering method is consistent: `ExceptionHandler::renderForConsole()`.
