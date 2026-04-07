<?php

declare(strict_types=1);

namespace Tests;

use Bin\Testing\TestCase;
use Bin\Middleware\Middleware;
use Bin\Middleware\Pipeline;
use Bin\Middleware\MiddlewareStack;
use Bin\Middleware\MiddlewareNameResolver;
use Bin\Route\RouteCollection as Route;
use Bin\Route\Route as RouteObj;

/**
 * 中间件管道系统测试 — 单元层 + 集成层 + 边界层
 *
 * 覆盖：Pipeline 洋葱模型、名称解析、栈管理、路由集成、Terminate 生命周期
 */
class MiddlewarePipelineTest extends TestCase
{
    protected function tearDown(): void
    {
        Route::clear();
        MiddlewareStack::reset();
        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();
        Route::clear();
    }

    // ================================================================
    // Pipeline — 洋葱模型基础
    // ================================================================

    public function testEmptyPipelinePassesThrough(): void
    {
        $result = (new Pipeline())
            ->send('request')
            ->through([])
            ->then(fn($r) => 'final:' . $r);

        $this->assertEquals('final:request', $result);
    }

    public function testSingleMiddlewarePassesThrough(): void
    {
        $middleware = new class extends Middleware {
            public function handle(mixed $request, \Closure $next): mixed
            {
                return $next('wrapped:' . $request);
            }
        };

        $result = (new Pipeline())
            ->send('request')
            ->through([$middleware])
            ->then(fn($r) => 'final:' . $r);

        $this->assertEquals('final:wrapped:request', $result);
    }

    public function testMultipleMiddlewareExecutesInOrder(): void
    {
        $middleware1 = new class extends Middleware {
            public function handle(mixed $request, \Closure $next): mixed
            {
                return $next($request . ':A');
            }
        };

        $middleware2 = new class extends Middleware {
            public function handle(mixed $request, \Closure $next): mixed
            {
                return $next($request . ':B');
            }
        };

        $result = (new Pipeline())
            ->send('REQ')
            ->through([$middleware1, $middleware2])
            ->then(fn($r) => $r);

        $this->assertEquals('REQ:A:B', $result);
    }

    public function testOnionModelPostProcessing(): void
    {
        $middlewareA = new class extends Middleware {
            public function handle(mixed $request, \Closure $next): mixed
            {
                $response = $next('before:A|' . $request);
                return $response . '|after:A';
            }
        };

        $middlewareB = new class extends Middleware {
            public function handle(mixed $request, \Closure $next): mixed
            {
                $response = $next($request . '|before:B');
                return $response . '|after:B';
            }
        };

        $result = (new Pipeline())
            ->send('REQ')
            ->through([$middlewareA, $middlewareB])
            ->then(fn($r) => 'TARGET[' . $r . ']');

        $this->assertEquals('TARGET[before:A|REQ|before:B]|after:B|after:A', $result);
    }

    public function testMiddlewareCanShortCircuit(): void
    {
        $middleware = new class extends Middleware {
            public function handle(mixed $request, \Closure $next): mixed
            {
                return 'blocked:' . $request;
            }
        };

        $called = false;
        $result = (new Pipeline())
            ->send('request')
            ->through([$middleware])
            ->then(function ($r) use (&$called) {
                $called = true;
                return 'should not reach';
            });

        $this->assertEquals('blocked:request', $result);
        $this->assertFalse($called);
    }

    public function testClosureMiddleware(): void
    {
        $result = (new Pipeline())
            ->send('request')
            ->through([
                fn($request, $next) => $next('closure:' . $request),
            ])
            ->then(fn($r) => 'final:' . $r);

        $this->assertEquals('final:closure:request', $result);
    }

    public function testStringClassMiddleware(): void
    {
        $result = (new Pipeline())
            ->send('GET-request')
            ->through([new \Bin\Middleware\CsrfMiddleware()])
            ->then(fn($r) => 'passed');

        $this->assertEquals('passed', $result);
    }

