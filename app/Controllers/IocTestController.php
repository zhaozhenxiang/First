<?php

declare(strict_types=1);

namespace App\Controllers;

use Bin\App\App;
use Bin\Container\Container;
use Bin\Psr\Container\ContainerInterface as PsrContainerInterface;

/**
 * IoC 容器行为测试控制器
 *
 * 通过 HTTP 请求验证 IoC 容器的各项功能
 */
class IocTestController extends BaseController
{
    // ===== 1. 基础绑定与解析 =====

    /**
     * 测试 bind + make：每次解析返回新实例
     */
    public function bindMake(): string
    {
        $container = new Container();
        $container->bind('service', \StdClass::class);

        $a = $container->make('service');
        $b = $container->make('service');

        return $this->encode([
            'test' => 'bind_make',
            'different_instances' => $a !== $b,
            'class' => get_class($a),
        ]);
    }

    /**
     * 测试 singleton：多次解析返回同一实例
     */
    public function singleton(): string
    {
        $container = new Container();
        $container->singleton('service', \StdClass::class);

        $a = $container->make('service');
        $b = $container->make('service');

        return $this->encode([
            'test' => 'singleton',
            'same_instance' => $a === $b,
        ]);
    }

    // ===== 2. 标签绑定 =====

    /**
     * 测试 tag + tagged：按标签批量解析
     */
    public function taggedBindings(): string
    {
        $container = new Container();
        $container->bind('cache.redis', \StdClass::class);
        $container->bind('cache.file', \StdClass::class);
        $container->tag(['cache.redis', 'cache.file'], 'cache');

        $cached = $container->tagged('cache');
        $empty = $container->tagged('nonexistent');

        return $this->encode([
            'test' => 'tagged_bindings',
            'count' => count($cached),
            'empty_tag' => $empty,
        ]);
    }

    // ===== 3. 解析回调 =====

    /**
     * 测试 resolving + afterResolving 回调
     */
    public function resolvingCallbacks(): string
    {
        $container = new Container();
        $log = [];

        $container->resolving(function ($obj, $c) use (&$log) {
            $log[] = 'global_resolving';
        });

        $container->resolving('svc', function ($obj, $c) use (&$log) {
            $log[] = 'per_abstract_resolving';
        });

        $container->afterResolving(function ($obj, $c) use (&$log) {
            $log[] = 'global_after_resolving';
        });

        $container->bind('svc', \StdClass::class);
        $container->make('svc');

        return $this->encode([
            'test' => 'resolving_callbacks',
            'order' => $log,
        ]);
    }

    // ===== 4. 作用域绑定 =====

    /**
     * 测试 scoped：同一作用域共享，resetScope 后重新创建
     */
    public function scopedBinding(): string
    {
        $container = new Container();
        $container->scoped('svc', \StdClass::class);

        $a = $container->make('svc');
        $b = $container->make('svc');

        $container->resetScope();
        $c = $container->make('svc');

        return $this->encode([
            'test' => 'scoped_binding',
            'same_in_scope' => $a === $b,
            'different_after_reset' => $a !== $c,
        ]);
    }

    /**
     * 测试 scoped 不影响 singleton
     */
    public function scopedWithSingleton(): string
    {
        $container = new Container();
        $container->singleton('singleton_svc', \StdClass::class);
        $container->scoped('scoped_svc', \StdClass::class);

        $s1 = $container->make('singleton_svc');
        $sc1 = $container->make('scoped_svc');

        $container->resetScope();

        $s2 = $container->make('singleton_svc');
        $sc2 = $container->make('scoped_svc');

        return $this->encode([
            'test' => 'scoped_with_singleton',
            'singleton_survives' => $s1 === $s2,
            'scoped_resets' => $sc1 !== $sc2,
        ]);
    }

    // ===== 5. 条件绑定（when/needs/give） =====

