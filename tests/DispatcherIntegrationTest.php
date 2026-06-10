<?php

declare(strict_types=1);

namespace Tests;

use Bin\App\App;
use Bin\Auth\AuthManager;
use Bin\Container\Attributes\RouteParameter;
use Bin\Container\Container;
use Bin\Exception\AuthorizationException;
use Bin\Exception\ValidationException;
use Bin\Foundation\HttpKernel;
use Bin\Middleware\AuthMiddleware;
use Bin\Middleware\Middleware;
use Bin\Middleware\MiddlewareStack;
use Bin\Request\Request;
use Bin\Response\Response;
use Bin\Routing\ControllerDispatcher;
use Bin\Route\Route;
use Bin\Route\RouteAction;
use Bin\Route\RouteCollection;
use Bin\Testing\TestCase;
use Bin\Validation\FormRequest;

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
    private ?string $originalProvider = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dispatcher = new ControllerDispatcher();
        \Bin\Route\RouteCollection::clear();
        $this->originalProvider = AuthManager::getProvider();
        AuthManager::setProvider(DispatcherIntegrationAuthUser::class);
        DispatcherIntegrationAuthUser::reset();
        session_manager()->clear();
        AuthManager::resetUser();

        $property = new \ReflectionProperty(RouteAction::class, 'dispatcher');
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
        MiddlewareStack::loadFromConfig([]);
        if ($this->originalProvider !== null) {
            AuthManager::setProvider($this->originalProvider);
        }
        session_manager()->clear();
        AuthManager::resetUser();
        $property = new \ReflectionProperty(RouteAction::class, 'dispatcher');
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

    public function testDispatcherClosureRouteParameterAttributeReadsDifferentUrlKey(): void
    {
        $request = \Bin\Request\Request::capture();
        $request->setUrlParam(['post' => '42']);
        Container::getInstance()->instance(\Bin\Request\Request::class, $request);

        $closure = fn (
            #[RouteParameter('post')]
            string $postId
        ): string => "post={$postId}";

        $route = new Route('GET', '/posts/{post}', $closure);
        $result = $this->dispatcher->dispatchClosure($closure, $route);

        $this->assertEquals('post=42', $result->getContent());
    }

    public function testDispatcherControllerRouteParameterAttributeReadsDifferentUrlKey(): void
    {
        $request = \Bin\Request\Request::capture();
        $request->setUrlParam(['post' => '84']);
        Container::getInstance()->instance(\Bin\Request\Request::class, $request);

        $controller = new class {
            public function show(
                #[RouteParameter('post')]
                string $postId
            ): string {
                return "post={$postId}";
            }
        };

        $className = get_class($controller);
        Container::getInstance()->instance($className, $controller);

        $route = new Route('GET', '/posts/{post}', $className . '@show');
        $result = $this->dispatcher->dispatch($className, 'show', $route);

        $this->assertEquals('post=84', $result->getContent());
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

    public function testRouteActionDispatchInstallsRequestUserResolverAndResetsAuthCache(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/private';

        $sessionUser = AuthManager::loginUsingId(7);
        $this->assertNotNull($sessionUser);
        AuthManager::resetUser();

        AuthManager::login(new DispatcherIntegrationAuthUser(404, 'Stale Cached User'));
        session_manager()->set(AuthManager::getSessionKey(), 7);

        MiddlewareStack::loadFromConfig([
            'aliases' => [
                'auth' => AuthMiddleware::class,
            ],
        ]);

        $resolvedUserId = null;
        $resolverWasInstalled = false;

        \Bin\Route\RouteCollection::get('/private', function (Request $request) use (&$resolvedUserId, &$resolverWasInstalled): string {
            $resolverWasInstalled = is_callable($request->getUserResolver());
            $resolvedUserId = $request->user()?->id;

            return 'private';
        })->middleware('auth');

        $response = RouteAction::dispatch(Request::capture());

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('private', $response->getContent());
        $this->assertTrue($resolverWasInstalled);
        $this->assertSame(7, $resolvedUserId);
    }

    public function testProtectedRouteControllerReceivesValidatedInputAndCurrentUser(): void
    {
        $originalProvider = AuthManager::getProvider();
        $originalServer = $_SERVER;
        $originalPost = $_POST;

        try {
            $this->configureIdentityInputControllerRoute(7, [
                'name' => 'Ada',
                'role' => 'admin',
                'ignored' => 'not-returned',
            ]);

            $response = RouteAction::dispatch(Request::capture());
            $payload = json_decode($response->getContent(), true);

            $this->assertSame(['name' => 'Ada', 'role' => 'admin'], $payload['validated']);
            $this->assertSame(7, $payload['user_id']);
            $this->assertTrue($payload['middleware_seen']);
            $this->assertSame([
                'middleware:before',
                'form:authorize',
                'form:rules',
                'form:passedValidation',
                'controller:store',
                'middleware:after',
            ], IdentityInputRouteProbe::events());
            $this->assertSame(1, RouteIdentityInputRequest::$authorizeCalls);
            $this->assertSame(1, RouteIdentityInputRequest::$rulesCalls);
            $this->assertSame(1, RouteIdentityInputRequest::$passedValidationCalls);
            $this->assertSame(1, RouteIdentityInputController::$storeCalls);
        } finally {
            $this->cleanupIdentityInputControllerRoute($originalProvider, $originalServer, $originalPost);
        }
    }

    public function testProtectedRouteControllerRejectsUnauthorizedFormRequestUser(): void
    {
        $originalProvider = AuthManager::getProvider();
        $originalServer = $_SERVER;
        $originalPost = $_POST;

        try {
            $this->configureIdentityInputControllerRoute(8, [
                'name' => 'Ada',
                'role' => 'admin',
            ]);

            $this->assertThrows(AuthorizationException::class, function (): void {
                RouteAction::dispatch(Request::capture());
            });

            $this->assertSame(['middleware:before', 'form:authorize'], IdentityInputRouteProbe::events());
            $this->assertSame(1, RouteIdentityInputRequest::$authorizeCalls);
            $this->assertSame(0, RouteIdentityInputRequest::$rulesCalls);
            $this->assertSame(0, RouteIdentityInputRequest::$passedValidationCalls);
            $this->assertSame(0, RouteIdentityInputController::$storeCalls);
        } finally {
            $this->cleanupIdentityInputControllerRoute($originalProvider, $originalServer, $originalPost);
        }
    }

    public function testProtectedRouteControllerRejectsInvalidFormRequestInputBeforeController(): void
    {
        $originalProvider = AuthManager::getProvider();
        $originalServer = $_SERVER;
        $originalPost = $_POST;

        try {
            $this->configureIdentityInputControllerRoute(7, [
                'name' => 'Ada',
            ]);

            $this->assertThrows(ValidationException::class, function (): void {
                RouteAction::dispatch(Request::capture());
            });

            $this->assertSame(['middleware:before', 'form:authorize', 'form:rules'], IdentityInputRouteProbe::events());
            $this->assertSame(1, RouteIdentityInputRequest::$authorizeCalls);
            $this->assertSame(1, RouteIdentityInputRequest::$rulesCalls);
            $this->assertSame(0, RouteIdentityInputRequest::$passedValidationCalls);
            $this->assertSame(0, RouteIdentityInputController::$storeCalls);
        } finally {
            $this->cleanupIdentityInputControllerRoute($originalProvider, $originalServer, $originalPost);
        }
    }

    private function configureIdentityInputControllerRoute(int $userId, array $post): void
    {
        IdentityInputRouteProbe::reset();
        RouteIdentityInputRequest::resetFlags();
        RouteIdentityInputController::reset();
        IdentityInputRouteUser::reset();

        AuthManager::setProvider(IdentityInputRouteUser::class);
        AuthManager::loginUsingId($userId);

        MiddlewareStack::reset();
        MiddlewareStack::loadFromConfig([
            'global' => [],
            'groups' => [],
            'aliases' => [
                'auth' => AuthMiddleware::class,
                'identity.probe' => IdentityInputRouteMiddleware::class,
            ],
            'priority' => [
                'identity.probe' => 20,
                IdentityInputRouteMiddleware::class => 20,
                'auth' => 10,
                AuthMiddleware::class => 10,
            ],
        ]);

        $_SERVER = array_merge($_SERVER, [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/identity/profile',
            'SERVER_NAME' => 'localhost',
        ]);
        $_POST = $post;

        RouteCollection::post('/identity/profile', RouteIdentityInputController::class . '@store')
            ->middleware(['identity.probe', 'auth']);
    }

    private function cleanupIdentityInputControllerRoute(?string $originalProvider, array $originalServer, array $originalPost): void
    {
        $_SERVER = $originalServer;
        $_POST = $originalPost;
        RouteCollection::clear();
        MiddlewareStack::reset();
        AuthManager::setProvider($originalProvider ?? 'App\\Model\\User');
        AuthManager::resetUser();
        session_manager()->clear();
        IdentityInputRouteProbe::reset();
        RouteIdentityInputRequest::resetFlags();
        RouteIdentityInputController::reset();
        IdentityInputRouteUser::clear();
    }

    public function testInjectedFormRequestUsesBoundRequestInputRouteParamsAndUserResolver(): void
    {
        $user = (object) ['id' => 9, 'name' => 'Ada'];
        $request = new Request(
            query: ['from_query' => 'yes'],
            post: ['name' => 'Ada'],
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/posts/42'],
            cookies: ['theme' => 'dark']
        );
        $request->merge(['slug' => 'hello-world']);
        $request->setUrlParam(['post' => '42']);
        $request->setUserResolver(fn (): ?object => $user);
        App::getInstance()->instance(Request::class, $request);

        $closure = function (DispatcherIdentityInputRequest $form): array {
            return [
                'name' => $form->validated()['name'],
                'slug' => $form->validated()['slug'],
                'post' => $form->route('post'),
                'user_id' => $form->user()?->id,
                'theme' => $form->cookie('theme'),
            ];
        };

        $route = new Route('POST', '/posts/{post}', $closure);

        $result = $this->dispatcher->dispatchClosure($closure, $route);
        $payload = json_decode($result->getContent(), true);

        $this->assertSame('Ada', $payload['name']);
        $this->assertSame('hello-world', $payload['slug']);
        $this->assertSame('42', $payload['post']);
        $this->assertSame(9, $payload['user_id']);
        $this->assertSame('dark', $payload['theme']);
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

    public function testDispatcherWrapsJsonResourceReturnValueAsJsonResponse(): void
    {
        $controller = new class {
            public function show(): \Bin\Resource\JsonResource
            {
                return new class(['id' => 7, 'name' => 'Ada']) extends \Bin\Resource\JsonResource {
                    public function toArray(): array
                    {
                        return [
                            'id' => $this->id(),
                            'name' => $this->resource['name'],
                        ];
                    }
                };
            }
        };

        $className = get_class($controller);
        Container::getInstance()->instance($className, $controller);

        $route = new Route('GET', '/resource', $className . '@show');
        $result = $this->dispatcher->dispatch($className, 'show', $route);

        $this->assertInstanceOf(Response::class, $result);
        $this->assertEquals('application/json', $result->getHeader('Content-Type'));
        $this->assertEquals(['id' => 7, 'name' => 'Ada'], json_decode($result->getContent(), true));
    }

    public function testDispatcherWrapsResourceCollectionClosureReturnValueAsJsonResponse(): void
    {
        $resourceClass = get_class(new class([]) extends \Bin\Resource\JsonResource {
            public function toArray(): array
            {
                return [
                    'id' => $this->id(),
                    'name' => $this->resource['name'],
                ];
            }
        });

        $closure = function () use ($resourceClass): \Bin\Resource\ResourceCollection {
            return \Bin\Resource\ResourceCollection::make([
                ['id' => 1, 'name' => 'Ada'],
                ['id' => 2, 'name' => 'Grace'],
            ], $resourceClass);
        };

        $route = new Route('GET', '/resources', $closure);
        $result = $this->dispatcher->dispatchClosure($closure, $route);

        $this->assertInstanceOf(Response::class, $result);
        $this->assertEquals('application/json', $result->getHeader('Content-Type'));
        $this->assertEquals([
            'data' => [
                ['id' => 1, 'name' => 'Ada'],
                ['id' => 2, 'name' => 'Grace'],
            ],
        ], json_decode($result->getContent(), true));
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

        $instances = $reflection->invoke(null, [[$className, []]]);
        $this->assertCount(1, $instances);
        $this->assertSame($middleware, $instances[0]);
    }

    public function testMiddlewareFallsBackWhenContainerFails(): void
    {
        $reflection = new \ReflectionMethod(RouteAction::class, 'buildMiddlewareInstances');

        $instances = $reflection->invoke(null, [[\Bin\Middleware\CsrfMiddleware::class, []]]);

        $this->assertCount(1, $instances);
        $this->assertInstanceOf(\Bin\Middleware\CsrfMiddleware::class, $instances[0]);
    }

    public function testMiddlewareWithParameters(): void
    {
        $reflection = new \ReflectionMethod(RouteAction::class, 'buildMiddlewareInstances');

        $instances = $reflection->invoke(null, [[\Bin\Middleware\RateLimitMiddleware::class, ['60', '1']]]);

        $this->assertCount(1, $instances);
        $this->assertInstanceOf(\Bin\Middleware\RateLimitMiddleware::class, $instances[0]);
    }
}

class DispatcherIdentityInputRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->id === 9 && $this->route('post') === '42';
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string',
            'slug' => 'required|string',
        ];
    }
}