    public function testThenReturn(): void
    {
        $result = (new Pipeline())
            ->send('request')
            ->through([])
            ->thenReturn();

        $this->assertEquals('request', $result);
    }

    public function testPipelineWithResolver(): void
    {
        $resolved = false;
        $middleware = new class extends Middleware {
            public function handle(mixed $request, \Closure $next): mixed
            {
                return $next('resolved:' . $request);
            }
        };

        $result = (new Pipeline())
            ->send('request')
            ->resolver(function (string $class) use ($middleware, &$resolved) {
                $resolved = true;
                return $middleware;
            })
            ->through([get_class($middleware)])
            ->then(fn($r) => $r);

        $this->assertEquals('resolved:request', $result);
        $this->assertTrue($resolved);
    }

    // ================================================================
    // Pipeline — 进阶场景
    // ================================================================

    public function testMultiLayerShortCircuitStopsAllLaterLayers(): void
    {
        // 第一层放行，第二层短路，第三层不应执行
        $layer3Called = false;

        $mw1 = new class extends Middleware {
            public function handle(mixed $request, \Closure $next): mixed
            {
                return $next('passed-1:' . $request);
            }
        };

        $mw2 = new class extends Middleware {
            public function handle(mixed $request, \Closure $next): mixed
            {
                return 'stopped-at-2';
            }
        };

        $mw3 = new class ($layer3Called) extends Middleware {
            private bool $flag;
            public function __construct(bool &$flag) { $this->flag = &$flag; }
            public function handle(mixed $request, \Closure $next): mixed
            {
                $this->flag = true;
                return $next($request);
            }
        };

        // 注意：这里 $layer3Called 是值传递，需要换种方式
        $mw3 = new class extends Middleware {
            public static bool $called = false;
            public function handle(mixed $request, \Closure $next): mixed
            {
                self::$called = true;
                return $next($request);
            }
        };

        $result = (new Pipeline())
            ->send('REQ')
            ->through([$mw1, $mw2, $mw3])
            ->then(fn($r) => 'target');

        $this->assertEquals('stopped-at-2', $result);
        $this->assertFalse($mw3::$called);
    }

    public function testPipelineViaCustomMethod(): void
    {
        $middleware = new class extends Middleware {
            public function customHandle(mixed $request, \Closure $next): mixed
            {
                return $next('custom:' . $request);
            }
        };

        $result = (new Pipeline())
            ->send('request')
            ->via('customHandle')
            ->through([$middleware])
            ->then(fn($r) => $r);

        $this->assertEquals('custom:request', $result);
    }

    public function testPipelineFiveLayersDeep(): void
    {
        $layers = [];
        for ($i = 1; $i <= 5; $i++) {
            $layers[] = new class ($i) extends Middleware {
                public function __construct(private int $n) {}
                public function handle(mixed $request, \Closure $next): mixed
                {
                    return $next($request . ':' . $this->n);
                }
            };
        }

        $result = (new Pipeline())
            ->send('IN')
            ->through($layers)
            ->then(fn($r) => $r);

        $this->assertEquals('IN:1:2:3:4:5', $result);
    }

    public function testPipelineMixedClosuresAndInstances(): void
    {
        $middleware = new class extends Middleware {
            public function handle(mixed $request, \Closure $next): mixed
            {
                return $next($request . ':instance');
            }
        };

        $result = (new Pipeline())
            ->send('REQ')
            ->through([
                fn($r, $n) => $n($r . ':closure'),
                $middleware,
            ])
            ->then(fn($r) => $r);

        $this->assertEquals('REQ:closure:instance', $result);
    }

    public function testPipelinePreservesPassableReference(): void
    {
        $obj = new \stdClass();
        $obj->value = 'original';

        $middleware = new class extends Middleware {
            public function handle(mixed $request, \Closure $next): mixed
            {
                $request->value = 'modified';
                return $next($request);
            }
        };

        (new Pipeline())
            ->send($obj)
            ->through([$middleware])
            ->then(function ($r) {
                $this->assertEquals('modified', $r->value);
            });
    }

