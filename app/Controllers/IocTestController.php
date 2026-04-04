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

        return json_encode([
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

        return json_encode([
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

        return json_encode([
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

        return json_encode([
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

        return json_encode([
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

        return json_encode([
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

        return json_encode([
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

        return json_encode([
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

        return json_encode([
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

        return json_encode([
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

        return json_encode([
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

        return json_encode([
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

        return json_encode([
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

        return json_encode([
            'test' => 'conditional_register',
            'bindIf_no_override' => get_class($second) === 'stdClass',
            'singletonIf_no_override' => $sa === $sb,
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
