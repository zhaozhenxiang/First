<?php

declare(strict_types=1);

namespace Tests;

use Bin\App\App;
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
use Bin\Response\Response;
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
    }

    protected function tearDown(): void
    {
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

        $console->bootstrap();
        $this->assertFalse($app->hasBeenBootstrappedBy(SetRequestContext::class));

        $reflection = new \ReflectionMethod(HttpKernel::class, 'bootstrap');
        $reflection->invoke($http);

        $this->assertTrue($app->hasBeenBootstrappedBy(SetRequestContext::class));
        $this->assertTrue($app->hasBeenBootstrappedBy(LoadMiddlewareConfiguration::class));
        $this->assertTrue($app->hasBeenBootstrappedBy(LoadRoutes::class));
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

    public function testHandleExceptionsFallbackSendsResponse(): void
    {
        $app = App::getInstance();
        $bootstrapper = new HandleExceptions();
        $bootstrapper->bootstrap($app);

        $registered = set_exception_handler(static function (): void {});
        restore_exception_handler();

        $this->assertTrue(is_callable($registered));

        ob_start();
        $registered(new \RuntimeException('fallback boom'));
        $output = ob_get_clean();

        $this->assertEquals('Internal Server Error', $output);
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
}