    // ================================================================
    // MiddlewareNameResolver
    // ================================================================

    public function testResolveSimpleName(): void
    {
        [$class, $params] = MiddlewareNameResolver::resolve('auth', [
            'auth' => \Bin\Middleware\AuthMiddleware::class,
        ]);

        $this->assertEquals(\Bin\Middleware\AuthMiddleware::class, $class);
        $this->assertEquals([], $params);
    }

    public function testResolveNameWithParameters(): void
    {
        [$class, $params] = MiddlewareNameResolver::resolve('throttle:60,1', [
            'throttle' => \Bin\Middleware\RateLimitMiddleware::class,
        ]);

        $this->assertEquals(\Bin\Middleware\RateLimitMiddleware::class, $class);
        $this->assertEquals(['60', '1'], $params);
    }

    public function testResolveUnknownNameReturnsAsIs(): void
    {
        [$class, $params] = MiddlewareNameResolver::resolve('SomeMiddleware');

        $this->assertEquals('SomeMiddleware', $class);
        $this->assertEquals([], $params);
    }

    public function testParseMiddlewareStringNoParams(): void
    {
        [$name, $params] = MiddlewareNameResolver::parseMiddlewareString('auth');

        $this->assertEquals('auth', $name);
        $this->assertEquals([], $params);
    }

    public function testParseMiddlewareStringWithParams(): void
    {
        [$name, $params] = MiddlewareNameResolver::parseMiddlewareString('throttle:60,1');

        $this->assertEquals('throttle', $name);
        $this->assertEquals(['60', '1'], $params);
    }

    public function testParseMiddlewareStringWithColonInValue(): void
    {
        // "throttle:60:1" — 只在第一个 : 分割
        [$name, $params] = MiddlewareNameResolver::parseMiddlewareString('throttle:60:1');

        $this->assertEquals('throttle', $name);
        // 第二个 : 不分割，整个 "60:1" 作为一个参数中的逗号分隔
        // 实际上 "60:1" 中没有逗号，所以是一个参数 "60:1"
        $this->assertEquals(['60:1'], $params);
    }

    public function testParseMiddlewareStringSingleParam(): void
    {
        [$name, $params] = MiddlewareNameResolver::parseMiddlewareString('auth:admin');

        $this->assertEquals('auth', $name);
        $this->assertEquals(['admin'], $params);
    }

    public function testResolveAll(): void
    {
        $results = MiddlewareNameResolver::resolveAll(
            ['auth', 'throttle:60,1'],
            [
                'auth' => \Bin\Middleware\AuthMiddleware::class,
                'throttle' => \Bin\Middleware\RateLimitMiddleware::class,
            ]
        );

        $this->assertCount(2, $results);
        $this->assertEquals(\Bin\Middleware\AuthMiddleware::class, $results[0][0]);
        $this->assertEquals(\Bin\Middleware\RateLimitMiddleware::class, $results[1][0]);
        $this->assertEquals(['60', '1'], $results[1][1]);
    }

    public function testResolveAllEmpty(): void
    {
        $results = MiddlewareNameResolver::resolveAll([]);
        $this->assertEquals([], $results);
    }

    // ================================================================
    // MiddlewareStack — 管理和收集
    // ================================================================

    public function testMiddlewareStackCollectsRouteMiddleware(): void
    {
        $stack = MiddlewareStack::loadFromConfig([
            'global' => ['GlobalMiddleware'],
            'groups' => [
                'web' => ['WebMiddleware'],
            ],
            'aliases' => [
                'auth' => 'AuthMiddleware',
            ],
        ]);

        $result = $stack->collectRouteMiddleware(
            ['auth'],
            ['web'],
            []
        );

        $this->assertContains('GlobalMiddleware', $result);
        $this->assertContains('WebMiddleware', $result);
        $this->assertContains('auth', $result);
    }

