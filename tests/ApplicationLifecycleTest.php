<?php

declare(strict_types=1);

namespace Tests;

use Bin\App\App;
use Bin\Foundation\ApplicationConfiguration;
use Bin\Foundation\ConsoleKernel;
use Bin\Foundation\HttpKernel;
use Bin\Foundation\Bootstrap\LoadEnvironmentVariables;
use Bin\Foundation\Bootstrap\LoadConfiguration;
use Bin\Foundation\Bootstrap\HandleExceptions;
use Bin\Foundation\Bootstrap\LoadMiddlewareConfiguration;
use Bin\Foundation\Bootstrap\LoadRoutes;
use Bin\Foundation\Bootstrap\SetRequestContext;
use Bin\Foundation\Bootstrap\RegisterProviders;
use Bin\Foundation\Bootstrap\BootProviders;
use Bin\Foundation\Contracts\Bootstrapper;
use Bin\Foundation\Configuration\MiddlewareConfigurator;
use Bin\Middleware\AuthMiddleware;
use Bin\Middleware\CsrfMiddleware;
use Bin\Middleware\MiddlewareStack;
use Bin\Middleware\RateLimitMiddleware;
use Bin\Middleware\SessionMiddleware;
use Bin\Response\Response;
use Bin\Route\RouteCollection as Route;
use Bin\Testing\TestCase;

/**
 * 应用生命周期测试
 *
 * 覆盖 HTTP / CLI 两条主路径的引导流程
 */
