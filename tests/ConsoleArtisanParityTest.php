<?php

declare(strict_types=1);

namespace Tests;

use Bin\App\App;
use Bin\Console\ClosureCommand;
use Bin\Console\Commands\RouteCacheCommand;
use Bin\Console\Commands\RouteClearCommand;
use Bin\Console\Commands\RouteListCommand;
use Bin\Console\Input;
use Bin\Console\Kernel;
use Bin\Console\Output;
use Bin\Foundation\ConsoleKernel;
use Bin\Route\RouteCache;
use Bin\Route\RouteCollection as Route;
use Bin\Testing\TestCase;

/**
 * Console Artisan 对齐测试 — 验证 Console 运行时行为与 Laravel 约定的一致性
 */
class ConsoleArtisanParityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Kernel::clear();
        Route::clear();
    }

    protected function tearDown(): void
    {
        Kernel::clear();
        Route::clear();
        parent::tearDown();
    }

    // =========================================================================
    // 签名解析增强测试
    // =========================================================================

    public function testSignatureParsesOptionWithDefault(): void
    {
        $command = new class extends \Bin\Console\Command {
            public string $signature = 'test {--format=json}';
            public function execute(): int { return 0; }
        };
        $command->parseSignature();

        $options = $command->getOptions();
        $this->assertArrayHasKey('format', $options);
        $this->assertEquals('json', $options['format']['default']);
        $this->assertEquals('string', $options['format']['type']);
    }

    public function testSignatureParsesOptionWithDescription(): void
    {
        $command = new class extends \Bin\Console\Command {
            public string $signature = 'test {--verbose : Run with verbose output}';
            public function execute(): int { return 0; }
        };
        $command->parseSignature();

        $options = $command->getOptions();
        $this->assertArrayHasKey('verbose', $options);
        $this->assertEquals('Run with verbose output', $options['verbose']['description']);
    }

    public function testSignatureParsesArgumentWithDescription(): void
    {
        $command = new class extends \Bin\Console\Command {
            public string $signature = 'test {name : The name of the resource}';
            public function execute(): int { return 0; }
        };
        $command->parseSignature();

        $arguments = $command->getArguments();
        $this->assertArrayHasKey('name', $arguments);
        $this->assertEquals('The name of the resource', $arguments['name']['description']);
        $this->assertTrue($arguments['name']['required']);
    }

    public function testSignatureParsesOptionalArgumentWithDefault(): void
    {
        $command = new class extends \Bin\Console\Command {
            public string $signature = 'test {name=world}';
            public function execute(): int { return 0; }
        };
        $command->parseSignature();

        $arguments = $command->getArguments();
        $this->assertArrayHasKey('name', $arguments);
        $this->assertEquals('world', $arguments['name']['default']);
        $this->assertFalse($arguments['name']['required']);
    }

    public function testSignatureParsesShortOption(): void
    {
        $command = new class extends \Bin\Console\Command {
            public string $signature = 'test {--V|verbose}';
            public function execute(): int { return 0; }
        };
        $command->parseSignature();

        $options = $command->getOptions();
        $this->assertArrayHasKey('V', $options);
        $this->assertArrayHasKey('verbose', $options);
    }

    // =========================================================================
    // ClosureCommand 测试
    // =========================================================================

    public function testClosureCommandRegistration(): void
    {
        Kernel::command('greet {name}', function (string $name) {
            // test
        }, 'Greet someone');

        $this->assertTrue(Kernel::hasCommand('greet'));

        $cmd = Kernel::getCommand('greet');
        $this->assertEquals('greet', $cmd->getName());
        $this->assertEquals('Greet someone', $cmd->getDescription());
    }

    public function testClosureCommandExecution(): void
    {
        $executed = false;
        $capturedName = '';

        Kernel::command('greet {name}', function (string $name) use (&$executed, &$capturedName) {
            $executed = true;
            $capturedName = $name;
            return 0;
        });

        $exitCode = Kernel::callSilent('greet', ['World']);
        $this->assertEquals(0, $exitCode);
        $this->assertTrue($executed);
        $this->assertEquals('World', $capturedName);
    }

    public function testClosureCommandWithOptions(): void
    {
        $capturedName = '';

        Kernel::command('export {name} {--format=json}', function (string $name) use (&$capturedName) {
            $capturedName = $name;
        });

        $exitCode = Kernel::callSilent('export', ['data', '--format' => 'csv']);
        $this->assertEquals(0, $exitCode);
        $this->assertEquals('data', $capturedName);
    }

    public function testClosureCommandReturnsExitCode(): void
    {
        Kernel::command('fail-test', function () {
            return 1;
        });

        $exitCode = Kernel::callSilent('fail-test');
        $this->assertEquals(1, $exitCode);
    }

    public function testClosureCommandNullReturnIsSuccess(): void
    {
        Kernel::command('null-test', function () {
            // no return
        });

        $exitCode = Kernel::callSilent('null-test');
        $this->assertEquals(0, $exitCode);
    }

    // =========================================================================
    // 命令注册与别名测试
    // =========================================================================

    public function testRegisterClassNameIsLazy(): void
    {
        // 注册一个不会立即实例化的命令类名
        Kernel::register('lazy:test', LazyTestCommand::class);

        // 应该存在但尚未实例化（通过工厂）
        $this->assertTrue(Kernel::hasCommand('lazy:test'));

        // 获取命令时才实例化
        $cmd = Kernel::getCommand('lazy:test');
        $this->assertInstanceOf(LazyTestCommand::class, $cmd);
        $this->assertEquals('lazy:test', $cmd->getName());
    }

    public function testAliasResolvesCorrectly(): void
    {
        Kernel::register('make:controller', function () {
            return new class extends \Bin\Console\Command {
                public string $name = 'make:controller';
                public function execute(): int { return 0; }
            };
        });

        Kernel::alias('mc', 'make:controller');

        $this->assertTrue(Kernel::hasCommand('mc'));
        $cmd = Kernel::getCommand('mc');
        $this->assertEquals('make:controller', $cmd->getName());
    }

    // =========================================================================
    // 程序化调用测试
    // =========================================================================

    public function testCallWithNamedArguments(): void
    {
        $capturedName = '';

        Kernel::command('test:call {name}', function (string $name) use (&$capturedName) {
            $capturedName = $name;
            return 0;
        });

        $exitCode = Kernel::callSilent('test:call', ['John']);
        $this->assertEquals(0, $exitCode);
        $this->assertEquals('John', $capturedName);
    }

    public function testCallSilentCapturesOutput(): void
    {
        Kernel::command('test:output', function () {
            echo "should not leak";
            return 0;
        });

        ob_start();
        $exitCode = Kernel::callSilent('test:output');
        $leaked = ob_get_clean();

        $this->assertEquals(0, $exitCode);
        $this->assertEquals('', $leaked);
    }

    public function testCallUnknownCommandReturnsError(): void
    {
        $exitCode = Kernel::callSilent('nonexistent:command');
        $this->assertNotEquals(0, $exitCode);
    }

    // =========================================================================
    // Command::call() 代理测试
    // =========================================================================

    public function testCommandCallProxiesToKernel(): void
    {
        Kernel::command('inner:cmd', function () { return 0; });
        Kernel::command('outer:cmd', function () {
            return 0;
        });

        $exitCode = Kernel::callSilent('outer:cmd');
        $this->assertEquals(0, $exitCode);
    }

    // =========================================================================
    // 签名解析兼容性测试（确保旧签名仍工作）
    // =========================================================================

    public function testLegacySignatureStillWorks(): void
    {
        $command = new class extends \Bin\Console\Command {
            public string $signature = 'test {arg1} {arg2?} {--option} {--flag}';
            public function execute(): int { return 0; }
        };
        $command->parseSignature();

        $this->assertEquals('test', $command->getName());

        $arguments = $command->getArguments();
        $this->assertArrayHasKey('arg1', $arguments);
        $this->assertArrayHasKey('arg2', $arguments);
        $this->assertTrue($arguments['arg1']['required']);
        $this->assertFalse($arguments['arg2']['required']);

        $options = $command->getOptions();
        $this->assertArrayHasKey('option', $options);
        $this->assertArrayHasKey('flag', $options);
    }

    // =========================================================================
    // ConsoleKernel 集成测试
    // =========================================================================

    public function testConsoleKernelCallBootstraps(): void
    {
        $app = \Bin\App\App::getInstance();
        $kernel = new ConsoleKernel($app);

        Kernel::command('ck:test', function () { return 0; });

        // call 应该经过 bootstrapping
        $exitCode = $kernel->call('ck:test');
        $this->assertEquals(0, $exitCode);
    }

    public function testConsoleKernelGetApp(): void
    {
        $app = \Bin\App\App::getInstance();
        $kernel = new ConsoleKernel($app);

        $this->assertSame($app, $kernel->getApp());
    }

    public function testConsoleKernelBootstrapperManagement(): void
    {
        $app = \Bin\App\App::getInstance();
        $kernel = new ConsoleKernel($app);

        $original = $kernel->getBootstrappers();
        $this->assertNotEmpty($original);

        $kernel->appendBootstrapper(\stdClass::class);
        $modified = $kernel->getBootstrappers();
        $this->assertCount(count($original) + 1, $modified);
        $this->assertEquals(\stdClass::class, end($modified));

        $kernel->prependBootstrapper(\stdClass::class);
        $modified = $kernel->getBootstrappers();
        $this->assertEquals(\stdClass::class, reset($modified));
    }

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

    public function testConsoleKernelCallHandlesBootstrapFailuresThroughExceptionHandler(): void
    {
        $app = \Bin\App\App::getInstance();
        $kernel = new ConsoleKernel($app);
        $kernel->setBootstrappers([ConsoleKernelThrowingBootstrapper::class]);

        $handler = new class(false) extends \Bin\Exception\ExceptionHandler {
            public bool $reported = false;

            public function report(\Throwable $e): void
            {
                $this->reported = true;
            }

            public function renderForConsole(\Throwable $e): string
            {
                return 'console bootstrap: ' . $e->getMessage() . PHP_EOL;
            }
        };

        $app->instance(\Bin\Exception\ExceptionHandler::class, $handler);

        ob_start();
        $exitCode = $kernel->call('ignored:test');
        $output = ob_get_clean();

        $this->assertSame(1, $exitCode);
        $this->assertTrue($handler->reported);
        $this->assertStringContainsString('console bootstrap: bootstrap boom', $output);
    }

    public function testConsoleKernelHandleHandlesBootstrapFailuresThroughExceptionHandler(): void
    {
        $app = \Bin\App\App::getInstance();
        $kernel = new ConsoleKernel($app);
        $kernel->setBootstrappers([ConsoleKernelThrowingBootstrapper::class]);

        $handler = new class(false) extends \Bin\Exception\ExceptionHandler {
            public bool $reported = false;

            public function report(\Throwable $e): void
            {
                $this->reported = true;
            }

            public function renderForConsole(\Throwable $e): string
            {
                return 'console handle: ' . $e->getMessage() . PHP_EOL;
            }
        };

        $app->instance(\Bin\Exception\ExceptionHandler::class, $handler);

        $originalArgv = $_SERVER['argv'] ?? null;
        $_SERVER['argv'] = ['command', 'boom:test'];
        $GLOBALS['argv'] = $_SERVER['argv'];

        try {
            ob_start();
            $exitCode = $kernel->handle();
            $output = ob_get_clean();
        } finally {
            if ($originalArgv === null) {
                unset($_SERVER['argv'], $GLOBALS['argv']);
            } else {
                $_SERVER['argv'] = $originalArgv;
                $GLOBALS['argv'] = $originalArgv;
            }
        }

        $this->assertSame(1, $exitCode);
        $this->assertTrue($handler->reported);
        $this->assertStringContainsString('console handle: bootstrap boom', $output);
    }

    // =========================================================================
    // Kernel::command() 完整签名测试
    // =========================================================================

    public function testCommandSignatureWithAllFeatures(): void
    {
        Kernel::command(
            'complex {name : Resource name} {--force : Overwrite existing} {--type=standard : Resource type}',
            function (string $name) {
                return 0;
            },
            'Complex command test'
        );

        $this->assertTrue(Kernel::hasCommand('complex'));

        $cmd = Kernel::getCommand('complex');
        $this->assertEquals('Complex command test', $cmd->getDescription());

        $args = $cmd->getArguments();
        $this->assertEquals('Resource name', $args['name']['description']);

        $opts = $cmd->getOptions();
        $this->assertEquals('Overwrite existing', $opts['force']['description']);
        $this->assertEquals('standard', $opts['type']['default']);
    }

    public function testRouteListCommandIsDiscovered(): void
    {
        Kernel::discover();

        $this->assertTrue(Kernel::hasCommand('route:list'));
        $this->assertInstanceOf(RouteListCommand::class, Kernel::getCommand('route:list'));
    }

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
}

/**
 * 延迟实例化测试命令
 */
class LazyTestCommand extends \Bin\Console\Command
{
    public string $name = 'lazy:test';
    public string $description = 'Lazy loaded command';
    public function execute(): int { return 0; }
}

class ConsoleKernelThrowingBootstrapper implements \Bin\Foundation\Contracts\Bootstrapper
{
    public function bootstrap(\Bin\App\App $app): void
    {
        throw new \RuntimeException('bootstrap boom');
    }
}