    /**
     * 测试 when()->needs()->give() 流畅接口
     */
    public function conditionalBinding(): string
    {
        $container = new Container();

        // 绑定接口到默认实现
        $container->bind(IocTestLoggerInterface::class, IocTestFileLogger::class);

        // 对特定控制器使用不同实现
        $container->when(IocTestAuditService::class)
            ->needs(IocTestLoggerInterface::class)
            ->give(function () {
                return new IocTestCloudLogger();
            });

        // 默认解析得到 FileLogger
        $default = $container->make(IocTestLoggerInterface::class);

        // AuditService 的依赖得到 CloudLogger
        $audit = $container->make(IocTestAuditService::class);

        return $this->encode([
            'test' => 'conditional_binding',
            'default_logger' => get_class($default),
            'audit_logger' => get_class($audit->logger),
        ]);
    }

    // ===== 6. PSR-11 兼容 =====

    /**
     * 测试 PSR-11 get/has
     */
    public function psr11(): string
    {
        $container = new Container();
        $container->bind('my_service', \StdClass::class);

        $hasService = $container->has('my_service');
        $hasMissing = $container->has('nonexistent');
        $isPsr11 = $container instanceof PsrContainerInterface;

        $instance = $container->get('my_service');

        $notFoundThrown = false;
        try {
            $container->get('NonExistentClass99999');
        } catch (\Throwable $e) {
            $notFoundThrown = true;
        }

        return $this->encode([
            'test' => 'psr11',
            'implements_interface' => $isPsr11,
            'has_bound' => $hasService,
            'has_missing' => $hasMissing,
            'get_returns_instance' => $instance instanceof \StdClass,
            'get_throws_on_missing' => $notFoundThrown,
        ]);
    }

    // ===== 7. 循环依赖检测 =====

    /**
     * 测试循环依赖异常
     */
    public function circularDependency(): string
    {
        $container = new Container();
        $container->bind(IocTestCircularA::class);
        $container->bind(IocTestCircularB::class);

        $thrown = false;
        $message = '';
        try {
            $container->make(IocTestCircularA::class);
        } catch (\Bin\Container\Exceptions\CircularDependencyException $e) {
            $thrown = true;
            $message = $e->getMessage();
        }

        return $this->encode([
            'test' => 'circular_dependency',
            'exception_thrown' => $thrown,
            'message_contains_circular' => str_contains($message, 'Circular dependency'),
        ]);
    }

    // ===== 8. 方法注入 =====

    /**
     * 测试 call() 方法的依赖注入
     */
    public function methodInjection(): string
    {
        $container = new Container();
        $container->instance(IocTestLoggerInterface::class, new IocTestFileLogger());

        // 测试 Class@method 语法
        $container->bind(IocTestControllerService::class);
        $result = $container->call(IocTestControllerService::class . '@handle', ['message' => 'hello']);

        return $this->encode([
            'test' => 'method_injection',
            'call_result' => $result,
        ]);
    }

    // ===== 9. 重绑定回调 =====

    /**
     * 测试 rebinding 回调
     */
    public function rebindingCallback(): string
    {
        $container = new Container();
        $container->bind('cache', \StdClass::class);
        $container->make('cache');

        $newInstance = null;
        $container->rebinding('cache', function ($c, $instance) use (&$newInstance) {
            $newInstance = $instance;
        });

        // 重新绑定
        $container->bind('cache', IocTestFileLogger::class);

        return $this->encode([
            'test' => 'rebinding_callback',
            'callback_fired' => $newInstance !== null,
            'new_instance_type' => get_class($newInstance),
        ]);
    }

    // ===== 10. extend 扩展器 =====

    /**
     * 测试 extend 装饰器模式
     */
    public function extendDecorator(): string
    {
        $container = new Container();
        $container->bind('reporter', \StdClass::class);

        $container->extend('reporter', function ($instance, $c) {
            $instance->extra = 'decorated';
            return $instance;
        });

        $instance = $container->make('reporter');

        return $this->encode([
            'test' => 'extend_decorator',
            'has_extra' => isset($instance->extra),
            'extra_value' => $instance->extra ?? null,
        ]);
    }

