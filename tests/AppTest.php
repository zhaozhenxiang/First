<?php

declare(strict_types=1);

namespace Tests;

use Bin\App\App;
use Bin\Container\Exceptions\BindingResolutionException;
use Bin\Providers\ServiceProvider;
use Bin\Testing\TestCase;

/**
 * 应用程序测试
 */
class AppTest extends TestCase
{
    private ?App $previousInstance = null;
    private App $app;

    protected function setUp(): void
    {
        parent::setUp();

        // 保存之前的实例
        $this->previousInstance = App::getInstance();

        // 创建新的实例用于测试
        App::setInstance(null);
        $this->app = App::getInstance();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // 恢复之前的实例
        App::setInstance($this->previousInstance);
    }

    public function testSingleton(): void
    {
        $instance1 = App::getInstance();
        $instance2 = App::getInstance();

        $this->assertSame($instance1, $instance2);
    }

    public function testMake(): void
    {
        $this->app->bind('test', \StdClass::class);

        $instance = $this->app->make('test');

        $this->assertInstanceOf(\StdClass::class, $instance);
    }

    public function testBind(): void
    {
        $this->app->bind('test', \StdClass::class);

        $this->assertTrue($this->app->bound('test'));
    }

    public function testSingletonService(): void
    {
        $this->app->singleton('test', \StdClass::class);

        $instance1 = $this->app->make('test');
        $instance2 = $this->app->make('test');

        $this->assertSame($instance1, $instance2);
    }

    public function testInstance(): void
    {
        $obj = new \StdClass();
        $obj->name = 'test';

        $this->app->instance('test', $obj);

        $instance = $this->app->make('test');

        $this->assertSame($obj, $instance);
    }

    public function testAlias(): void
    {
        $this->app->bind('concrete', \StdClass::class);
        $this->app->alias('concrete', 'alias');

        $instance = $this->app->make('alias');

        $this->assertInstanceOf(\StdClass::class, $instance);
    }

    public function testContextualBinding(): void
    {
        $this->app->bind(TestDependencyA::class);
        $this->app->bind(TestDependencyB::class);

        $this->app->contextual(
            TestDependencyB::class,
            TestDependencyA::class,
            function () {
                $a = new TestDependencyA();
                $a->value = 'contextual';
                return $a;
            }
        );

        $b = $this->app->make(TestDependencyB::class);

        $this->assertEquals('contextual', $b->getA()->value);
    }

    public function testExtend(): void
    {
        $this->app->bind('test', function () {
            $obj = new \StdClass();
            $obj->name = 'original';
            return $obj;
        });

        $this->app->extend('test', function ($obj, $container) {
            $obj->extended = true;
            return $obj;
        });

        $instance = $this->app->make('test');

        $this->assertTrue($instance->extended);
    }

    public function testBound(): void
    {
        $this->assertFalse($this->app->bound('nonexistent'));

        $this->app->bind('test', \StdClass::class);

        $this->assertTrue($this->app->bound('test'));
    }

    public function testResolved(): void
    {
        $this->app->singleton('test', \StdClass::class);

        $this->assertFalse($this->app->resolved('test'));

        $this->app->make('test');

        $this->assertTrue($this->app->resolved('test'));
    }

    public function testMock(): void
    {
        $mock = $this->app->mock('test');

        $this->assertInstanceOf(\StdClass::class, $mock);
    }

    public function testFlush(): void
    {
        $this->app->bind('test', \StdClass::class);

        $this->assertTrue($this->app->bound('test'));

        $this->app->flush();

        $this->assertFalse($this->app->bound('test'));
    }

    public function testForget(): void
    {
        $this->app->singleton('test', \StdClass::class);
        $this->app->make('test');

        $this->assertTrue($this->app->resolved('test'));

        $this->app->forget('test');

        $this->assertFalse($this->app->resolved('test'));
    }