class RouteIdentityInputRequest extends FormRequest
{
    public static int $authorizeCalls = 0;
    public static int $rulesCalls = 0;
    public static int $passedValidationCalls = 0;

    public static function resetFlags(): void
    {
        self::$authorizeCalls = 0;
        self::$rulesCalls = 0;
        self::$passedValidationCalls = 0;
    }

    public function authorize(): bool
    {
        self::$authorizeCalls++;
        IdentityInputRouteProbe::record('form:authorize');

        return $this->user()?->id === 7;
    }

    public function rules(): array
    {
        self::$rulesCalls++;
        IdentityInputRouteProbe::record('form:rules');

        return [
            'name' => 'required|string',
            'role' => 'required|string',
        ];
    }

    protected function passedValidation(): void
    {
        self::$passedValidationCalls++;
        IdentityInputRouteProbe::record('form:passedValidation');
    }
}

class RouteIdentityInputController
{
    public static int $storeCalls = 0;

    public static function reset(): void
    {
        self::$storeCalls = 0;
    }

    public function store(RouteIdentityInputRequest $request): array
    {
        self::$storeCalls++;
        IdentityInputRouteProbe::record('controller:store');

        return [
            'validated' => $request->validated(),
            'user_id' => $request->user()?->id,
            'middleware_seen' => IdentityInputRouteProbe::has('middleware:before'),
        ];
    }
}