    // ===== 11. 通过 App 门面使用容器 =====

    /**
     * 测试通过 App 门面注册和使用服务
     */
    public function appFacade(): string
    {
        App::getInstance()->singleton('ioc_test.service', function () {
            $obj = new \StdClass();
            $obj->source = 'app_facade';
            return $obj;
        });

        $a = App::getInstance()->make('ioc_test.service');
        $b = app('ioc_test.service');

        return $this->encode([
            'test' => 'app_facade',
            'singleton_works' => $a === $b,
            'source' => $a->source,
        ]);
    }

    // ===== 12. bindIf / singletonIf =====

    /**
     * 测试条件绑定：不覆盖已有绑定
     */
    public function conditionalRegister(): string
    {
        $container = new Container();
        $container->bind('svc', \StdClass::class);
        $first = $container->make('svc');

        // bindIf 不应覆盖
        $container->bindIf('svc', IocTestFileLogger::class);
        $second = $container->make('svc');

        // singletonIf 不应覆盖
        $container->singleton('single', \StdClass::class);
        $sa = $container->make('single');
        $container->singletonIf('single', IocTestFileLogger::class);
        $sb = $container->make('single');

        return $this->encode([
            'test' => 'conditional_register',
            'bindIf_no_override' => get_class($second) === 'stdClass',
            'singletonIf_no_override' => $sa === $sb,
        ]);
    }

    // ===== 13. 别名解析 =====

    public function aliasResolution(): string
    {
        $container = new Container();
        $container->bind('cache.redis', IocTestFileLogger::class);
        $container->alias('cache.redis', 'cache');

        $hasAlias = $container->hasAlias('cache');
        $hasAliasOriginal = $container->hasAlias('cache.redis');
        $resolved = $container->getAlias('cache');

        $instance = $container->make('cache');

        return $this->encode([
            'test' => 'alias_resolution',
            'alias_registered' => $hasAlias,
            'original_not_alias' => !$hasAliasOriginal,
            'alias_resolves_to' => $resolved,
            'make_via_alias_works' => $instance instanceof IocTestFileLogger,
        ]);
    }

    // ===== 14. 批量操作 =====

    public function batchOperations(): string
    {
        $container = new Container();

        // bindArray
        $container->bindArray([
            'svc.a' => IocTestFileLogger::class,
            'svc.b' => IocTestCloudLogger::class,
        ]);

        // singletonArray
        $container->singletonArray([
            'svc.c' => IocTestFileLogger::class,
        ]);

        // instanceArray
        $obj = new \StdClass();
        $obj->tag = 'instanced';
        $container->instanceArray(['svc.d' => $obj]);

        // bindAndMake
        $bam = $container->bindAndMake('svc.e', IocTestFileLogger::class);

        // singletonAndMake
        $sam = $container->singletonAndMake('svc.f', IocTestFileLogger::class);

        // factory
        $container->factory('svc.g', function ($c) {
            $o = new \StdClass();
            $o->from = 'factory';
            return $o;
        });

        $factoryResult = $container->make('svc.g');

        return $this->encode([
            'test' => 'batch_operations',
            'bind_array_a' => $container->make('svc.a') instanceof IocTestFileLogger,
            'bind_array_b' => $container->make('svc.b') instanceof IocTestCloudLogger,
            'singleton_array_c' => $container->make('svc.c') === $container->make('svc.c'),
            'instance_array_d' => $container->make('svc.d')->tag === 'instanced',
            'bind_and_make' => $bam instanceof IocTestFileLogger,
            'singleton_and_make' => $sam instanceof IocTestFileLogger,
            'factory_works' => $factoryResult->from === 'factory',
        ]);
    }

    // ===== 15. 上下文 giveTagged =====

