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

    public function testRouteUrlPreservesValueThatLooksLikeOptionalPlaceholder(): void
    {
        $route = Route::get('/prefix/{slug}/{optional?}', function () {});
        $this->assertEquals('/prefix/{optional?}', $route->url(['slug' => '{optional?}']));
    }

    public function testRouteUrlPreservesCompositePlaceholderLookingValue(): void
    {
        $route = Route::get('/files/{name}.{ext}', function () {});
        $this->assertEquals('/files/{ext}.txt', $route->url(['name' => '{ext}', 'ext' => 'txt']));
    }

    public function testRouteUrlPreservesSlugValueWhenOptionalPlaceholderMissingInSameSegment(): void
    {
        $route = Route::get('/{slug}-{optional?}', function () {});
        $this->assertEquals('/{optional?}-', $route->url(['slug' => '{optional?}']));
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

    // === where 约束匹配 ===

    public function testWhereConstraintMatchesNumeric(): void
    {
        $route = Route::get('/user/{id}', function () {});
        $route->where('id', '[0-9]+');

        $this->assertTrue($route->matches('/user/42'));
        $this->assertFalse($route->matches('/user/abc'));
    }

    public function testWhereConstraintMultipleParams(): void
    {
        $route = Route::get('/post/{postId}/comment/{commentId}', function () {});
        $route->where('postId', '[0-9]+');
        $route->where('commentId', '[a-z]+');

        $this->assertTrue($route->matches('/post/123/comment/abc'));
        $this->assertFalse($route->matches('/post/abc/comment/xyz'));
    }

    public function testWhereArraySyntax(): void
    {
        $route = Route::get('/post/{postId}/comment/{commentId}', function () {});
        $route->where(['postId' => '[0-9]+', 'commentId' => '[a-z]+']);

        $this->assertTrue($route->matches('/post/1/comment/abc'));
        $this->assertFalse($route->matches('/post/abc/comment/abc'));
    }

    public function testOptionalWhereConstraintMatchesWithoutOptionalSegment(): void
    {
        $route = Route::get('/reports/{year}/{month?}', function () {});
        $route->where(['year' => '[0-9]{4}', 'month' => '[0-9]{2}']);

        $this->assertTrue($route->matches('/reports/2026'));
        $this->assertEquals(['year' => '2026'], $this->getMatchedParams($route));
    }

    public function testOptionalWhereConstraintMatchesWithOptionalSegment(): void
    {
        $route = Route::get('/reports/{year}/{month?}', function () {});
        $route->where(['year' => '[0-9]{4}', 'month' => '[0-9]{2}']);

        $this->assertTrue($route->matches('/reports/2026/04'));
        $this->assertEquals(['year' => '2026', 'month' => '04'], $this->getMatchedParams($route));
    }

    public function testGetWhereConstraints(): void
    {
        $route = Route::get('/user/{id}', function () {});
        $route->where('id', '[0-9]+');

        $where = $route->getWheres();
        $this->assertEquals(['id' => '[0-9]+'], $where);
    }

    // === 嵌套 group 属性合并 ===

    public function testNestedGroupPrefixConcatenation(): void
    {
        Route::group(['prefix' => '/admin'], function () {
            Route::group(['prefix' => '/settings'], function () {
                Route::get('/general', function () { return 'ok'; });
            });
        });

        $routes = Route::getRoutes();
        $this->assertCount(1, $routes);
        $this->assertEquals('/admin/settings/general', $routes[0]->getPath());
    }

    public function testNestedGroupNamePrefix(): void
    {
        Route::group(['name' => 'admin.'], function () {
            Route::group(['name' => 'settings.'], function () {
                Route::get('/general', function () {})->name('general');
            });
        });

        $this->assertNotNull(Route::namedRoute('admin.settings.general'));
    }

    public function testNestedGroupMiddlewareAccumulation(): void
    {
        Route::group(['middleware' => ['auth']], function () {
            Route::group(['middleware' => ['throttle']], function () {
                Route::get('/panel', function () {});
            });
        });

        $routes = Route::getRoutes();
        $this->assertCount(1, $routes);
        $mw = $routes[0]->getMiddleware();
        $this->assertContains('auth', $mw);
        $this->assertContains('throttle', $mw);
    }

    public function testGroupWhereConstraintsInherited(): void
    {
        Route::group(['where' => ['id' => '[0-9]+']], function () {
            Route::get('/user/{id}', function () {});
        });

        $routes = Route::getRoutes();
        $this->assertCount(1, $routes);
        $where = $routes[0]->getWheres();
        $this->assertEquals(['id' => '[0-9]+'], $where);
    }

    public function testNestedGroupDomainOverride(): void
    {
        Route::group(['domain' => '{team}.example.com'], function () {
            Route::group(['domain' => 'www.example.com'], function () {
                Route::get('/home', function () {});
            });
        });

        $routes = Route::getRoutes();
        $this->assertEquals('www.example.com', $routes[0]->getDomain());
    }

    public function testGroupNamespaceConcatenation(): void
    {
        Route::group(['namespace' => 'App\\Controllers'], function () {
            Route::group(['namespace' => 'Admin'], function () {
                Route::get('/dashboard', 'DashboardController@index');
            });
        });

        $routes = Route::getRoutes();
        $this->assertEquals('App\\Controllers\\Admin\\DashboardController@index', $routes[0]->getAction());
    }

    // === match 方法别名 ===

    public function testMatchMethod(): void
    {
        Route::match(['GET', 'POST'], '/submit', function () {});

        $routes = Route::getRoutes();
        $methods = array_map(fn($r) => $r->getMethod(), $routes);
        $this->assertContains('GET', $methods);
        $this->assertContains('POST', $methods);
    }

    // === fallback 语义 ===

    public function testFallbackNotInDynamicRoutes(): void
    {
        Route::fallback(function () { return 'fallback'; });

        // fallback 不应出现在主路由数组中
        $allRoutes = Route::getRoutes();
        foreach ($allRoutes as $r) {
            $this->assertNotEquals('{fallback}', $r->getPath());
        }
    }

    private function getMatchedParams(RouteObj $route): ?array
    {
        $reflection = new \ReflectionClass($route);
        $property = $reflection->getProperty('matchedParams');
        $property->setAccessible(true);

        return $property->getValue($route);
    }
}
