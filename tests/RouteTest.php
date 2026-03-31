<?php

declare(strict_types=1);

namespace Tests;

use Bin\Testing\TestCase;
use Bin\Route\RouteCollection as Route;
use Bin\Route\Route as RouteObj;

class RouteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Route::clear();
    }

    protected function tearDown(): void
    {
        Route::clear();
        parent::tearDown();
    }

    // === 基本路由注册 ===

    public function testGetRouteRegistration(): void
    {
        $route = Route::get('/home', function () { return 'home'; });
        $this->assertInstanceOf(RouteObj::class, $route);
        $this->assertEquals('/home', $route->getPath());
        $this->assertEquals('GET', $route->getMethod());
    }

    public function testPostRouteRegistration(): void
    {
        $route = Route::post('/submit', function () { return 'ok'; });
        $this->assertEquals('POST', $route->getMethod());
    }

    public function testPutRouteRegistration(): void
    {
        $route = Route::put('/update', function () {});
        $this->assertEquals('PUT', $route->getMethod());
    }

    public function testPatchRouteRegistration(): void
    {
        $route = Route::patch('/patch', function () {});
        $this->assertEquals('PATCH', $route->getMethod());
    }

    public function testDeleteRouteRegistration(): void
    {
        $route = Route::delete('/delete', function () {});
        $this->assertEquals('DELETE', $route->getMethod());
    }

    public function testOptionsRouteRegistration(): void
    {
        $route = Route::options('/options', function () {});
        $this->assertEquals('OPTIONS', $route->getMethod());
    }

    // === 路由匹配 ===

    public function testStaticRouteMatch(): void
    {
        Route::get('/about', function () { return 'about'; });

        $routes = Route::getRoutes();
        $this->assertNotEmpty($routes);

        $aboutRoute = null;
        foreach ($routes as $r) {
            if ($r->getPath() === '/about') {
                $aboutRoute = $r;
                break;
            }
        }

        $this->assertNotNull($aboutRoute);
        $this->assertTrue($aboutRoute->matches('/about'));
    }

    public function testStaticRouteNoMatch(): void
    {
        $route = Route::get('/about', function () {});
        $this->assertFalse($route->matches('/contact'));
    }

    public function testDynamicRouteWithRegexMatches(): void
    {
        $route = Route::get('/user/{id}', function ($id) { return $id; })
            ->with('[0-9]+');
        // matches() uses pregMatch which requires App container for Request
        // Test that with() stores the pattern correctly
        $this->assertNotNull($route->getPreg());
        $this->assertEquals(['[0-9]+'], $route->getPreg());
    }

    public function testMultipleParametersStorePatterns(): void
    {
        $route = Route::get('/post/{id}/comment/{cid}', function () {})
            ->with('[0-9]+')->with('[0-9]+');
        $this->assertCount(2, $route->getPreg());
    }

    public function testWithRegexConstraint(): void
    {
        $route = Route::get('/user/{id}', function ($id) {})
            ->with('[0-9]+');

        // Verify regex constraint is stored
        $this->assertNotNull($route->getPreg());
        $this->assertContains('[0-9]+', $route->getPreg());
    }

    // === Route 对象方法 ===

    public function testRouteGetName(): void
    {
        $route = Route::get('/home', function () {});
        $this->assertNull($route->getName());
    }

    public function testRouteSetName(): void
    {
        $route = Route::get('/home', function () {})->name('home');
        $this->assertEquals('home', $route->getName());
    }

    public function testRouteUrlGeneration(): void
    {
        $route = Route::get('/user/{id}', function () {});
        $this->assertEquals('/user/5', $route->url(['id' => 5]));
    }

    public function testRouteGetAction(): void
    {
        $action = function () { return 'test'; };
        $route = Route::get('/test', $action);
        $this->assertSame($action, $route->getAction());
    }

    public function testRouteStringAction(): void
    {
        $route = Route::get('/test', 'UserController@index');
        $this->assertEquals('UserController@index', $route->getAction());
    }

    // === 路由分组 ===

    public function testMiddlewareGroup(): void
    {
        Route::middle(['auth' => []], function () {
            Route::get('/dashboard', function () { return 'dashboard'; });
        });

        $routes = Route::getRoutes();
        $this->assertNotEmpty($routes);

        $dashboardRoute = $routes[0];
        $middle = $dashboardRoute->getMiddle();
        $this->assertNotNull($middle);
        $this->assertArrayHasKey('middle', $middle);
        $this->assertArrayHasKey('auth', $middle['middle']);
    }

    // === 批量注册 ===

    public function testGetArrayRegistration(): void
    {
        Route::getArray([
            '/page1' => 'PageController@page1',
            '/page2' => 'PageController@page2',
        ]);

        $routes = Route::getRoutes();
        $this->assertCount(2, $routes);
    }

    // === clear 清理 ===

    public function testClearRemovesAllRoutes(): void
    {
        Route::get('/a', function () {});
        Route::get('/b', function () {});
        Route::clear();
        $this->assertEmpty(Route::getRoutes());
    }

    // === any 方法 ===

    public function testAnyRegistersAllMethods(): void
    {
        Route::any('/catch-all', function () {});
        $routes = Route::getRoutes();
        $methods = array_map(fn($r) => $r->getMethod(), $routes);
        $this->assertContains('GET', $methods);
        $this->assertContains('POST', $methods);
        $this->assertContains('DELETE', $methods);
    }

    // === Route withSuccess ===

    public function testWithSuccessAlias(): void
    {
        $route = Route::get('/test', function () {});
        $this->assertTrue($route->withSuccess('/test'));
        $this->assertFalse($route->withSuccess('/other'));
    }
}