    public function testMiddlewareStackExcludesMiddleware(): void
    {
        $stack = MiddlewareStack::loadFromConfig([
            'global' => ['GlobalA', 'GlobalB'],
            'aliases' => [],
        ]);

        $result = $stack->collectRouteMiddleware([], [], ['GlobalB']);

        $this->assertContains('GlobalA', $result);
        $this->assertNotContains('GlobalB', $result);
    }

    public function testMiddlewareStackExcludesByAlias(): void
    {
        $stack = MiddlewareStack::loadFromConfig([
            'global' => ['AuthMiddleware'],
            'aliases' => [
                'auth' => 'AuthMiddleware',
            ],
        ]);

        // 用别名排除
        $result = $stack->collectRouteMiddleware([], [], ['auth']);

        $this->assertNotContains('AuthMiddleware', $result);
    }

    public function testMiddlewareStackExcludesMultiple(): void
    {
        $stack = MiddlewareStack::loadFromConfig([
            'global' => ['A', 'B', 'C'],
            'aliases' => [],
        ]);

        $result = $stack->collectRouteMiddleware([], [], ['B', 'C']);

        $this->assertEquals(['A'], $result);
    }

    public function testMiddlewareStackResolvesAlias(): void
    {
        $stack = MiddlewareStack::loadFromConfig([
            'aliases' => [
                'auth' => 'App\Middleware\AuthMiddleware',
            ],
        ]);

        $this->assertEquals('App\Middleware\AuthMiddleware', $stack->resolveAlias('auth'));
        $this->assertEquals('UnknownClass', $stack->resolveAlias('UnknownClass'));
    }

    public function testMiddlewareStackPrioritySorting(): void
    {
        $stack = MiddlewareStack::loadFromConfig([
            'global' => ['LowPriority', 'HighPriority', 'MediumPriority'],
            'priority' => [
                'HighPriority' => 100,
                'MediumPriority' => 50,
                'LowPriority' => 10,
            ],
        ]);

        $result = $stack->getGlobals();

        $this->assertEquals('HighPriority', $result[0]);
        $this->assertEquals('MediumPriority', $result[1]);
        $this->assertEquals('LowPriority', $result[2]);
    }

    public function testMiddlewareStackPrioritySortingByAlias(): void
    {
        $stack = MiddlewareStack::loadFromConfig([
            'global' => ['low', 'high'],
            'aliases' => [
                'low' => 'LowClass',
                'high' => 'HighClass',
            ],
            'priority' => [
                'high' => 100,
                'low' => 10,
            ],
        ]);

        $result = $stack->getGlobals();

        $this->assertEquals('high', $result[0]);
        $this->assertEquals('low', $result[1]);
    }

    public function testMiddlewareStackNoPriorityPreservesOrder(): void
    {
        $stack = MiddlewareStack::loadFromConfig([
            'global' => ['First', 'Second', 'Third'],
        ]);

        $result = $stack->getGlobals();

        $this->assertEquals(['First', 'Second', 'Third'], $result);
    }

    public function testMiddlewareStackAddGlobal(): void
    {
        $stack = MiddlewareStack::getInstance();
        $stack->addGlobal('NewGlobal');

        $this->assertContains('NewGlobal', $stack->getGlobals());
    }

    public function testMiddlewareStackAddToGroup(): void
    {
        $stack = MiddlewareStack::getInstance();
        $stack->addToGroup('api', 'NewApiMiddleware');

        $this->assertContains('NewApiMiddleware', $stack->getGroup('api'));
    }

    public function testMiddlewareStackNoDuplicateGlobal(): void
    {
        $stack = MiddlewareStack::getInstance();
        $stack->addGlobal('SameMiddleware');
        $stack->addGlobal('SameMiddleware');

        $count = array_count_values($stack->getGlobals());
        $this->assertEquals(1, $count['SameMiddleware']);
    }

    public function testMiddlewareStackNoDuplicateInGroup(): void
    {
        $stack = MiddlewareStack::getInstance();
        $stack->addToGroup('web', 'SameMw');
        $stack->addToGroup('web', 'SameMw');

        $count = array_count_values($stack->getGroup('web'));
        $this->assertEquals(1, $count['SameMw']);
    }

