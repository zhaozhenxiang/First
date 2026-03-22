<?php

declare(strict_types=1);

namespace Tests;

use Bin\App\App;
use Bin\Container\Container;
use Bin\Testing\TestCase;

/**
 * IoC 容器测试
 */
class ContainerTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = new Container();
    }

    public function testBindAndMake(): void
    {
        $this->container->bind('test', \StdClass::class);

        $instance = $this->container->make('test');

        $this->assertInstanceOf(\StdClass::class, $instance);
    }

    public function testSingleton(): void
    {
        $this->container->singleton('test', \StdClass::class);

        $instance1 = $this->container->make('test');
        $instance2 = $this->container->make('test');

        $this->assertSame($instance1, $instance2, '单例应返回相同实例');
    }

    public function testInstance(): void
    {
        $obj = new \StdClass();
        $obj->name = 'test';

        $this->container->instance('test', $obj);

        $instance = $this->container->make('test');

        $this->assertSame($obj, $instance);
        $this->assertEquals('test', $instance->name);
    }

    public function testAlias(): void
    {
        $this->container->bind('concrete', \StdClass::class);
        $this->container->alias('concrete', 'alias');

        $instance = $this->container->make('alias');

        $this->assertInstanceOf(\StdClass::class, $instance);
    }

    public function testDependencyInjection(): void
    {
        // 测试构造函数依赖注入
        $this->container->bind(DependencyA::class);
        $this->container->bind(DependencyB::class);

        $instance = $this->container->make(DependencyB::class);

        $this->assertInstanceOf(DependencyB::class, $instance);
        $this->assertInstanceOf(DependencyA::class, $instance->getA());
    }

    public function testClosureBinding(): void
    {
        $this->container->bind('test', function () {
            return new \StdClass();
        });

        $instance = $this->container->make('test');

        $this->assertInstanceOf(\StdClass::class, $instance);
    }

    public function testBound(): void
    {
        $this->assertFalse($this->container->bound('test'));

        $this->container->bind('test', \StdClass::class);

        $this->assertTrue($this->container->bound('test'));
    }

    public function testResolved(): void
    {
        $this->container->singleton('test', \StdClass::class);

        $this->assertFalse($this->container->resolved('test'));

        $this->container->make('test');

        $this->assertTrue($this->container->resolved('test'));
    }

    public function testExtend(): void
    {
        $this->container->bind('test', function () {
            $obj = new \StdClass();
            $obj->name = 'original';
            return $obj;
        });

        $this->container->extend('test', function ($obj, $container) {
            $obj->extended = true;
            return $obj;
        });

        $instance = $this->container->make('test');

        $this->assertTrue($instance->extended);
    }

    public function testContextualBinding(): void
    {
        $this->container->bind(DependencyA::class);
        $this->container->bind(ContextualDependency::class);
        $this->container->bind(AnotherDependency::class);

        // 上下文绑定：当 DependencyB 需要 DependencyA 时，使用特殊实例
        $this->container->contextual(
            DependencyB::class,
            DependencyA::class,
            function () {
                $a = new DependencyA();
                $a->value = 'contextual';
                return $a;
            }
        );

        $b = $this->container->make(DependencyB::class);
        $another = $this->container->make(AnotherDependency::class);

        $this->assertEquals('contextual', $b->getA()->value);
        $this->assertEquals('default', $another->getA()->value);
    }

    public function testFlush(): void
    {
        $this->container->bind('test', \StdClass::class);
        $this->container->singleton('singleton', \StdClass::class);

        $this->assertTrue($this->container->bound('test'));
        $this->assertTrue($this->container->bound('singleton'));

        $this->container->flush();

        $this->assertFalse($this->container->bound('test'));
        $this->assertFalse($this->container->bound('singleton'));
    }

    public function testForget(): void
    {
        $this->container->singleton('test', \StdClass::class);
        $this->container->make('test');

        $this->assertTrue($this->container->resolved('test'));

        $this->container->forget('test');

        $this->assertFalse($this->container->resolved('test'));
    }

    public function testCall(): void
    {
        $result = $this->container->call(function ($a, $b) {
            return $a + $b;
        }, ['a' => 3, 'b' => 5]);

        $this->assertEquals(8, $result);
    }

    public function testCallWithDependencyInjection(): void
    {
        $this->container->bind(DependencyA::class);

        $result = $this->container->call(function (DependencyA $a, $multiplier) {
            return $a->value . $multiplier;
        }, ['multiplier' => 'X']);

        $this->assertEquals('defaultX', $result);
    }

    public function testHas(): void
    {
        $this->assertFalse($this->container->has('test'));

        $this->container->bind('test', \StdClass::class);

        $this->assertTrue($this->container->has('test'));
    }

    public function testFactory(): void
    {
        $count = 0;

        $this->container->factory('test', function () use (&$count) {
            $count++;
            return new \StdClass();
        });

        $this->container->make('test');
        $this->container->make('test');

        $this->assertEquals(2, $count, '工厂应该每次都创建新实例');
    }

    public function testBindArray(): void
    {
        $bindings = [
            'test1' => \StdClass::class,
            'test2' => \ArrayObject::class,
        ];

        $this->container->bindArray($bindings);

        $this->assertTrue($this->container->bound('test1'));
        $this->assertTrue($this->container->bound('test2'));
    }

    public function testSingletonArray(): void
    {
        $bindings = [
            'test1' => \StdClass::class,
            'test2' => \ArrayObject::class,
        ];

        $this->container->singletonArray($bindings);

        // 验证单例 - 多次调用返回相同实例
        $instance1a = $this->container->make('test1');
        $instance1b = $this->container->make('test1');
        $this->assertSame($instance1a, $instance1b);

        $instance2a = $this->container->make('test2');
        $instance2b = $this->container->make('test2');
        $this->assertSame($instance2a, $instance2b);
    }

    public function testInstanceArray(): void
    {
        $instances = [
            'test1' => new \StdClass(),
            'test2' => new \ArrayObject(),
        ];

        $this->container->instanceArray($instances);

        $this->assertSame($instances['test1'], $this->container->make('test1'));
        $this->assertSame($instances['test2'], $this->container->make('test2'));
    }

    public function testMock(): void
    {
        $mock = $this->container->mock('test');

        $this->assertInstanceOf(\StdClass::class, $mock);
        $this->assertTrue($this->container->hasInstance('test'));
    }

    public function testIsBuildStack(): void
    {
        $this->container->bind(DependencyC::class);

        $this->assertFalse($this->container->isBuildStack(DependencyC::class));

        $this->container->make(DependencyC::class);

        // 构建完成后应该不在堆栈中
        $this->assertFalse($this->container->isBuildStack(DependencyC::class));
    }

    public function testGetBuildStack(): void
    {
        $this->container->bind(DependencyC::class);

        $stack = $this->container->getBuildStack();

        $this->assertIsType('array', $stack);
        $this->assertEmpty($stack);
    }

    public function testGetAlias(): void
    {
        $this->container->bind('concrete', \StdClass::class);
        $this->container->alias('concrete', 'alias');
        $this->container->alias('alias', 'alias2');

        $result = $this->container->getAlias('alias2');

        $this->assertEquals('concrete', $result);
    }

    public function testHasAlias(): void
    {
        $this->container->bind('concrete', \StdClass::class);
        $this->container->alias('concrete', 'alias');

        $this->assertTrue($this->container->hasAlias('alias'));
        $this->assertFalse($this->container->hasAlias('nonexistent'));
    }

    public function testAppIntegration(): void
    {
        $app = App::getInstance();

        $this->assertInstanceOf(Container::class, $app->getContainer());

        // 测试核心服务
        $this->assertTrue($app->bound('request'));
        $this->assertTrue($app->bound('response'));
        $this->assertTrue($app->bound('route'));
    }
}

/**
 * 测试用的依赖类
 */
class DependencyA
{
    public string $value = 'default';
}

class DependencyB
{
    private DependencyA $a;

    public function __construct(DependencyA $a)
    {
        $this->a = $a;
    }

    public function getA(): DependencyA
    {
        return $this->a;
    }
}

class DependencyC
{
    public function __construct()
    {
        $container = Container::getInstance();
        // 空构造函数，用于测试构建堆栈
    }
}

class ContextualDependency
{
    private DependencyA $a;

    public function __construct(DependencyA $a)
    {
        $this->a = $a;
    }

    public function getA(): DependencyA
    {
        return $this->a;
    }
}

class AnotherDependency
{
    private DependencyA $a;

    public function __construct(DependencyA $a)
    {
        $this->a = $a;
    }

    public function getA(): DependencyA
    {
        return $this->a;
    }
}
