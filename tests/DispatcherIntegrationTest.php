<?php

declare(strict_types=1);

namespace Tests;

use Bin\App\App;
use Bin\Container\Container;
use Bin\Middleware\Middleware;
use Bin\Response\Response;
use Bin\Routing\ControllerDispatcher;
use Bin\Route\Route;
use Bin\Route\RouteAction;
use Bin\Testing\TestCase;

/**
 * 调度器集成测试
 *
 * 验证 ControllerDispatcher 的容器驱动调度：
 * - 控制器方法通过容器实例化 + 参数注入
 * - 闭包 action 通过容器调用
 * - URL 路由参数映射
 * - Response 包装
 * - 中间件容器构建
 */
class DispatcherIntegrationTest extends TestCase
{
    private ControllerDispatcher $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dispatcher = new ControllerDispatcher();
        \Bin\Route\RouteCollection::clear();

        $property = new \ReflectionProperty(RouteAction::class, 'dispatcher');
        $property->setAccessible(true);
        $property->setValue(null, null);
    }

    protected function tearDown(): void
    {
        \Bin\Route\RouteCollection::clear();
        App::getInstance()->forget(ControllerDispatcher::class);
        $property = new \ReflectionProperty(RouteAction::class, 'dispatcher');
        $property->setAccessible(true);
        $property->setValue(null, null);
        parent::tearDown();
    }

    // ================================================================
    // 控制器方法调度
    // ================================================================

    public function testDispatcherCreatesControllerWithoutConstructor(): void
    {
        $controller = new class {
            public function index(): string { return 'simple'; }
        };

        $className = get_class($controller);
        Container::getInstance()->instance($className, $controller);

        $route = new Route('GET', '/simple', $className . '@index');
        $result = $this->dispatcher->dispatch($className, 'index', $route);

        $this->assertEquals('simple', $result->getContent());
    }

    public function testDispatcherResolvesMethodDependencies(): void
    {
        $controller = new class {
            public function show(\stdClass $std): string { return $std->value ?? 'none'; }
        };

        $className = get_class($controller);
        Container::getInstance()->instance($className, $controller);

        $service = new \stdClass();
        $service->value = 'from-container';
        Container::getInstance()->instance(\stdClass::class, $service);

        $route = new Route('GET', '/show', $className . '@show');
        $result = $this->dispatcher->dispatch($className, 'show', $route);

        $this->assertEquals('from-container', $result->getContent());
    }

    // ================================================================
    // 闭包调度
    // ================================================================

    public function testDispatcherClosureWithTypedParam(): void
    {
        $service = new \stdClass();
        $service->name = 'closure-test';
        Container::getInstance()->instance(\stdClass::class, $service);

        $closure = function (\stdClass $s): string {
            return $s->name;
        };

        $route = new Route('GET', '/closure', $closure);
        $result = $this->dispatcher->dispatchClosure($closure, $route);

        $this->assertEquals('closure-test', $result->getContent());
    }

    public function testDispatcherClosureWithUrlParam(): void
    {
        $request = \Bin\Request\Request::capture();
        $request->setUrlParam(['id' => '42']);

        // 将 Request 实例注册到容器，让 ControllerDispatcher 获取同一个实例
        Container::getInstance()->instance(\Bin\Request\Request::class, $request);

        $closure = fn (string $id): string => "id={$id}";

        $route = new Route('GET', '/user/{id}', $closure);
        $result = $this->dispatcher->dispatchClosure($closure, $route);

        $this->assertEquals('id=42', $result->getContent());
    }

    // ================================================================
    // RouteAction getter/setter
    // ================================================================

    public function testRouteActionGetDispatcherReturnsSameInstance(): void
    {
        $d1 = RouteAction::getDispatcher();
        $d2 = RouteAction::getDispatcher();
        $this->assertSame($d1, $d2);
    }

    public function testRouteActionSetDispatcher(): void
    {
        $custom = new ControllerDispatcher();
        RouteAction::setDispatcher($custom);
        $this->assertSame($custom, RouteAction::getDispatcher());
        RouteAction::setDispatcher(new ControllerDispatcher());
    }

    public function testRouteActionResolvesDispatcherFromContainer(): void
    {
        $custom = new ControllerDispatcher();
        App::getInstance()->instance(ControllerDispatcher::class, $custom);

        $property = new \ReflectionProperty(RouteAction::class, 'dispatcher');
        $property->setAccessible(true);
        $property->setValue(null, null);

        $this->assertSame($custom, RouteAction::getDispatcher());
    }

    public function testRouteActionPropagatesDispatcherResolutionFailures(): void
    {
        App::getInstance()->forget(ControllerDispatcher::class);
        App::getInstance()->singleton(ControllerDispatcher::class, function (): ControllerDispatcher {
            throw new \RuntimeException('dispatcher wiring failed');
        });

        try {
            RouteAction::getDispatcher();
            $this->fail('Expected dispatcher resolution failure to be propagated');
        } catch (\RuntimeException $e) {
            $this->assertSame('dispatcher wiring failed', $e->getMessage());
        }
    }

    public function testRouteActionStartsEachTestWithFreshDispatcherState(): void
    {
        $custom = new ControllerDispatcher();
        App::getInstance()->instance(ControllerDispatcher::class, $custom);

        $this->assertSame($custom, RouteAction::getDispatcher());
    }

    // ================================================================
    // Response 包装
    // ================================================================

    public function testDispatcherWrapsStringInResponse(): void
    {
        $controller = new class {
            public function index(): string { return 'plain-string'; }
        };

        $className = get_class($controller);
        Container::getInstance()->instance($className, $controller);

        $route = new Route('GET', '/string', $className . '@index');
        $result = $this->dispatcher->dispatch($className, 'index', $route);

        $this->assertInstanceOf(Response::class, $result);
        $this->assertEquals('plain-string', $result->getContent());
    }

    public function testDispatcherPassesThroughResponse(): void
    {
        $controller = new class {
            public function index(): Response { return new Response('direct'); }
        };

        $className = get_class($controller);
        Container::getInstance()->instance($className, $controller);

        $route = new Route('GET', '/direct', $className . '@index');
        $result = $this->dispatcher->dispatch($className, 'index', $route);

        $this->assertInstanceOf(Response::class, $result);
        $this->assertEquals('direct', $result->getContent());
    }

    // ================================================================
    // 方法参数默认值
    // ================================================================

    public function testDispatcherResolvesDefaultValues(): void
    {
        $controller = new class {
            public function show(string $format = 'json'): string { return "format={$format}"; }
        };

        $className = get_class($controller);
        Container::getInstance()->instance($className, $controller);

        $route = new Route('GET', '/show', $className . '@show');
        $result = $this->dispatcher->dispatch($className, 'show', $route);

        $this->assertEquals('format=json', $result->getContent());
    }

    // ================================================================
    // 中间件通过容器构建
    // ================================================================

    public function testMiddlewareBuiltViaContainer(): void
    {
        $middleware = new class extends Middleware {
            public function handle(mixed $request, \Closure $next): mixed { return $next($request); }
        };

        $className = get_class($middleware);
        Container::getInstance()->instance($className, $middleware);

        $reflection = new \ReflectionMethod(RouteAction::class, 'buildMiddlewareInstances');
        $reflection->setAccessible(true);

        $instances = $reflection->invoke(null, [[$className, []]]);
        $this->assertCount(1, $instances);
        $this->assertSame($middleware, $instances[0]);
    }

    public function testMiddlewareFallsBackWhenContainerFails(): void
    {
        $reflection = new \ReflectionMethod(RouteAction::class, 'buildMiddlewareInstances');
        $reflection->setAccessible(true);

        $instances = $reflection->invoke(null, [[\Bin\Middleware\CsrfMiddleware::class, []]]);

        $this->assertCount(1, $instances);
        $this->assertInstanceOf(\Bin\Middleware\CsrfMiddleware::class, $instances[0]);
    }

    public function testMiddlewareWithParameters(): void
    {
        $reflection = new \ReflectionMethod(RouteAction::class, 'buildMiddlewareInstances');
        $reflection->setAccessible(true);

        $instances = $reflection->invoke(null, [[\Bin\Middleware\RateLimitMiddleware::class, ['60', '1']]]);

        $this->assertCount(1, $instances);
        $this->assertInstanceOf(\Bin\Middleware\RateLimitMiddleware::class, $instances[0]);
    }
}