    public function testGetBindings(): void
    {
        $this->app->bind('test', \StdClass::class);

        $bindings = $this->app->getBindings();

        $this->assertIsType('array', $bindings);
        $this->assertArrayHasKey('test', $bindings);
    }

    public function testHasBinding(): void
    {
        $this->assertFalse($this->app->hasBinding('test'));

        $this->app->bind('test', \StdClass::class);

        $this->assertTrue($this->app->hasBinding('test'));
    }

    public function testGetContainer(): void
    {
        $container = $this->app->getContainer();

        $this->assertInstanceOf(\Bin\Container\Container::class, $container);
    }

    public function testBindArray(): void
    {
        $bindings = [
            'test1' => \StdClass::class,
            'test2' => \ArrayObject::class,
        ];

        $this->app->bindArray($bindings);

        $this->assertTrue($this->app->bound('test1'));
        $this->assertTrue($this->app->bound('test2'));
    }

    public function testSingletonArray(): void
    {
        $bindings = [
            'test1' => \StdClass::class,
            'test2' => \ArrayObject::class,
        ];

        $this->app->singletonArray($bindings);

        // 单例应该是共享的
        $instance1 = $this->app->make('test1');
        $instance2 = $this->app->make('test1');

        $this->assertSame($instance1, $instance2);
    }

    public function testInstanceArray(): void
    {
        $instances = [
            'test1' => new \StdClass(),
            'test2' => new \ArrayObject(),
        ];

        $this->app->instanceArray($instances);

        $this->assertSame($instances['test1'], $this->app->make('test1'));
        $this->assertSame($instances['test2'], $this->app->make('test2'));
    }

    public function testHas(): void
    {
        $this->assertFalse($this->app->has('test'));

        $this->app->bind('test', \StdClass::class);

        $this->assertTrue($this->app->has('test'));
    }

    public function testFactory(): void
    {
        $count = 0;

        $this->app->factory('test', function () use (&$count) {
            $count++;
            return new \StdClass();
        });

        $this->app->make('test');
        $this->app->make('test');

        $this->assertEquals(2, $count);
    }

    public function testBindAndMake(): void
    {
        $instance = $this->app->bindAndMake('test', \StdClass::class);

        $this->assertInstanceOf(\StdClass::class, $instance);
        $this->assertTrue($this->app->bound('test'));
    }

    public function testSingletonAndMake(): void
    {
        $instance1 = $this->app->singletonAndMake('test', \StdClass::class);
        $instance2 = $this->app->make('test');

        $this->assertSame($instance1, $instance2);
    }

    public function testIsResolving(): void
    {
        $this->app->bind(ResolvingTestDependency::class);

        $this->assertFalse($this->app->isResolving(ResolvingTestDependency::class));
    }

    public function testGetBuildStack(): void
    {
        $stack = $this->app->getBuildStack();

        $this->assertIsType('array', $stack);
    }

    public function testResolve(): void
    {
        $this->app->bind('test', \StdClass::class);

        $instance = $this->app->resolve('test');

        $this->assertInstanceOf(\StdClass::class, $instance);
    }

    public function testHasAlias(): void
    {
        $this->app->bind('concrete', \StdClass::class);
        $this->app->alias('concrete', 'alias');

        $this->assertTrue($this->app->hasAlias('alias'));
    }

    public function testGetAlias(): void
    {
        $this->app->bind('concrete', \StdClass::class);
        $this->app->alias('concrete', 'alias');

        $alias = $this->app->getAlias('alias');

        $this->assertEquals('concrete', $alias);
    }

    public function testSetAlias(): void
    {
        $this->app->bind('concrete', \StdClass::class);
        $this->app->setAlias('concrete', 'alias');

        $instance = $this->app->make('alias');

        $this->assertInstanceOf(\StdClass::class, $instance);
    }

    public function testFacade(): void
    {
        // 测试 Request Facade
        $request = $this->app->facade('Request');

        $this->assertInstanceOf(\Bin\Request\Request::class, $request);
    }