    public function contextualGiveTagged(): string
    {
        $container = new Container();
        $container->bind('logger.file', IocTestFileLogger::class);
        $container->bind('logger.cloud', IocTestCloudLogger::class);
        $container->tag(['logger.file', 'logger.cloud'], 'loggers');

        $container->bind(IocTestTaggedConsumer::class);
        $container->when(IocTestTaggedConsumer::class)
            ->needs('loggers')
            ->giveTagged('loggers');

        $consumer = $container->make(IocTestTaggedConsumer::class);

        return $this->encode([
            'test' => 'contextual_give_tagged',
            'received_array' => is_array($consumer->loggers),
            'count' => count($consumer->loggers),
        ]);
    }

    // ===== 16. 检查方法 =====

    public function inspectionMethods(): string
    {
        $container = new Container();
        $container->bind('svc', IocTestFileLogger::class);

        $boundBefore = $container->bound('svc');
        $hasBinding = $container->hasBinding('svc');
        $notBound = $container->bound('nonexistent');
        $notResolved = $container->resolved('svc');

        $container->make('svc');

        $resolvedAfter = $container->resolved('svc');
        $bindings = $container->getBindings();
        $hasSvcKey = isset($bindings['svc']);

        // instance 测试
        $obj = new \StdClass();
        $container->instance('shared', $obj);
        $hasInstance = $container->hasInstance('shared');
        $gotInstance = $container->getInstanceOf('shared');
        $noInstance = $container->hasInstance('nonexistent');

        return $this->encode([
            'test' => 'inspection_methods',
            'bound_before' => $boundBefore,
            'has_binding' => $hasBinding,
            'not_bound' => !$notBound,
            'not_resolved_before_make' => !$notResolved,
            'resolved_after_make' => $resolvedAfter,
            'get_bindings_has_svc' => $hasSvcKey,
            'has_instance' => $hasInstance,
            'get_instance_of' => $gotInstance === $obj,
            'no_instance_for_missing' => !$noInstance,
        ]);
    }

    // ===== 17. flush/forget =====

    public function flushForget(): string
    {
        $container = new Container();

        // forget 单个
        $container->bind('svc.a', IocTestFileLogger::class);
        $container->bind('svc.b', IocTestCloudLogger::class);
        $container->make('svc.a');

        $resolvedBefore = $container->resolved('svc.a');
        $container->forget('svc.a');
        $resolvedAfter = $container->resolved('svc.a');
        $boundAfterForget = $container->bound('svc.a');
        $bStillBound = $container->bound('svc.b');

        // flush 全部
        $container->flush();
        $bAfterFlush = $container->bound('svc.b');

        return $this->encode([
            'test' => 'flush_forget',
            'resolved_before_forget' => $resolvedBefore,
            'resolved_after_forget' => !$resolvedAfter,
            'not_bound_after_forget' => !$boundAfterForget,
            'b_still_bound' => $bStillBound,
            'b_not_bound_after_flush' => !$bAfterFlush,
        ]);
    }

    // ===== 18. mock =====

    public function mockService(): string
    {
        $container = new Container();
        $container->bind('svc', IocTestFileLogger::class);

        $mock = $container->mock('svc');

        $isMock = $mock instanceof IocTestFileLogger;
        $sameInstance = $container->make('svc') === $mock;

        return $this->encode([
            'test' => 'mock_service',
            'mock_is_correct_type' => $isMock,
            'make_returns_mock' => $sameInstance,
        ]);
    }

    // ===== 19. facade 解析 =====

    public function facadeResolve(): string
    {
        $container = new Container();
        $container->bind('my.facade', IocTestFileLogger::class);
        $container->alias('my.facade', 'MyFacade');

        $resolved = $container->facade('MyFacade');
        $nullForUnknown = $container->facade('UnknownFacade');

        return $this->encode([
            'test' => 'facade_resolve',
            'facade_resolves' => $resolved instanceof IocTestFileLogger,
            'unknown_returns_null' => $nullForUnknown === null,
        ]);
    }

    // ===== 20. call 变体 =====