    public function testMiddlewareStackDeduplication(): void
    {
        $stack = MiddlewareStack::loadFromConfig([
            'global' => ['AuthMiddleware'],
            'aliases' => [],
        ]);

        $result = $stack->collectRouteMiddleware(['AuthMiddleware'], [], []);
        $count = array_count_values($result);
        $this->assertEquals(1, $count['AuthMiddleware']);
    }

    public function testMiddlewareStackGetGroupReturnsEmptyForUnknown(): void
    {
        $stack = MiddlewareStack::getInstance();

        $this->assertEquals([], $stack->getGroup('nonexistent'));
    }

    public function testMiddlewareStackGetAliases(): void
    {
        $stack = MiddlewareStack::loadFromConfig([
            'aliases' => [
                'auth' => 'AuthMW',
                'csrf' => 'CsrfMW',
            ],
        ]);

        $aliases = $stack->getAliases();
        $this->assertCount(2, $aliases);
        $this->assertEquals('AuthMW', $aliases['auth']);
        $this->assertEquals('CsrfMW', $aliases['csrf']);
    }

    public function testMiddlewareStackAliasRegistration(): void
    {
        $stack = MiddlewareStack::getInstance();
        $stack->alias('custom', 'CustomMiddleware');

        $this->assertEquals('CustomMiddleware', $stack->resolveAlias('custom'));
    }

    public function testMiddlewareStackLoadFromConfigReturnsInstance(): void
    {
        $stack = MiddlewareStack::loadFromConfig([
            'global' => ['TestMiddleware'],
        ]);

        $this->assertInstanceOf(MiddlewareStack::class, $stack);
        $this->assertSame($stack, MiddlewareStack::getInstance());
    }

    public function testMiddlewareStackReset(): void
    {
        $stack = MiddlewareStack::getInstance();
        $stack->addGlobal('TestGlobal');

        MiddlewareStack::reset();

        $newStack = MiddlewareStack::getInstance();
        $this->assertNotContains('TestGlobal', $newStack->getGlobals());
    }

    public function testMiddlewareStackCollectWithMultipleGroups(): void
    {
        $stack = MiddlewareStack::loadFromConfig([
            'groups' => [
                'web' => ['WebMW'],
                'api' => ['ApiMW'],
            ],
        ]);

        $result = $stack->collectRouteMiddleware([], ['web', 'api'], []);

        $this->assertContains('WebMW', $result);
        $this->assertContains('ApiMW', $result);
    }

    public function testMiddlewareStackCollectEmpty(): void
    {
        $stack = MiddlewareStack::getInstance();

        $result = $stack->collectRouteMiddleware();

        $this->assertEquals([], $result);
    }

    public function testMiddlewareStackCollectExcludesFromGroups(): void
    {
        $stack = MiddlewareStack::loadFromConfig([
            'groups' => [
                'web' => ['WebMW', 'CsrfMW'],
            ],
        ]);

        $result = $stack->collectRouteMiddleware([], ['web'], ['CsrfMW']);

        $this->assertContains('WebMW', $result);
        $this->assertNotContains('CsrfMW', $result);
    }

    // ================================================================
    // Route ↔ Middleware 集成
    // ================================================================

    public function testRouteMiddlewareSingleString(): void
    {
        $route = Route::get('/test', function () {});
        $result = $route->middleware('auth');

        $this->assertSame($route, $result);
        $this->assertEquals(['auth'], $route->getMiddleware());
    }

    public function testRouteMiddlewareArray(): void
    {
        $route = Route::get('/test', function () {});
        $route->middleware(['auth', 'throttle:60,1']);

        $this->assertEquals(['auth', 'throttle:60,1'], $route->getMiddleware());
    }

