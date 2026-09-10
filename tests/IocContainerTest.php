<?php

declare(strict_types=1);

namespace Tests;

use Bin\Container\Container;
use Bin\Container\ContextualBindingBuilder;
use Bin\Container\Exceptions\BindingResolutionException;
use Bin\Container\Exceptions\CircularDependencyException;
use Bin\Psr\Container\ContainerInterface as PsrContainerInterface;
use Bin\Testing\TestCase;

/**
 * IoC 容器增强测试
 */
class IocContainerTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = new Container();
    }

    // ===== 标签绑定测试 =====

    public function testTagSingleService(): void
    {
        $this->container->bind('redis.cache', IocHelper_RedisCache::class);
        $this->container->bind('file.cache', IocHelper_FileCache::class);
        $this->container->tag(['redis.cache', 'file.cache'], 'cache');

        $cached = $this->container->tagged('cache');
        $this->assertCount(2, $cached);
        $this->assertInstanceOf(IocHelper_RedisCache::class, $cached[0]);
        $this->assertInstanceOf(IocHelper_FileCache::class, $cached[1]);
    }

    public function testTagMultipleTags(): void
    {
        $this->container->bind('report.speed', IocHelper_Simple::class);
        $this->container->bind('report.memory', IocHelper_Simple::class);
        $this->container->tag(['report.speed', 'report.memory'], ['reports', 'analytics']);

        $this->assertCount(2, $this->container->tagged('reports'));
        $this->assertCount(2, $this->container->tagged('analytics'));
    }

    public function testTaggedEmptyTag(): void
    {
        $result = $this->container->tagged('nonexistent');
        $this->assertSame([], $result);
    }

    // ===== 解析回调测试 =====

    public function testGlobalResolvingCallback(): void
    {
        $called = false;
        $this->container->resolving(function ($object, $c) use (&$called) {
            $called = true;
        });

        $this->container->bind('test.svc', IocHelper_Simple::class);
        $this->container->make('test.svc');
        $this->assertTrue($called);
    }

    public function testPerAbstractResolvingCallback(): void
    {
        $calledForTarget = false;

        $this->container->resolving('target', function ($object, $c) use (&$calledForTarget) {
            $calledForTarget = true;
        });

        $this->container->bind('target', IocHelper_Simple::class);
        $this->container->bind('other', IocHelper_Simple::class);

        $this->container->make('other');
        $this->assertFalse($calledForTarget, 'Target callback should NOT fire for other');

        $this->container->make('target');
        $this->assertTrue($calledForTarget, 'Target callback SHOULD fire for target');
    }

    public function testAfterResolvingCallbacks(): void
    {
        $order = [];

        $this->container->resolving(function ($obj, $c) use (&$order) {
            $order[] = 'resolving';
        });

        $this->container->afterResolving(function ($obj, $c) use (&$order) {
            $order[] = 'afterResolving';
        });

        $this->container->bind('svc', IocHelper_Simple::class);
        $this->container->make('svc');

        $this->assertSame(['resolving', 'afterResolving'], $order);
    }

    public function testSingletonResolvingFiresOnlyOnce(): void
    {
        $count = 0;
        $this->container->resolving(function ($obj, $c) use (&$count) {
            $count++;
        });

        $this->container->singleton('svc', IocHelper_Simple::class);
        $this->container->make('svc');
        $this->container->make('svc');

        $this->assertEquals(1, $count);
    }

    // ===== 重绑定回调测试 =====

    public function testRebindingCallbackFiresOnRebind(): void
    {
        $this->container->bind('cache', IocHelper_FileCache::class);
        $this->container->make('cache');

        $newInstance = null;
        $this->container->rebinding('cache', function ($c, $instance) use (&$newInstance) {
            $newInstance = $instance;
        });

        $this->container->bind('cache', IocHelper_RedisCache::class);
        $this->assertInstanceOf(IocHelper_RedisCache::class, $newInstance);
    }

    public function testRebindingDoesNotFireOnFirstBind(): void
    {
        $called = false;
        $this->container->rebinding('newsvc', function ($c, $instance) use (&$called) {
            $called = true;
        });

        $this->container->bind('newsvc', IocHelper_Simple::class);
        $this->assertFalse($called);
    }

    // ===== 方法注入增强测试 =====

    public function testCallClassAtMethod(): void
    {
        $this->container->bind(IocHelper_Controller::class);

        $result = $this->container->call(IocHelper_Controller::class . '@show', ['id' => 42]);
        $this->assertEquals('simple:42', $result);
    }

    public function testCallArrayCallable(): void
    {
        $target = new IocHelper_MethodTarget();
        $this->container->instance(IocHelper_Logger::class, new IocHelper_Logger());

        $result = $this->container->call([$target, 'handle'], ['message' => 'test']);
        $this->assertEquals('info:test', $result);
    }

    public function testCallClosureWithDI(): void
    {
        $this->container->instance(IocHelper_Logger::class, new IocHelper_Logger());

        $result = $this->container->call(function (IocHelper_Logger $logger) {
            return $logger->level;
        });
        $this->assertEquals('info', $result);
    }

    public function testCallExplicitParamsOverride(): void
    {
        $target = new IocHelper_MethodTarget();
        $this->container->instance(IocHelper_Logger::class, new IocHelper_Logger());

        $result = $this->container->call([$target, 'handle'], ['message' => 'custom']);
        $this->assertEquals('info:custom', $result);
    }

    // ===== 作用域绑定测试 =====

    public function testScopedSharesWithinScope(): void
    {
        $this->container->scoped('svc', IocHelper_Simple::class);

        $a = $this->container->make('svc');
        $b = $this->container->make('svc');
        $this->assertSame($a, $b);
    }

    public function testScopedResetsAfterResetScope(): void
    {
        $this->container->scoped('svc', IocHelper_Simple::class);

        $a = $this->container->make('svc');
        $this->container->resetScope();
        $b = $this->container->make('svc');

        $this->assertNotSame($a, $b);
    }

    public function testResetScopeDoesNotAffectSingletons(): void
    {
        $this->container->singleton('singleton_svc', IocHelper_Simple::class);
        $this->container->scoped('scoped_svc', IocHelper_Simple::class);

        $singletonA = $this->container->make('singleton_svc');
        $scopedA = $this->container->make('scoped_svc');

        $this->container->resetScope();

        $singletonB = $this->container->make('singleton_svc');
        $scopedB = $this->container->make('scoped_svc');

        $this->assertSame($singletonA, $singletonB, 'Singleton should survive resetScope');
        $this->assertNotSame($scopedA, $scopedB, 'Scoped should be reset');
    }

    public function testScopedWithoutConcrete(): void
    {
        $this->container->scoped(IocHelper_Simple::class);
        $instance = $this->container->make(IocHelper_Simple::class);
        $this->assertInstanceOf(IocHelper_Simple::class, $instance);
    }

    // ===== 条件绑定流畅接口测试 =====

    public function testWhenNeedsGive(): void
    {
        $this->container->bind(IocHelper_Logger::class);

        $this->container->when(IocHelper_ServiceWithDep::class)
            ->needs(IocHelper_Logger::class)
            ->give(function () {
                $l = new IocHelper_Logger();
                $l->level = 'debug';
                return $l;
            });

        $service = $this->container->make(IocHelper_ServiceWithDep::class);
        $this->assertEquals('debug', $service->logger->level);
    }

    public function testWhenNeedsGiveString(): void
    {
        $this->container->bind(IocHelper_Logger::class, IocHelper_Logger::class);

        $this->container->when(IocHelper_ServiceWithDep::class)
            ->needs(IocHelper_Logger::class)
            ->give(IocHelper_Logger::class);

        $service = $this->container->make(IocHelper_ServiceWithDep::class);
        $this->assertInstanceOf(IocHelper_Logger::class, $service->logger);
    }

    public function testWhenNeedsGivePrimitive(): void
    {
        $this->container->when(IocHelper_OptionalParam::class)
            ->needs('timeout')
            ->give(60);

        $service = $this->container->make(IocHelper_OptionalParam::class);
        $this->assertEquals(60, $service->timeout);
    }

    public function testOldContextualStillWorks(): void
    {
        $this->container->contextual(IocHelper_ServiceWithDep::class, IocHelper_Logger::class, function () {
            $l = new IocHelper_Logger();
            $l->level = 'warning';
            return $l;
        });

        $service = $this->container->make(IocHelper_ServiceWithDep::class);
        $this->assertEquals('warning', $service->logger->level);
    }

    public function testWhenReturnsContextualBindingBuilder(): void
    {
        $builder = $this->container->when(IocHelper_ServiceWithDep::class);
        $this->assertInstanceOf(ContextualBindingBuilder::class, $builder);
    }

    // ===== PSR-11 兼容测试 =====

    public function testPsr11Interface(): void
    {
        $this->assertInstanceOf(PsrContainerInterface::class, $this->container);
    }

    public function testPsr11Get(): void
    {
        $this->container->bind('svc', IocHelper_Simple::class);
        $instance = $this->container->get('svc');
        $this->assertInstanceOf(IocHelper_Simple::class, $instance);
    }

    public function testPsr11Has(): void
    {
        $this->container->bind('svc', IocHelper_Simple::class);
        $this->assertTrue($this->container->has('svc'));
        $this->assertFalse($this->container->has('nonexistent'));
    }

    public function testPsr11GetThrowsOnNotFound(): void
    {
        $thrown = false;
        try {
            $this->container->get('SomeNonExistentClass12345');
        } catch (BindingResolutionException $e) {
            $thrown = true;
        }
        $this->assertTrue($thrown, 'Should throw BindingResolutionException');
    }

    // ===== 异常体系测试 =====

    public function testBindingResolutionException(): void
    {
        $thrown = false;
        try {
            $this->container->make('SomeNonExistentClass12345');
        } catch (BindingResolutionException $e) {
            $thrown = true;
        }
        $this->assertTrue($thrown, 'Should throw BindingResolutionException');
    }

    public function testBindingResolutionExceptionContainsAbstract(): void
    {
        try {
            $this->container->make('MyMissingClass');
            $this->fail('Expected exception');
        } catch (BindingResolutionException $e) {
            $this->assertEquals('MyMissingClass', $e->getAbstract());
            $this->assertStringContainsString('MyMissingClass', $e->getMessage());
        }
    }

    public function testCircularDependencyException(): void
    {
        $this->container->bind(IocHelper_CircularA::class);
        $this->container->bind(IocHelper_CircularB::class);

        $thrown = false;
        try {
            $this->container->make(IocHelper_CircularA::class);
        } catch (CircularDependencyException $e) {
            $thrown = true;
        }
        $this->assertTrue($thrown, 'Should throw CircularDependencyException');
    }

    public function testCircularDependencyExtendsBindingResolution(): void
    {
        $this->container->bind(IocHelper_CircularA::class);
        $this->container->bind(IocHelper_CircularB::class);

        try {
            $this->container->make(IocHelper_CircularA::class);
            $this->fail('Expected exception');
        } catch (CircularDependencyException $e) {
            $this->assertInstanceOf(BindingResolutionException::class, $e);
            $this->assertStringContainsString('Circular dependency', $e->getMessage());
            $this->assertNotEmpty($e->getPath());
        }
    }

    // ===== bindIf / singletonIf 测试 =====

    public function testBindIf(): void
    {
        $this->container->bind('svc', IocHelper_Simple::class);
        $first = $this->container->make('svc');

        $this->container->bindIf('svc', IocHelper_Logger::class);
        $second = $this->container->make('svc');

        $this->assertInstanceOf(IocHelper_Simple::class, $second);
    }

    public function testSingletonIf(): void
    {
        $this->container->singletonIf('svc', IocHelper_Simple::class);
        $a = $this->container->make('svc');

        $this->container->singletonIf('svc', IocHelper_Logger::class);
        $b = $this->container->make('svc');

        $this->assertSame($a, $b);
    }

    // ===== 容器优化回归测试（2026-09） =====

    public function testContextualBindingWithNonClassAbstract(): void
    {
        $this->container->bind(IocHelper_Logger::class);
        $this->container->bind('report.service', IocHelper_ServiceWithDep::class);

        $this->container->when(IocHelper_ServiceWithDep::class)
            ->needs(IocHelper_Logger::class)
            ->give(function () {
                $logger = new IocHelper_Logger();
                $logger->level = 'debug';
                return $logger;
            });

        $service = $this->container->make('report.service');
        $this->assertEquals(
            'debug',
            $service->logger->level,
            'when(具体类名) 注册的上下文绑定应在字符串抽象名绑定下生效'
        );
    }

    public function testResolvingPerAbstractWithCallableStringAbstract(): void
    {
        $fired = false;
        // 'strlen' 是真实函数名（可调用字符串），不应被误判为全局回调
        $this->container->resolving('strlen', function ($object, $container) use (&$fired) {
            $fired = true;
        });

        $this->container->bind('strlen', IocHelper_Simple::class);
        $this->container->make('strlen');

        $this->assertTrue($fired, '按抽象名回调应被注册并触发');
    }

    public function testCallPositionalParameters(): void
    {
        $target = new IocHelper_PositionalTarget();

        $result = $this->container->call([$target, 'combine'], ['first', 'second']);
        $this->assertEquals('first:second:default', $result);
    }

    public function testCallMixedNamedAndPositionalParameters(): void
    {
        $target = new IocHelper_PositionalTarget();

        $result = $this->container->call([$target, 'combine'], ['tail' => '!', 'head']);
        $this->assertEquals('head:default:!', $result);
    }

    public function testCallPositionalSkipsClassTypedParameterUnlessInstanceMatches(): void
    {
        $this->container->bind(IocHelper_Logger::class);
        $target = new IocHelper_PositionalTarget();

        // 标量位置参数不应错位注入 Logger 参数，而应流向后续标量参数
        $result = $this->container->call([$target, 'greet'], ['hello']);
        $this->assertEquals('info:hello', $result);

        // 位置值是 Logger 实例时才被类类型参数消费
        $logger = new IocHelper_Logger();
        $logger->level = 'debug';
        $result = $this->container->call([$target, 'greet'], [$logger, 'hello']);
        $this->assertEquals('debug:hello', $result);
    }

    public function testCallVariadicPositionalSpread(): void
    {
        $target = new IocHelper_PositionalTarget();

        $result = $this->container->call([$target, 'listing'], ['a', 'b', 'c']);
        $this->assertEquals('a,b,c', $result);
    }

    public function testExtendAppliesToResolvedScopedInstance(): void
    {
        $this->container->scoped('scoped_svc', IocHelper_Simple::class);
        $this->container->make('scoped_svc');

        $replacement = new IocHelper_Simple();
        $replacement->name = 'extended';
        $this->container->extend('scoped_svc', fn () => $replacement);

        $this->assertSame($replacement, $this->container->make('scoped_svc'));
    }

    public function testOptionalDependencyFallsBackToDefaultWhenUnresolvable(): void
    {
        $service = $this->container->make(IocHelper_OptionalBrokenDep::class);

        $this->assertNull($service->dep, '可选类依赖解析失败应回退默认值');
    }

    public function testOptionalDependencyCycleFallsBackToDefault(): void
    {
        $b = $this->container->make(IocHelper_CycleRequired::class);

        $this->assertInstanceOf(IocHelper_CycleOptional::class, $b->a);
        $this->assertNull($b->a->b, '循环依赖检测命中时可选参数应回退默认值而非抛异常');
    }

    public function testGetBoundButBrokenEntryThrowsContainerException(): void
    {
        $this->container->bind('broken', IocHelper_BrokenDeps::class);

        try {
            $this->container->get('broken');
            $this->fail('Expected exception');
        } catch (\Bin\Container\Exceptions\NotFoundException $e) {
            $this->fail('已绑定但解析失败的条目不应抛 NotFoundException');
        } catch (\Bin\Container\Exceptions\ContainerException $e) {
            $this->assertEquals('broken', $e->getAbstract());
        }
    }

    public function testGetUnknownIdentifierStillThrowsNotFound(): void
    {
        $thrown = null;

        try {
            $this->container->get('SomeNonExistentClass67890');
        } catch (\Throwable $e) {
            $thrown = $e;
        }

        $this->assertInstanceOf(
            \Bin\Container\Exceptions\NotFoundException::class,
            $thrown,
            '未绑定的未知标识符应抛 NotFoundException'
        );
    }

    public function testRebindingDoesNotFireImmediatelyOnRegistration(): void
    {
        $this->container->singleton('cache', IocHelper_FileCache::class);
        $this->container->make('cache');

        $fires = 0;
        $this->container->rebinding('cache', function () use (&$fires) {
            $fires++;
        });

        $this->assertEquals(0, $fires, '注册回调不应立即触发');

        $this->container->bind('cache', IocHelper_RedisCache::class);
        $this->assertEquals(1, $fires, '重绑定时应触发');
    }

    public function testScalarClosureBindingThrowsContainerException(): void
    {
        $this->container->bind('timeout', fn () => 60);

        $thrown = null;

        try {
            $this->container->make('timeout');
        } catch (BindingResolutionException $e) {
            $thrown = $e;
        } catch (\TypeError $e) {
            $this->fail('标量闭包绑定不应抛原生 TypeError');
        }

        $this->assertNotNull($thrown);
        $this->assertStringContainsString('timeout', $thrown->getMessage());
    }
}