    public function callVariants(): string
    {
        $container = new Container();
        $container->instance(IocTestLoggerInterface::class, new IocTestFileLogger());

        // 闭包
        $closureResult = $container->call(function (IocTestLoggerInterface $logger) {
            return $logger->name() . '_closure';
        });

        // 数组回调
        $target = new IocTestControllerService();
        $arrayResult = $container->call([$target, 'handle'], ['message' => 'array']);

        // Class@method
        $container->bind(IocTestControllerService::class);
        $classAtResult = $container->call(IocTestControllerService::class . '@handle', ['message' => 'classat']);

        return $this->encode([
            'test' => 'call_variants',
            'closure' => $closureResult,
            'array' => $arrayResult,
            'class_at' => $classAtResult,
        ]);
    }

    // ===== 21. instance 绑定 =====

    public function instanceBinding(): string
    {
        $container = new Container();
        $obj = new \StdClass();
        $obj->value = 'bound_instance';

        $container->instance('my.instance', $obj);

        $a = $container->make('my.instance');
        $b = $container->make('my.instance');

        return $this->encode([
            'test' => 'instance_binding',
            'same_instance' => $a === $b,
            'value_preserved' => $a->value === 'bound_instance',
        ]);
    }

    // ===== 22. 多级 extend =====

    public function multiExtender(): string
    {
        $container = new Container();
        $container->bind('svc', \StdClass::class);

        $container->extend('svc', function ($instance, $c) {
            $instance->step = 1;
            return $instance;
        });

        $container->extend('svc', function ($instance, $c) {
            $instance->step = $instance->step + 1;
            $instance->extra = 'chained';
            return $instance;
        });

        $instance = $container->make('svc');

        return $this->encode([
            'test' => 'multi_extender',
            'step' => $instance->step,
            'extra' => $instance->extra,
            'has_extenders' => $container->hasExtenders('svc'),
        ]);
    }

    // ===== 23. resolving 细节 =====

    public function resolvingDetail(): string
    {
        $container = new Container();
        $log = [];

        // 全局 resolving
        $container->resolving(function ($obj, $c) use (&$log) {
            $log[] = 'global:' . get_class($obj);
        });

        // 特定抽象名 afterResolving
        $container->afterResolving('svc', function ($obj, $c) use (&$log) {
            $log[] = 'after_svc:' . get_class($obj);
        });

        // singleton resolving 只触发一次
        $count = 0;
        $container->resolving(function ($obj, $c) use (&$count) {
            $count++;
        });

        $container->singleton('svc', \StdClass::class);
        $container->make('svc');
        $container->make('svc');

        return $this->encode([
            'test' => 'resolving_detail',
            'singleton_resolving_fired_once' => $count === 1,
        ]);
    }

    // ===== 24. 深层注入 =====

    public function deepInjection(): string
    {
        $container = new Container();
        $container->bind(IocTestLoggerInterface::class, IocTestFileLogger::class);
        $container->bind(IocTestDeepA::class);

        $a = $container->make(IocTestDeepA::class);

        return $this->encode([
            'test' => 'deep_injection',
            'a_has_b' => isset($a->b),
            'b_has_c' => isset($a->b->c),
            'c_has_logger' => isset($a->b->c->logger),
            'logger_name' => $a->b->c->logger->name(),
        ]);
    }

    // ===== 25. 闭包工厂 =====

    public function closureFactory(): string
    {
        $container = new Container();

        $container->bind('factory_svc', function ($c) {
            $obj = new \StdClass();
            $obj->created_at = time();
            return $obj;
        });

        $a = $container->make('factory_svc');
        $b = $container->make('factory_svc');

        $container->singleton('singleton_factory', function ($c) {
            $obj = new \StdClass();
            $obj->id = uniqid();
            return $obj;
        });

        $s1 = $container->make('singleton_factory');
        $s2 = $container->make('singleton_factory');

        return $this->encode([
            'test' => 'closure_factory',
            'factory_creates_different' => $a !== $b,
            'singleton_factory_same' => $s1 === $s2,
        ]);
    }

    // ===== 26. 容器 flush =====