    public function testRouteMiddlewareChained(): void
    {
        $route = Route::get('/test', function () {});
        $route->middleware('auth')->middleware('csrf');

        $this->assertEquals(['auth', 'csrf'], $route->getMiddleware());
    }

    public function testRouteMiddlewareNoDuplicate(): void
    {
        $route = Route::get('/test', function () {});
        $route->middleware('auth')->middleware('auth');

        $this->assertEquals(['auth'], $route->getMiddleware());
    }

    public function testRouteWithoutMiddleware(): void
    {
        $route = Route::get('/test', function () {});
        $result = $route->withoutMiddleware('csrf');

        $this->assertSame($route, $result);
        $this->assertEquals(['csrf'], $route->getExcludedMiddleware());
    }

    public function testRouteWithoutMiddlewareArray(): void
    {
        $route = Route::get('/test', function () {});
        $route->withoutMiddleware(['csrf', 'throttle']);

        $this->assertEquals(['csrf', 'throttle'], $route->getExcludedMiddleware());
    }

    public function testRouteWithoutMiddlewareNoDuplicate(): void
    {
        $route = Route::get('/test', function () {});
        $route->withoutMiddleware('csrf')->withoutMiddleware('csrf');

        $this->assertEquals(['csrf'], $route->getExcludedMiddleware());
    }

    public function testRouteMiddlewareGroup(): void
    {
        $route = Route::get('/test', function () {});
        $result = $route->middlewareGroup('web');

        $this->assertSame($route, $result);
        $this->assertEquals(['web'], $route->getMiddlewareGroups());
    }

    public function testRouteMiddlewareGroupArray(): void
    {
        $route = Route::get('/test', function () {});
        $route->middlewareGroup(['web', 'api']);

        $this->assertEquals(['web', 'api'], $route->getMiddlewareGroups());
    }

    public function testRouteMiddlewareGroupNoDuplicate(): void
    {
        $route = Route::get('/test', function () {});
        $route->middlewareGroup('web')->middlewareGroup('web');

        $this->assertEquals(['web'], $route->getMiddlewareGroups());
    }

    public function testRouteUpdatePath(): void
    {
        $route = Route::get('/original', function () {});
        $this->assertEquals('/original', $route->getPath());

        $route->updatePath('/prefix/original');
        $this->assertEquals('/prefix/original', $route->getPath());
    }

    // ================================================================
    // RouteCollection::group() — Bug 修复验证
    // ================================================================

    public function testGroupAppliesPrefix(): void
    {
        Route::group(['prefix' => 'admin'], function () {
            Route::get('/dashboard', function () {});
        });

        $routes = Route::getRoutes();
        $this->assertCount(1, $routes);
        $this->assertEquals('/admin/dashboard', $routes[0]->getPath());
    }

    public function testGroupAppliesNestedPrefix(): void
    {
        Route::group(['prefix' => 'admin'], function () {
            Route::group(['prefix' => 'users'], function () {
                Route::get('/list', function () {});
            });
        });

        $routes = Route::getRoutes();
        $this->assertCount(1, $routes);
        $this->assertEquals('/admin/users/list', $routes[0]->getPath());
    }

    public function testGroupAppliesMiddleware(): void
    {
        Route::group(['middleware' => ['auth', 'csrf']], function () {
            Route::get('/dashboard', function () {});
        });

        $routes = Route::getRoutes();
        $this->assertEquals(['auth', 'csrf'], $routes[0]->getMiddleware());
    }

    public function testGroupAppliesMiddlewareGroup(): void
    {
        Route::group(['middleware_group' => 'web'], function () {
            Route::get('/page', function () {});
        });

        $routes = Route::getRoutes();
        $this->assertEquals(['web'], $routes[0]->getMiddlewareGroups());
    }

    public function testGroupAppliesAllAttributes(): void
    {
        Route::group([
            'prefix' => 'api',
            'middleware' => ['auth'],
            'middleware_group' => 'api',
        ], function () {
            Route::get('/users', function () {});
        });

        $routes = Route::getRoutes();
        $this->assertEquals('/api/users', $routes[0]->getPath());
        $this->assertEquals(['auth'], $routes[0]->getMiddleware());
        $this->assertEquals(['api'], $routes[0]->getMiddlewareGroups());
    }