// ===== 测试辅助类（放在测试类之后，不会被 TestRunner 误识别为测试类） =====

class IocHelper_Simple
{
    public string $name = 'simple';
}

class IocHelper_Logger
{
    public string $level = 'info';
}

class IocHelper_FileCache
{
    public string $driver = 'file';
}

class IocHelper_RedisCache
{
    public string $driver = 'redis';
}

class IocHelper_ServiceWithDep
{
    public IocHelper_Logger $logger;

    public function __construct(IocHelper_Logger $logger)
    {
        $this->logger = $logger;
    }
}

class IocHelper_OptionalParam
{
    public function __construct(public int $timeout = 30) {}
}

class IocHelper_CircularA
{
    public function __construct(IocHelper_CircularB $b) {}
}

class IocHelper_CircularB
{
    public function __construct(IocHelper_CircularA $a) {}
}

class IocHelper_MethodTarget
{
    public function handle(IocHelper_Logger $logger, string $message = 'default'): string
    {
        return $logger->level . ':' . $message;
    }
}

class IocHelper_Controller
{
    public function show(IocHelper_Simple $service, int $id = 0): string
    {
        return $service->name . ':' . $id;
    }
}

class IocHelper_PositionalTarget
{
    public function combine(string $head, string $middle = 'default', string $tail = 'default'): string
    {
        return $head . ':' . $middle . ':' . $tail;
    }

    public function greet(IocHelper_Logger $logger, string $message = 'n/a'): string
    {
        return $logger->level . ':' . $message;
    }

    public function listing(string ...$items): string
    {
        return implode(',', $items);
    }
}

class IocHelper_OptionalBrokenDep
{
    public function __construct(public ?IocHelper_UnresolvableDeps $dep = null) {}
}

class IocHelper_UnresolvableDeps
{
    public function __construct(SomeMissingIocClass999 $missing) {}
}

class IocHelper_BrokenDeps
{
    public function __construct(SomeMissingIocClass999 $missing) {}
}

class IocHelper_CycleOptional
{
    public function __construct(public ?IocHelper_CycleRequired $b = null) {}
}

class IocHelper_CycleRequired
{
    public function __construct(public IocHelper_CycleOptional $a) {}
}
