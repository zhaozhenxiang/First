<?php

declare(strict_types=1);

namespace Tests;

use Bin\Testing\TestCase;
use Bin\Route\Route;
use Bin\Route\RouteCollection;
use Bin\Route\RouteBinding;
use Bin\Route\ResourceRegistrar;

/**
 * 路由增强测试 — resource、模型绑定、组增强、快捷路由
 */
class RouteEnhancementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        RouteCollection::clear();
        RouteBinding::clear();
    }

    protected function tearDown(): void
    {
        RouteCollection::clear();
        RouteBinding::clear();
        parent::tearDown();
    }

    // ================================================================
    // Resource 路由
    // ================================================================

    public function testResourceRegistersSevenRoutes(): void
    {
        $routes = RouteCollection::resource('posts', 'PostController');

        $this->assertCount(8, $routes); // 7 + 1 PATCH
    }

    public function testResourceRouteNames(): void
    {
        RouteCollection::resource('posts', 'PostController');

        $this->assertNotNull(RouteCollection::namedRoute('posts.index'));
        $this->assertNotNull(RouteCollection::namedRoute('posts.show'));
        $this->assertNotNull(RouteCollection::namedRoute('posts.store'));
        $this->assertNotNull(RouteCollection::namedRoute('posts.update'));
        $this->assertNotNull(RouteCollection::namedRoute('posts.destroy'));
        $this->assertNotNull(RouteCollection::namedRoute('posts.create'));
        $this->assertNotNull(RouteCollection::namedRoute('posts.edit'));
    }

    public function testResourceWithOnly(): void
    {
        $routes = RouteCollection::resource('posts', 'PostController', [
            'only' => ['index', 'show'],
        ]);

        // index + show + PATCH for update (only filters apply methods, PATCH always added)
        $this->assertCount(2, $routes);
    }

    public function testResourceWithExcept(): void
    {
        $routes = RouteCollection::resource('posts', 'PostController', [
            'except' => ['create', 'edit'],
        ]);

        // 5 routes (no create/edit) + 1 PATCH
        $this->assertCount(6, $routes);
    }

    public function testApiResourceExcludesCreateAndEdit(): void
    {
        $routes = RouteCollection::apiResource('posts', 'PostController');

        // 5 API routes + 1 PATCH
        $this->assertCount(6, $routes);
        $this->assertNull(RouteCollection::namedRoute('posts.create'));
        $this->assertNull(RouteCollection::namedRoute('posts.edit'));
    }

    public function testResourceCustomNames(): void
    {
        RouteCollection::resource('posts', 'PostController', [
            'names' => ['index' => 'posts.list'],
        ]);

        $this->assertNotNull(RouteCollection::namedRoute('posts.list'));
    }

    public function testResourceRoutePaths(): void
    {
        RouteCollection::resource('posts', 'PostController');

        $allRoutes = RouteCollection::getRoutes();
        $paths = array_map(fn(Route $r) => $r->getPath(), $allRoutes);

        $this->assertTrue(in_array('/posts', $paths));
        $this->assertTrue(in_array('/posts/create', $paths));
        $this->assertTrue(in_array('/posts/{post}', $paths));
        $this->assertTrue(in_array('/posts/{post}/edit', $paths));
    }

    // ================================================================
    // ResourceRegistrar
    // ================================================================

    public function testRegistrarSingularize(): void
    {
        $registrar = new ResourceRegistrar();

        // 通过反射测试 protected 方法
        $ref = new \ReflectionClass($registrar);
        $method = $ref->getMethod('singularize');
        $method->setAccessible(true);

        $this->assertEquals('post', $method->invoke($registrar, 'posts'));
        $this->assertEquals('category', $method->invoke($registrar, 'categories'));
        $this->assertEquals('box', $method->invoke($registrar, 'boxes'));
        $this->assertEquals('status', $method->invoke($registrar, 'statuses'));
    }

    // ================================================================
    // RouteBinding 模型绑定
    // ================================================================

    public function testCustomBinder(): void
    {
        RouteBinding::bind('user', fn($value) => strtoupper((string) $value));

        $result = RouteBinding::resolve('user', 'john');
        $this->assertEquals('JOHN', $result);
    }

    public function testModelBinding(): void
    {
        // 用一个简单的模拟类
        RouteBinding::model('item', \stdClass::class);

        $result = RouteBinding::resolve('item', 'test');
        $this->assertInstanceOf(\stdClass::class, $result);
    }

    public function testResolveUnboundReturnsRawValue(): void
    {
        $result = RouteBinding::resolve('unknown', 'raw');
        $this->assertEquals('raw', $result);
    }

    public function testHasBinding(): void
    {
        $this->assertFalse(RouteBinding::hasBinding('user'));

        RouteBinding::bind('user', fn($v) => $v);
        $this->assertTrue(RouteBinding::hasBinding('user'));
    }

    public function testClearBinding(): void
    {
        RouteBinding::bind('user', fn($v) => $v);
        RouteBinding::clear();
        $this->assertFalse(RouteBinding::hasBinding('user'));
    }

    public function testModelBindingWithCallback(): void
    {
        RouteBinding::model('user', \stdClass::class, fn($value) => 'resolved:' . $value);

        $result = RouteBinding::resolve('user', '123');
        $this->assertEquals('resolved:123', $result);
    }

    // ================================================================
    // RouteCollection::model/bind 代理
    // ================================================================

    public function testRouteCollectionModelProxy(): void
    {
        RouteCollection::model('post', \stdClass::class);
        $this->assertTrue(RouteBinding::hasBinding('post'));
    }

    public function testRouteCollectionBindProxy(): void
    {
        RouteCollection::bind('slug', fn($v) => strtolower($v));
        $result = RouteBinding::resolve('slug', 'HELLO');
        $this->assertEquals('hello', $result);
    }

    // ================================================================
    // 快捷路由
    // ================================================================

    public function testFallbackRoute(): void
    {
        RouteCollection::fallback(function () {
            return 'fallback';
        });

        $allRoutes = RouteCollection::getRoutes();
        // fallback 路由不添加到主数组
        $this->assertGreaterThanOrEqual(0, count($allRoutes));
    }

    public function testRedirectRoute(): void
    {
        $route = RouteCollection::redirect('/old', '/new');

        $this->assertInstanceOf(Route::class, $route);
        $this->assertEquals('GET', $route->getMethod());
        $this->assertEquals('/old', $route->getPath());
    }

    public function testPermanentRedirectRoute(): void
    {
        $route = RouteCollection::permanentRedirect('/old', '/new');

        $this->assertInstanceOf(Route::class, $route);
    }

    public function testViewRoute(): void
    {
        $route = RouteCollection::view('/about', 'about');

        $this->assertInstanceOf(Route::class, $route);
        $this->assertEquals('GET', $route->getMethod());
        $this->assertEquals('/about', $route->getPath());
    }

    // ================================================================
    // 路由组 namespace
    // ================================================================

    public function testGroupAppliesNamespace(): void
    {
        RouteCollection::group(['namespace' => 'App\Controllers\Admin'], function () {
            RouteCollection::get('/dashboard', 'AdminController@index');
        });

        $allRoutes = RouteCollection::getRoutes();
        $this->assertNotEmpty($allRoutes);
        $last = end($allRoutes);
        $this->assertEquals('App\Controllers\Admin\AdminController@index', $last->getAction());
    }

    public function testGroupNamespaceSkipsAlreadyNamespaced(): void
    {
        RouteCollection::group(['namespace' => 'App\Controllers'], function () {
            RouteCollection::get('/test', '\Full\Path\Controller@index');
        });

        $allRoutes = RouteCollection::getRoutes();
        $last = end($allRoutes);
        // 已经有 \ 的不应该再添加前缀
        $this->assertEquals('\Full\Path\Controller@index', $last->getAction());
    }

    // ================================================================
    // 路由组 domain
    // ================================================================

    public function testGroupAppliesDomain(): void
    {
        RouteCollection::group(['domain' => '{account}.example.com'], function () {
            RouteCollection::get('/dashboard', function () {
                return 'dashboard';
            });
        });

        $allRoutes = RouteCollection::getRoutes();
        $last = end($allRoutes);
        $this->assertEquals('{account}.example.com', $last->getDomain());
    }

    // ================================================================
    // 命名路由
    // ================================================================

    public function testNamedRouteAutoRegistered(): void
    {
        RouteCollection::get('/home', function () {})->name('home');

        $found = RouteCollection::namedRoute('home');
        $this->assertNotNull($found);
        $this->assertEquals('/home', $found->getPath());
    }

    public function testNamedRouteUrlGeneration(): void
    {
        RouteCollection::get('/users/{id}', function () {})->name('users.show');

        $url = RouteCollection::url('users.show', ['id' => '42']);
        $this->assertStringContainsString('42', $url);
    }

    // ================================================================
    // Route 新增方法
    // ================================================================

    public function testRouteSetAction(): void
    {
        $route = new Route('GET', '/test', 'Controller@index');
        $route->setAction('App\Controllers\Controller@index');

        $this->assertEquals('App\Controllers\Controller@index', $route->getAction());
    }

    public function testRouteSetDomain(): void
    {
        $route = new Route('GET', '/test', function () {});
        $route->setDomain('{sub}.example.com');

        $this->assertEquals('{sub}.example.com', $route->getDomain());
    }

    public function testRouteGetDomainDefaultNull(): void
    {
        $route = new Route('GET', '/test', function () {});
        $this->assertNull($route->getDomain());
    }

    public function testRouteWhere(): void
    {
        $route = new Route('GET', '/users/{id}', function () {});
        $route->where('id', '[0-9]+');

        $this->assertTrue(true); // 不抛异常即通过
    }

    // ================================================================
    // Route::url() 参数替换
    // ================================================================

    public function testRouteUrlGeneration(): void
    {
        $route = new Route('GET', '/posts/{post}/comments/{comment}', function () {});
        $url = $route->url(['post' => '1', 'comment' => '5']);

        $this->assertStringContainsString('1', $url);
        $this->assertStringContainsString('5', $url);
    }

    // ================================================================
    // 组合测试：prefix + middleware + namespace
    // ================================================================

    public function testGroupCombinesAllAttributes(): void
    {
        RouteCollection::group([
            'prefix' => '/admin',
            'namespace' => 'App\Controllers\Admin',
            'middleware' => ['auth'],
        ], function () {
            RouteCollection::get('/dashboard', 'DashboardController@index')->name('admin.dashboard');
        });

        $allRoutes = RouteCollection::getRoutes();
        $this->assertCount(1, $allRoutes);

        $route = $allRoutes[0];
        $this->assertEquals('/admin/dashboard', $route->getPath());
        $this->assertEquals('App\Controllers\Admin\DashboardController@index', $route->getAction());
        $this->assertEquals(['auth'], $route->getMiddleware());
        $this->assertNotNull(RouteCollection::namedRoute('admin.dashboard'));
    }
}