    public function testGroupWithNoAttributes(): void
    {
        // 空属性不应报错
        Route::group([], function () {
            Route::get('/bare', function () {});
        });

        $routes = Route::getRoutes();
        $this->assertCount(1, $routes);
        $this->assertEquals('/bare', $routes[0]->getPath());
    }

    public function testGroupOnlyCalledOnce(): void
    {
        // 验证 group 的 callback 只被调用一次（旧 bug 修复）
        $callCount = 0;
        Route::group(['prefix' => 'api'], function () use (&$callCount) {
            $callCount++;
            Route::get('/test', function () {});
        });

        $this->assertEquals(1, $callCount);
        $routes = Route::getRoutes();
        $this->assertCount(1, $routes);
    }

    public function testGroupMiddlewareStringConvertedToArray(): void
    {
        // middleware 可以是单个字符串
        Route::group(['middleware' => 'auth'], function () {
            Route::get('/test', function () {});
        });

        $routes = Route::getRoutes();
        $this->assertEquals(['auth'], $routes[0]->getMiddleware());
    }

    public function testGroupPrefixTrimsSlashes(): void
    {
        Route::group(['prefix' => '/admin/'], function () {
            Route::get('/dashboard', function () {});
        });

        $routes = Route::getRoutes();
        $this->assertEquals('/admin/dashboard', $routes[0]->getPath());
    }

    public function testGroupUpdatesStaticRouteIndex(): void
    {
        Route::group(['prefix' => 'api'], function () {
            Route::get('/users', function () { return 'users'; });
        });

        // 静态路由索引应该更新了路径
        $routes = Route::getRoutes();
        $this->assertEquals('/api/users', $routes[0]->getPath());
    }

    // ================================================================
    // Terminate 生命周期
    // ================================================================

    public function testTerminateCalledAfterResponse(): void
    {
        $log = [];

        $middleware = new class ($log) extends Middleware {
            private array $log;
            public function __construct(array &$log) { $this->log = &$log; }
            public function handle(mixed $request, \Closure $next): mixed
            {
                $this->log[] = 'handle';
                return $next($request);
            }
            public function terminate(mixed $request, mixed $response): void
            {
                $this->log[] = 'terminate';
            }
            public function getLog(): array { return $this->log; }
        };

        $result = (new Pipeline())
            ->send('request')
            ->through([$middleware])
            ->then(fn($r) => 'response');

        $this->assertEquals('response', $result);
        // terminate 需要手动调用（Pipeline 只处理 handle 阶段）
        $middleware->terminate('request', $result);
        $this->assertEquals(['handle', 'terminate'], $middleware->getLog());
    }

    public function testTerminateWithMultipleMiddleware(): void
    {
        $log = [];

        $mw1 = new class ($log) extends Middleware {
            private array $log;
            public function __construct(array &$log) { $this->log = &$log; }
            public function handle(mixed $request, \Closure $next): mixed
            {
                $this->log[] = 'mw1:handle';
                return $next($request);
            }
            public function terminate(mixed $request, mixed $response): void
            {
                $this->log[] = 'mw1:terminate';
            }
            public function getLog(): array { return $this->log; }
        };

        $mw2 = new class ($log) extends Middleware {
            private array $log;
            public function __construct(array &$log) { $this->log = &$log; }
            public function handle(mixed $request, \Closure $next): mixed
            {
                $this->log[] = 'mw2:handle';
                return $next($request);
            }
            public function terminate(mixed $request, mixed $response): void
            {
                $this->log[] = 'mw2:terminate';
            }
            public function getLog(): array { return $this->log; }
        };

        $result = (new Pipeline())
            ->send('request')
            ->through([$mw1, $mw2])
            ->then(fn($r) => 'response');

        // 手动调用 terminate（逆序，模拟 Laravel 行为）
        $mw2->terminate('request', $result);
        $mw1->terminate('request', $result);

        $this->assertEquals([
            'mw1:handle',
            'mw2:handle',
            'mw2:terminate',
            'mw1:terminate',
        ], $mw1->getLog());
    }