    public function containerFlush(): string
    {
        $container = new Container();
        $container->bind('svc.a', \StdClass::class);
        $container->singleton('svc.b', \StdClass::class);
        $container->alias('svc.a', 'alias_a');
        $container->tag(['svc.a', 'svc.b'], 'all');
        $container->resolving(function () {});
        $container->afterResolving(function () {});

        $container->flush();

        return $this->encode([
            'test' => 'container_flush',
            'not_bound_a' => !$container->bound('svc.a'),
            'not_bound_b' => !$container->bound('svc.b'),
            'no_alias' => !$container->hasAlias('alias_a'),
            'empty_bindings' => empty($container->getBindings()),
        ]);
    }

    // ===== 27. 依赖覆盖 =====

    public function dependencyOverride(): string
    {
        $container = new Container();
        $container->bind(IocTestLoggerInterface::class, IocTestFileLogger::class);

        $first = $container->make(IocTestLoggerInterface::class);

        // 覆盖绑定
        $container->bind(IocTestLoggerInterface::class, IocTestCloudLogger::class);

        $second = $container->make(IocTestLoggerInterface::class);

        return $this->encode([
            'test' => 'dependency_override',
            'first_was_file' => $first instanceof IocTestFileLogger,
            'second_is_cloud' => $second instanceof IocTestCloudLogger,
        ]);
    }

    /**
     * 编码为 JSON，失败时抛出异常（避免 json_encode 返回 false 触发返回类型错误）
     */
    private function encode(array $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR);
    }

    // ===== 28. 构建堆栈检测 =====

    public function buildStackInspection(): string
    {
        $container = new Container();

        // 初始为空
        $emptyStack = $container->getBuildStack();
        $notInStack = $container->isBuildStack('nothing');

        // 测试 afterResolving 可以工作
        $lastResolved = null;
        $container->afterResolving(function ($obj, $c) use (&$lastResolved) {
            $lastResolved = get_class($obj);
        });

        $container->bind(IocTestLoggerInterface::class, IocTestFileLogger::class);
        $container->bind(IocTestDeepA::class);
        $container->make(IocTestDeepA::class);

        return $this->encode([
            'test' => 'build_stack',
            'empty_initial' => empty($emptyStack),
            'not_in_stack' => !$notInStack,
            'after_resolving_fired' => $lastResolved === IocTestDeepA::class,
        ]);
    }
}

// ===== 测试辅助接口和类 =====

interface IocTestLoggerInterface
{
    public function name(): string;
}

class IocTestFileLogger implements IocTestLoggerInterface
{
    public function name(): string
    {
        return 'file';
    }
}

class IocTestCloudLogger implements IocTestLoggerInterface
{
    public function name(): string
    {
        return 'cloud';
    }
}

class IocTestAuditService
{
    public IocTestLoggerInterface $logger;

    public function __construct(IocTestLoggerInterface $logger)
    {
        $this->logger = $logger;
    }
}

class IocTestControllerService
{
    public function handle(IocTestLoggerInterface $logger, string $message = 'default'): string
    {
        return $logger->name() . ':' . $message;
    }
}

class IocTestCircularA
{
    public function __construct(IocTestCircularB $b) {}
}

class IocTestCircularB
{
    public function __construct(IocTestCircularA $a) {}
}

class IocTestDeepC
{
    public IocTestLoggerInterface $logger;

    public function __construct(IocTestLoggerInterface $logger)
    {
        $this->logger = $logger;
    }
}

class IocTestDeepB
{
    public IocTestDeepC $c;

    public function __construct(IocTestDeepC $c)
    {
        $this->c = $c;
    }
}

class IocTestDeepA
{
    public IocTestDeepB $b;

    public function __construct(IocTestDeepB $b)
    {
        $this->b = $b;
    }
}

class IocTestTaggedConsumer
{
    public array $loggers;

    public function __construct(array $loggers = [])
    {
        $this->loggers = $loggers;
    }
}

class IocTestReportService
{
    public array $loggers;

    public function __construct(array $loggers = [])
    {
        $this->loggers = $loggers;
    }
}