class ApplicationLifecycleTest extends TestCase
{
    private ?App $previousInstance = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousInstance = App::getInstance();
        App::setInstance(null);
        Route::clear();
        MiddlewareStack::reset();
    }

    protected function tearDown(): void
    {
        Route::clear();
        MiddlewareStack::reset();
        parent::tearDown();
        App::setInstance($this->previousInstance);
    }

    // ─── App 基础 ──────────────────────────────────────

    public function testAppHasBasePath(): void
    {
        $app = App::getInstance();

        $this->assertTrue(strlen($app->basePath()) > 0);
        $this->assertEquals(BASE_PATH, $app->basePath());
    }

    public function testAppPathHelpers(): void
    {
        $app = App::getInstance();

        $this->assertTrue(str_ends_with($app->bootstrapPath(), '/bootstrap'));
        $this->assertTrue(str_ends_with($app->configPath(), '/config'));
        $this->assertTrue(str_ends_with($app->databasePath(), '/database'));
        $this->assertTrue(str_ends_with($app->storagePath(), '/storage'));
        $this->assertTrue(str_ends_with($app->publicPath(), '/public'));
    }

    public function testAppPathHelpersWithSuffix(): void
    {
        $app = App::getInstance();

        $this->assertTrue(str_ends_with($app->configPath('app.php'), '/config/app.php'));
        $this->assertTrue(str_ends_with($app->bootstrapPath('app.php'), '/bootstrap/app.php'));
        $this->assertTrue(str_ends_with($app->storagePath('logs'), '/storage/logs'));
    }

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

    public function testApplicationBuilderNormalizesTrailingSlashBasePathForAppAndConfiguration(): void
    {
        $basePath = $this->createTempBootstrapBasePath();
        $basePathWithTrailingSlash = $basePath . '/';

        $app = App::configure($basePathWithTrailingSlash)->create();
        $configuration = $app->getApplicationConfiguration();

        $this->assertSame($basePath, $app->basePath());
        $this->assertSame($basePath, $configuration->basePath());
    }

    public function testApplicationBuilderPreservesRootBasePathForAppAndConfiguration(): void
    {
        $app = App::configure('/')->create();
        $configuration = $app->getApplicationConfiguration();

        $this->assertSame('/', $app->basePath());
        $this->assertSame('/', $configuration->basePath());
    }

    public function testApplicationBuilderUsesCanonicalRootPathHelpers(): void
    {
        $app = App::configure('/')->create();

        $this->assertSame('/bootstrap', $app->bootstrapPath());
        $this->assertSame('/config/app.php', $app->configPath('app.php'));
        $this->assertSame('/database/migrations', $app->databasePath('migrations'));
        $this->assertSame('/storage/logs', $app->storagePath('logs'));
        $this->assertSame('/public/index.php', $app->publicPath('index.php'));
    }

    public function testApplicationBuilderUsesCanonicalRootProviderPath(): void
    {
        $app = App::configure('/')
            ->withProviders()
            ->create();

        $this->assertSame(['/bootstrap/providers.php'], $app->getApplicationConfiguration()->providerFiles());
    }

    public function testApplicationBuilderPreservesExplicitAbsoluteRootRoutePaths(): void
    {
        $app = App::configure('/')
            ->withRouting(web: '/routes/web.php', api: '/routes/api.php')
            ->create();

        $this->assertSame(
            ['/routes/web.php', '/routes/api.php'],
            $app->getApplicationConfiguration()->routeFiles()
        );
    }

    public function testAppExposesHttpKernel(): void
    {
        $app = App::getInstance();

        $kernel = $app->getHttpKernel();

        $this->assertInstanceOf(HttpKernel::class, $kernel);
        $this->assertSame($app, $kernel->getApp());
        $this->assertSame($kernel, $app->getHttpKernel());
    }

    public function testAppExposesConsoleKernel(): void
    {
        $app = App::getInstance();

        $kernel = $app->getConsoleKernel();

        $this->assertInstanceOf(ConsoleKernel::class, $kernel);
        $this->assertSame($app, $kernel->getApp());
        $this->assertSame($kernel, $app->getConsoleKernel());
    }

    // ─── Bootstrapper 接口 ──────────────────────────────────────

    public function testBootstrapperInterface(): void
    {
        $bootstrapper = new class implements Bootstrapper {
            public bool $called = false;
            public function bootstrap(App $app): void
            {
                $this->called = true;
            }
        };

        $app = App::getInstance();
        $app->bootstrapWith([$bootstrapper::class]);

        // 创建新实例验证
        $instance = new $bootstrapper();
        $this->assertInstanceOf(Bootstrapper::class, $instance);
    }

    // ─── bootstrapWith ──────────────────────────────────────

    public function testBootstrapWithRunsBootstrappers(): void
    {
        $app = App::getInstance();

        $app->bootstrapWith([
            LoadEnvironmentVariables::class,
            LoadConfiguration::class,
        ]);

        $this->assertTrue($app->hasBeenBootstrapped());
        $this->assertTrue($app->hasBeenBootstrappedBy(LoadEnvironmentVariables::class));
        $this->assertTrue($app->hasBeenBootstrappedBy(LoadConfiguration::class));
    }

    public function testBootstrapWithIsIdempotent(): void
    {
        $app = App::getInstance();

        // 第一次引导
        $app->bootstrapWith([LoadEnvironmentVariables::class]);
        $this->assertTrue($app->hasBeenBootstrappedBy(LoadEnvironmentVariables::class));

        // 第二次引导同个 bootstrapper 不应重复执行（幂等）
        $bootstrappedBefore = $app->getBootstrapped();
        $app->bootstrapWith([LoadEnvironmentVariables::class]);
        $bootstrappedAfter = $app->getBootstrapped();

        $this->assertEquals($bootstrappedBefore, $bootstrappedAfter);
    }

    public function testHasBeenBootstrappedFlag(): void
    {
        $app = App::getInstance();

        $this->assertFalse($app->hasBeenBootstrapped());

        $app->bootstrapWith([]);

        $this->assertTrue($app->hasBeenBootstrapped());
    }

    // ─── HTTP Kernel ──────────────────────────────────────

    public function testHttpKernelBootstrappers(): void
    {
        $app = App::getInstance();
        $kernel = new HttpKernel($app);

        $bootstrappers = $kernel->getBootstrappers();

        $this->assertContains(LoadEnvironmentVariables::class, $bootstrappers);
        $this->assertContains(HandleExceptions::class, $bootstrappers);
        $this->assertContains(LoadConfiguration::class, $bootstrappers);
        $this->assertContains(SetRequestContext::class, $bootstrappers);
        $this->assertContains(RegisterProviders::class, $bootstrappers);
        $this->assertContains(BootProviders::class, $bootstrappers);
    }

    public function testHttpKernelIncludesHttpOnlyBootstrappers(): void
    {
        $app = App::getInstance();
        $kernel = new HttpKernel($app);

        $bootstrappers = $kernel->getBootstrappers();

        $this->assertContains(LoadMiddlewareConfiguration::class, $bootstrappers);
        $this->assertContains(LoadRoutes::class, $bootstrappers);
    }

    public function testHttpKernelBootstrapperOrder(): void
    {
        $app = App::getInstance();
        $kernel = new HttpKernel($app);

        $bootstrappers = $kernel->getBootstrappers();

        // 环境变量必须最先加载
        $this->assertEquals(LoadEnvironmentVariables::class, $bootstrappers[0]);

        // 异常处理在环境变量之后
        $envPos = array_search(LoadEnvironmentVariables::class, $bootstrappers, true);
        $exceptPos = array_search(HandleExceptions::class, $bootstrappers, true);
        $this->assertGreaterThan($envPos, $exceptPos);

        // Provider 启动必须在注册之后
        $registerPos = array_search(RegisterProviders::class, $bootstrappers, true);
        $bootPos = array_search(BootProviders::class, $bootstrappers, true);
        $this->assertGreaterThan($registerPos, $bootPos);
    }

    public function testHttpKernelPrependBootstrapper(): void
    {
        $app = App::getInstance();
        $kernel = new HttpKernel($app);

        $customBootstrapper = new class implements Bootstrapper {
            public function bootstrap(App $app): void {}
        };

        $kernel->prependBootstrapper(LoadConfiguration::class, $customBootstrapper::class);
        $bootstrappers = $kernel->getBootstrappers();

        $customPos = array_search($customBootstrapper::class, $bootstrappers, true);
        $configPos = array_search(LoadConfiguration::class, $bootstrappers, true);

        $this->assertTrue($customPos !== false);
        $this->assertLessThan($configPos, $customPos);
    }

    public function testHttpKernelAppendBootstrapper(): void
    {
        $app = App::getInstance();
        $kernel = new HttpKernel($app);

        $customBootstrapper = new class implements Bootstrapper {
            public function bootstrap(App $app): void {}
        };

        $kernel->appendBootstrapper(LoadConfiguration::class, $customBootstrapper::class);
        $bootstrappers = $kernel->getBootstrappers();

        $customPos = array_search($customBootstrapper::class, $bootstrappers, true);
        $configPos = array_search(LoadConfiguration::class, $bootstrappers, true);

        $this->assertTrue($customPos !== false);
        $this->assertGreaterThan($configPos, $customPos);
    }

    public function testHttpKernelSetBootstrappers(): void
    {
        $app = App::getInstance();
        $kernel = new HttpKernel($app);

        $customBootstrapper = new class implements Bootstrapper {
            public function bootstrap(App $app): void {}
        };

        $kernel->setBootstrappers([$customBootstrapper::class]);

        $this->assertEquals([$customBootstrapper::class], $kernel->getBootstrappers());
    }

    // ─── Console Kernel ──────────────────────────────────────

    public function testConsoleKernelBootstrappers(): void
    {
        $app = App::getInstance();
        $kernel = new ConsoleKernel($app);

        $bootstrappers = $kernel->getBootstrappers();

        $this->assertContains(LoadEnvironmentVariables::class, $bootstrappers);
        $this->assertContains(HandleExceptions::class, $bootstrappers);
        $this->assertContains(LoadConfiguration::class, $bootstrappers);
        $this->assertContains(RegisterProviders::class, $bootstrappers);
        $this->assertContains(BootProviders::class, $bootstrappers);
    }

    public function testConsoleKernelHasNoSetRequestContext(): void
    {
        $app = App::getInstance();
        $kernel = new ConsoleKernel($app);

        $bootstrappers = $kernel->getBootstrappers();

        // Console 不需要 SetRequestContext
        $this->assertNotContains(SetRequestContext::class, $bootstrappers);
    }

    public function testConsoleKernelBootstrapperOrder(): void
    {
        $app = App::getInstance();
        $kernel = new ConsoleKernel($app);

        $bootstrappers = $kernel->getBootstrappers();

        // 环境变量最先
        $this->assertEquals(LoadEnvironmentVariables::class, $bootstrappers[0]);

        // Boot 在 Register 之后
        $registerPos = array_search(RegisterProviders::class, $bootstrappers, true);
        $bootPos = array_search(BootProviders::class, $bootstrappers, true);
        $this->assertGreaterThan($registerPos, $bootPos);
    }

    // ─── Bootstrap 流程集成 ──────────────────────────────────────

    public function testBootstrapWithFullPipeline(): void
    {
        $app = App::getInstance();

        $app->bootstrapWith([
            LoadEnvironmentVariables::class,
            HandleExceptions::class,
            LoadConfiguration::class,
            SetRequestContext::class,
            RegisterProviders::class,
            BootProviders::class,
        ]);

        $this->assertTrue($app->hasBeenBootstrapped());
        $this->assertTrue($app->isBooted());
        $this->assertCount(6, $app->getBootstrapped());
    }

    public function testHttpBootstrapRunsHttpOnlyStagesAfterConsoleBootstrap(): void
    {
        $app = App::getInstance();
        $console = new ConsoleKernel($app);
        $http = new HttpKernel($app);
        $tempBasePath = $this->createTempBootstrapBasePath();
        $this->setAppBasePath($app, $tempBasePath);

        $this->assertSame([], Route::getRoutes());
        $this->assertSame([], MiddlewareStack::getInstance()->getAliases());

        $console->bootstrap();
        $this->assertFalse($app->hasBeenBootstrappedBy(SetRequestContext::class));
        $this->assertSame([], Route::getRoutes());
        $this->assertSame([], MiddlewareStack::getInstance()->getAliases());

        $reflection = new \ReflectionMethod(HttpKernel::class, 'bootstrap');
        $reflection->invoke($http);

        $routes = Route::getRoutes();
        $stack = MiddlewareStack::getInstance();

        $this->assertTrue($app->hasBeenBootstrappedBy(SetRequestContext::class));
        $this->assertTrue($app->hasBeenBootstrappedBy(LoadMiddlewareConfiguration::class));
        $this->assertTrue($app->hasBeenBootstrappedBy(LoadRoutes::class));
        $this->assertCount(1, $routes);
        $this->assertSame('/bootstrap/test-route', $routes[0]->getPath());
        $this->assertSame(AuthMiddleware::class, $stack->getAliases()['auth']);
        $this->assertContains(CsrfMiddleware::class, $stack->getGroup('web'));
    }

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

    public function testHttpBootstrapLoadsSessionBeforeCsrfInWebGroup(): void
    {
        $app = App::getInstance();
        $http = new HttpKernel($app);

        $reflection = new \ReflectionMethod(HttpKernel::class, 'bootstrap');
        $reflection->invoke($http);

        $stack = MiddlewareStack::getInstance();

        $this->assertSame(SessionMiddleware::class, $stack->getGroup('web')[0]);
        $this->assertSame(CsrfMiddleware::class, $stack->getGroup('web')[1]);
        $this->assertSame(SessionMiddleware::class, $stack->getAliases()['session']);
    }

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
        'auth' => 1,
    ],
];
PHP);

        $app = App::configure($basePath)
            ->withMiddleware(function (MiddlewareConfigurator $middleware): void {
                $middleware->append(CsrfMiddleware::class);
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
        $this->assertEquals(['auth', 'legacy'], array_slice($stack->collectRouteMiddleware(['legacy', 'auth']), 0, 2));
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

    public function testHttpBootstrapKeepsSessionBeforeAuthForProtectedWebRoutes(): void
    {
        $app = App::getInstance();
        $http = new HttpKernel($app);

        $reflection = new \ReflectionMethod(HttpKernel::class, 'bootstrap');
        $reflection->invoke($http);

        $middleware = MiddlewareStack::getInstance()->collectRouteMiddleware(['auth'], ['web']);

        $sessionIndex = array_search(SessionMiddleware::class, $middleware, true);
        $authIndex = array_search('auth', $middleware, true);

        $this->assertNotSame(false, $sessionIndex);
        $this->assertNotSame(false, $authIndex);
        $this->assertLessThan($authIndex, $sessionIndex);
    }

    public function testBootstrapWithPartialPipeline(): void
    {
        $app = App::getInstance();

        // 只运行环境变量和配置
        $app->bootstrapWith([
            LoadEnvironmentVariables::class,
            LoadConfiguration::class,
        ]);

        $this->assertTrue($app->hasBeenBootstrapped());
        $this->assertFalse($app->hasBeenBootstrappedBy(BootProviders::class));
    }

    // ─── 引导器各自功能 ──────────────────────────────────────

    public function testLoadEnvironmentVariablesBootstrap(): void
    {
        $app = App::getInstance();
        $bootstrapper = new LoadEnvironmentVariables();

        // 不应抛出异常
        $bootstrapper->bootstrap($app);
        $this->assertTrue(true);
    }

    public function testLoadConfigurationBootstrap(): void
    {
        $app = App::getInstance();
        $bootstrapper = new LoadConfiguration();

        $bootstrapper->bootstrap($app);
        $this->assertTrue($app->bound('config'));
    }

    public function testHandleExceptionsBootstrap(): void
    {
        $app = App::getInstance();
        $bootstrapper = new HandleExceptions();

        $bootstrapper->bootstrap($app);
        $this->assertTrue(true);
    }

    public function testHandleExceptionsSendsRenderedResponse(): void
    {
        $app = App::getInstance();
        $handler = new class extends \Bin\Exception\ExceptionHandler {
            public bool $reported = false;

            public function __construct()
            {
                parent::__construct(false);
            }

            public function report(\Throwable $e): void
            {
                $this->reported = true;
            }

            public function render(\Throwable $e): ?Response
            {
                return new Response('handled-response', 500);
            }
        };

        $app->instance(\Bin\Exception\ExceptionHandler::class, $handler);

        $bootstrapper = new HandleExceptions();
        $bootstrapper->bootstrap($app);

        $registered = set_exception_handler(static function (): void {});
        restore_exception_handler();

        $this->assertTrue(is_callable($registered));

        ob_start();
        $registered(new \RuntimeException('boom'));
        $output = ob_get_clean();

        $this->assertTrue($handler->reported);
        $this->assertEquals('handled-response', $output);
    }

    public function testHandleExceptionsResolvesDefaultHandlerWhenContainerBindingMissing(): void
    {
        $app = App::getInstance();
        $app->getContainer()->forget(\Bin\Exception\ExceptionHandler::class);
        $bootstrapper = new HandleExceptions();
        $bootstrapper->bootstrap($app);

        $resolver = \Closure::bind(
            fn (App $app): \Bin\Exception\ExceptionHandler => $this->resolveHandler($app),
            $bootstrapper,
            HandleExceptions::class
        );
        $handler = $resolver($app);
        $handler->dontReport([\RuntimeException::class]);

        $registered = set_exception_handler(static function (): void {});
        restore_exception_handler();

        $this->assertTrue(is_callable($registered));

        ob_start();
        $registered(new \RuntimeException('fallback boom'));
        $output = ob_get_clean();

        $this->assertStringContainsString('Internal Server Error', $output);
    }

    public function testSetRequestContextBootstrap(): void
    {
        $app = App::getInstance();
        $bootstrapper = new SetRequestContext();

        $bootstrapper->bootstrap($app);

        $this->assertEquals('UTF-8', mb_internal_encoding());
    }

    public function testRegisterProvidersBootstrap(): void
    {
        $app = App::getInstance();
        $bootstrapper = new RegisterProviders();

        $bootstrapper->bootstrap($app);

        // 应该已加载 config/app.php 中的 providers
        $this->assertTrue(true);
    }

    public function testRegisterProvidersLoadsBootstrapProvidersAndLegacyProvidersOnce(): void
    {
        LifecycleConfiguredProvider::resetCounts();
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
        \Tests\LifecycleLegacyProvider::class,
    ],
];
PHP);

        $app = App::configure($basePath)
            ->withProviders([\Tests\LifecycleConfiguredProvider::class])
            ->create();

        (new RegisterProviders())->bootstrap($app);
        (new BootProviders())->bootstrap($app);

        $this->assertTrue($app->bound('lifecycle.configured.provider'));
        $this->assertTrue($app->bound('lifecycle.bootstrap.provider'));
        $this->assertTrue($app->bound('lifecycle.legacy.provider'));
        $this->assertEquals(1, LifecycleConfiguredProvider::$registered);
        $this->assertEquals(1, LifecycleConfiguredProvider::$booted);
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

    public function testBootProvidersBootstrap(): void
    {
        $app = App::getInstance();
        $bootstrapper = new BootProviders();

        $this->assertFalse($app->isBooted());

        $bootstrapper->bootstrap($app);

        $this->assertTrue($app->isBooted());
    }

    // ─── 职责分层 ──────────────────────────────────────

    public function testAppDelegatesToContainer(): void
    {
        $app = App::getInstance();

        $app->bind('test.service', \StdClass::class);

        $this->assertTrue($app->bound('test.service'));
        $this->assertInstanceOf(\StdClass::class, $app->make('test.service'));
    }

    public function testAppDelegatesToProviderRepository(): void
    {
        $app = App::getInstance();
        $repo = $app->getProviderRepository();

        $this->assertNotNull($repo);
        $this->assertInstanceOf(\Bin\Providers\ProviderRepository::class, $repo);
    }

    public function testAppManagesLifecycleNotBindingDetails(): void
    {
        $app = App::getInstance();

        // App 提供 bootstrapWith 但不负责具体绑定逻辑
        $this->assertTrue(method_exists($app, 'bootstrapWith'));
        $this->assertTrue(method_exists($app, 'boot'));
        $this->assertTrue(method_exists($app, 'isBooted'));
        $this->assertTrue(method_exists($app, 'hasBeenBootstrapped'));
    }

    private function createTempBootstrapBasePath(): string
    {
        $basePath = sys_get_temp_dir() . '/first-bootstrap-' . bin2hex(random_bytes(6));
        $appPath = $basePath . '/app';
        $configPath = $basePath . '/config';

        mkdir($appPath, 0777, true);
        mkdir($configPath, 0777, true);

        file_put_contents($appPath . '/routes.php', <<<'PHP'
<?php

declare(strict_types=1);

use Bin\Route\RouteCollection as Route;

Route::get('/bootstrap/test-route', static function (): string {
    return 'loaded';
});
PHP);

        file_put_contents($configPath . '/middleware.php', <<<'PHP'
<?php

declare(strict_types=1);

use Bin\Middleware\AuthMiddleware;
use Bin\Middleware\CsrfMiddleware;

return [
    'global' => [],
    'groups' => [
        'web' => [
            CsrfMiddleware::class,
        ],
    ],
    'aliases' => [
        'auth' => AuthMiddleware::class,
    ],
    'priority' => [
        'auth' => 20,
    ],
];
PHP);

        return $basePath;
    }

    private function setAppBasePath(App $app, string $basePath): void
    {
        $reflection = new \ReflectionProperty(App::class, 'basePath');
        $reflection->setValue($app, $basePath);
    }
}

class LifecycleConfiguredProvider extends \Bin\Providers\ServiceProvider
{
    public static int $registered = 0;
    public static int $booted = 0;

    public function register(): void
    {
        self::$registered++;
        $this->app->instance('lifecycle.configured.provider', new \stdClass());
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