    // ================================================================
    // Config 结构验证
    // ================================================================

    public function testMiddlewareConfigFileStructure(): void
    {
        $config = require BASE_PATH . '/config/middleware.php';

        $this->assertArrayHasKey('global', $config);
        $this->assertArrayHasKey('groups', $config);
        $this->assertArrayHasKey('aliases', $config);
        $this->assertArrayHasKey('priority', $config);
    }

    public function testMiddlewareConfigHasDefaultAliases(): void
    {
        $config = require BASE_PATH . '/config/middleware.php';

        $this->assertArrayHasKey('auth', $config['aliases']);
        $this->assertArrayHasKey('guest', $config['aliases']);
        $this->assertArrayHasKey('csrf', $config['aliases']);
        $this->assertArrayHasKey('throttle', $config['aliases']);
    }

    public function testMiddlewareConfigHasDefaultGroups(): void
    {
        $config = require BASE_PATH . '/config/middleware.php';

        $this->assertArrayHasKey('web', $config['groups']);
        $this->assertArrayHasKey('api', $config['groups']);
    }

    public function testMiddlewareConfigAliasesPointToRealClasses(): void
    {
        $config = require BASE_PATH . '/config/middleware.php';

        foreach ($config['aliases'] as $name => $class) {
            $this->assertTrue(
                class_exists($class),
                "Alias '{$name}' points to non-existent class '{$class}'"
            );
        }
    }

    // ================================================================
    // 边界情况
    // ================================================================

    public function testPipelineWithEmptyArrayMiddleware(): void
    {
        $result = (new Pipeline())
            ->send('request')
            ->through([])
            ->then(fn($r) => 'ok');

        $this->assertEquals('ok', $result);
    }

    public function testMiddlewareSetOptionsIsAccessible(): void
    {
        $middleware = new class extends Middleware {
            public function getOptions(): array { return $this->options; }
        };

        $middleware->setOptions(['a', 'b', 'c']);
        $this->assertEquals(['a', 'b', 'c'], $middleware->getOptions());
    }

    public function testMiddlewareSetOptionsEmptyArray(): void
    {
        $middleware = new class extends Middleware {
            public function getOptions(): array { return $this->options; }
        };

        $middleware->setOptions([]);
        $this->assertEquals([], $middleware->getOptions());
    }

    public function testPipelineSendAcceptsMixedTypes(): void
    {
        // null
        $result = (new Pipeline())->send(null)->through([])->then(fn($r) => $r);
        $this->assertNull($result);

        // integer
        $result = (new Pipeline())->send(42)->through([])->then(fn($r) => $r);
        $this->assertEquals(42, $result);

        // array
        $data = ['key' => 'value'];
        $result = (new Pipeline())->send($data)->through([])->then(fn($r) => $r);
        $this->assertEquals($data, $result);
    }

    public function testRouteLegacyMiddleApiStillWorks(): void
    {
        // 旧 API: Route::middle(['middle' => ['auth' => []]], callback)
        $route = Route::get('/legacy', function () {});
        $route->middle(['middle' => ['auth' => []]]);

        // 旧 API getMiddle() 返回非 null
        $this->assertNotNull($route->getMiddle());
        // 新 API getMiddleware() 也应该有值
        $this->assertContains('auth', $route->getMiddleware());
    }

    public function testMiddlewareStackCollectWithExcludedNonexistentMiddleware(): void
    {
        $stack = MiddlewareStack::loadFromConfig([
            'global' => ['A', 'B'],
        ]);

        // 排除一个不存在的中间件不应报错
        $result = $stack->collectRouteMiddleware([], [], ['NonExistent']);

        $this->assertEquals(['A', 'B'], $result);
    }
}
