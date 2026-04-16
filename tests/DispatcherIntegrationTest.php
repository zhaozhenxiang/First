<?php

declare(strict_types=1);

namespace Tests;

use Bin\App\App;
use Bin\Container\Container;
use Bin\Foundation\HttpKernel;
use Bin\Middleware\Middleware;
use Bin\Request\Request;
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
        $app = App::getInstance();
        $app->forget(ControllerDispatcher::class);
        $app->singleton(ControllerDispatcher::class, ControllerDispatcher::class);
        $app->forget(Request::class);
        $app->singleton(Request::class, Request::class);
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

    public function testRouteActionDispatchUsesProvidedRequestInstance(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/users/42';

        $resolvedRequest = null;

        \Bin\Route\RouteCollection::get('/users/{id}', function (Request $request, string $id) use (&$resolvedRequest): string {
            $resolvedRequest = $request;
            return "id={$id}";
        })
            ->where('id', '[^/]+');

        $staleRequest = new Request([], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/stale',
        ], []);
        App::getInstance()->instance(Request::class, $staleRequest);

        $request = Request::capture();

        $response = RouteAction::dispatch($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals('id=42', $response->getContent());
        $this->assertSame($request, $resolvedRequest);
        $this->assertNotSame($staleRequest, $resolvedRequest);
        $this->assertSame($request, App::getInstance()->make(Request::class));
    }

    public function testHttpKernelHandleUsesCapturedRequestThroughDispatchPipeline(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/users/42';

        $resolvedRequest = null;

        \Bin\Route\RouteCollection::get('/users/{id}', function (Request $request, string $id) use (&$resolvedRequest): string {
            $resolvedRequest = $request;
            return "id={$id}";
        })
            ->where('id', '[^/]+');

        $staleRequest = new Request([], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/stale',
        ], []);
        App::getInstance()->instance(Request::class, $staleRequest);

        $kernel = new HttpKernel(App::getInstance());
        $kernel->setBootstrappers([]);

        $response = $kernel->handle();

        $property = new \ReflectionProperty(HttpKernel::class, 'currentRequest');
        $property->setAccessible(true);
        $currentRequest = $property->getValue($kernel);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals('id=42', $response->getContent());
        $this->assertInstanceOf(Request::class, $currentRequest);
        $this->assertSame($currentRequest, $resolvedRequest);
        $this->assertNotSame($staleRequest, $currentRequest);
        $this->assertSame($currentRequest, App::getInstance()->make(Request::class));
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

    public function testTearDownRestoresDefaultControllerDispatcherSingletonBinding(): void
    {
        $app = App::getInstance();

        $this->tearDown();

        $this->assertTrue($app->bound(ControllerDispatcher::class));
        $this->assertSame(
            $app->make(ControllerDispatcher::class),
            $app->make(ControllerDispatcher::class)
        );
    }

    public function testTearDownRestoresDefaultRequestSingletonBinding(): void
    {
        $app = App::getInstance();
        $custom = new Request([], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/custom',
        ], []);
        $app->instance(Request::class, $custom);

        $this->tearDown();

        $this->assertTrue($app->bound(Request::class));
        $this->assertNotSame($custom, $app->make(Request::class));
        $this->assertSame(
            $app->make(Request::class),
            $app->make(Request::class)
        );
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