    public function testBoot(): void
    {
        $this->assertFalse($this->app->isBooted());

        $this->app->boot();

        $this->assertTrue($this->app->isBooted());
    }

    public function testRegisterProvider(): void
    {
        $this->app->register(TestServiceProvider::class);

        // 对于延迟服务提供者，需要先请求服务才会触发注册
        $this->assertFalse($this->app->bound('test.service'), '延迟服务提供者应该尚未注册');

        // 请求服务触发延迟加载
        $this->app->make('test.service');

        $this->assertTrue($this->app->bound('test.service'), '服务应该已注册');
        $this->assertInstanceOf(\StdClass::class, $this->app->make('test.service'));
    }

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

    public function testCoreAliasAndClassResolveSameSingletonInstance(): void
    {
        $alias = $this->app->make('session');
        $class = $this->app->make(\Bin\Session\SessionManager::class);

        $alias->setLifetime(15);

        $this->assertSame($alias, $class);
        $this->assertSame(15 * 60, $class->getLifetime());
    }

    public function testProviderRepositoryPropagatesProviderConstructionFailures(): void
    {
        try {
            $this->app->register(BrokenContainerAwareProvider::class, true);
            $this->fail('Expected provider construction failure to be propagated');
        } catch (BindingResolutionException $e) {
            $this->assertMatchesRegularExpression('/UnresolvableProviderDependency/', $e->getMessage());
        }
    }

    public function testAppReturnsReboundHttpKernelFromContainer(): void
    {
        $original = $this->app->getHttpKernel();
        $replacement = new \Bin\Foundation\HttpKernel($this->app);

        $this->app->instance(\Bin\Foundation\HttpKernel::class, $replacement);

        $this->assertNotSame($original, $replacement);
        $this->assertSame($replacement, $this->app->getHttpKernel());
    }

    public function testAppReturnsReboundConsoleKernelFromContainer(): void
    {
        $original = $this->app->getConsoleKernel();
        $replacement = new \Bin\Foundation\ConsoleKernel($this->app);

        $this->app->instance(\Bin\Foundation\ConsoleKernel::class, $replacement);

        $this->assertNotSame($original, $replacement);
        $this->assertSame($replacement, $this->app->getConsoleKernel());
    }

    public function testAppReturnsReboundProviderRepositoryFromContainer(): void
    {
        $original = $this->app->getProviderRepository();
        $replacement = new \Bin\Providers\ProviderRepository($this->app);

        $this->app->instance(\Bin\Providers\ProviderRepository::class, $replacement);

        $this->assertNotSame($original, $replacement);
        $this->assertSame($replacement, $this->app->getProviderRepository());
    }

    public function testMagicCall(): void
    {
        $this->app->bind('test', \StdClass::class);

        $instance = $this->app->test();

        $this->assertInstanceOf(\StdClass::class, $instance);
    }

    public function testStaticCall(): void
    {
        App::getInstance()->bind('staticTest', \StdClass::class);

        $instance = App::staticTest();

        $this->assertInstanceOf(\StdClass::class, $instance);
    }
}

/**
 * 测试用的依赖类
 */
class TestDependencyA
{
    public string $value = 'default';
}

class TestDependencyB
{
    private TestDependencyA $a;

    public function __construct(TestDependencyA $a)
    {
        $this->a = $a;
    }

    public function getA(): TestDependencyA
    {
        return $this->a;
    }
}

class ResolvingTestDependency
{
    // 空类，用于测试解析状态
}

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

class UnresolvableProviderDependency
{
    public function __construct(string $name)
    {
    }
}

class BrokenContainerAwareProvider extends ServiceProvider
{
    public function __construct(\Bin\App\App $app, private UnresolvableProviderDependency $dependency)
    {
        parent::__construct($app);
    }

    public function register(): void
    {
    }
}

/**
 * 测试服务提供者
 */
class TestServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->singleton('test.service', \StdClass::class);
    }

    public function boot(): void
    {
        // 启动逻辑
    }

    public function provides(): array
    {
        return ['test.service'];
    }
}