class IdentityInputRouteMiddleware extends Middleware
{
    public function handle(mixed $request, \Closure $next): mixed
    {
        IdentityInputRouteProbe::record('middleware:before');
        $response = $next($request);
        IdentityInputRouteProbe::record('middleware:after');

        return $response;
    }
}

class IdentityInputRouteProbe
{
    /** @var array<int, string> */
    private static array $events = [];

    public static function reset(): void
    {
        self::$events = [];
    }

    public static function record(string $event): void
    {
        self::$events[] = $event;
    }

    public static function has(string $event): bool
    {
        return in_array($event, self::$events, true);
    }

    /**
     * @return array<int, string>
     */
    public static function events(): array
    {
        return self::$events;
    }
}

class IdentityInputRouteUser
{
    public function __construct(
        public int $id,
        public string $name,
    ) {
    }

    /** @var array<int, self> */
    private static array $users = [];

    public static function reset(): void
    {
        self::$users = [
            7 => new self(7, 'Route User'),
            8 => new self(8, 'Unauthorized Route User'),
        ];
    }

    public static function clear(): void
    {
        self::$users = [];
    }

    public static function find(mixed $id): ?self
    {
        return self::$users[(int) $id] ?? null;
    }
}

class DispatcherIntegrationAuthUser
{
    /** @var array<int, self> */
    private static array $users = [];

    public function __construct(
        public int $id,
        public string $name,
    ) {
        self::$users[$id] = $this;
    }

    public static function reset(): void
    {
        self::$users = [];
        new self(7, 'Session User');
    }

    public static function find(int $id): ?self
    {
        return self::$users[$id] ?? null;
    }
}
