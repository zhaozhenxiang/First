# M2-B Identity and Input Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make validation, `FormRequest`, session-backed auth, request-scoped current user access, auth middleware, and exception rendering work as one request chain.

**Architecture:** Add a request-scoped user resolver to `Request`, install it at route dispatch entry, and make auth middleware consume the request user path. Build `FormRequest` instances from the already-bound request so validated input, route params, cookies, server state, merged input, and the user resolver survive controller injection. Keep failures flowing through `ValidationException`, `AuthenticationException`, `AuthorizationException`, and `ExceptionHandler`.

**Tech Stack:** PHP 8.3+, custom Bin runtime, `App`/`Container`, `Request`, `RouteAction`, `ControllerDispatcher`, `MiddlewareStack`/`Pipeline`, `ValidationManager`, existing `php test` runner.

---

## Constraints

- Do not expand token/API auth, OAuth, remember-me, password reset, policy/gate matrices, or ORM behavior in this stage.
- Do not add a new auth manager/driver/facade layer.
- Use the existing `SessionManager`, `AuthManager`, `ValidationManager`, `FormRequest`, and `ExceptionHandler` boundaries.
- Before implementation edits, use GitNexus/code-review graph impact tools if they are available in that session. If MCP resources are still unavailable, state that and continue with local context.
- Preserve existing worktrees. Do not delete `.worktrees/m2a-web-state-foundation` or this plan worktree.

## File Structure

- Modify: `bin/Request/Request.php`
  Responsibility: expose server access, request-scoped user resolver, and a runtime-context copy method used by `FormRequest`.
- Modify: `bin/Route/RouteAction.php`
  Responsibility: bind the active request and install the auth user resolver at the start of route dispatch.
- Modify: `bin/Middleware/AuthMiddleware.php`
  Responsibility: make `auth` and `guest` decisions from `$request->user()` when a `Request` is available.
- Modify: `bin/Validation/FormRequest.php`
  Responsibility: create injected form requests from the active base request.
- Modify: `bin/Routing/ControllerDispatcher.php`
  Responsibility: pass the active request into `FormRequest` resolution instead of rebuilding from superglobals.
- Modify: `bin/Validation/ValidationManager.php`
  Responsibility: expose a named throwing validation entry point.
- Modify: `bin/Func/helpers/validation.php`
  Responsibility: route `validate()` through the named throwing entry point.
- Modify: `bin/Exception/ValidationException.php`
  Responsibility: document and preserve string-or-array validation error shapes.
- Modify: `bin/Exception/ExceptionHandler.php`
  Responsibility: render JSON not only for XHR, but also for requests whose active `Request` expects JSON.
- Test: `tests/RequestTest.php`
  Responsibility: cover server access and request-scoped user resolver.
- Test: `tests/AuthTest.php`
  Responsibility: cover session-backed user restore through request user access.
- Test: `tests/MiddlewareTest.php`
  Responsibility: cover auth middleware consuming the request-scoped user path.
- Test: `tests/DispatcherIntegrationTest.php`
  Responsibility: cover `FormRequest` injection from active request and the protected route input/identity chain.
- Test: `tests/ValidationTest.php`
  Responsibility: cover the throwing validation entry point.
- Test: `tests/ExceptionHandlerTest.php`
  Responsibility: cover JSON exits for validation, unauthenticated, and unauthorized failures.

---

### Task 1: Request-Scoped Current User

**Files:**
- Modify: `bin/Request/Request.php`
- Modify: `bin/Route/RouteAction.php`
- Modify: `bin/Middleware/AuthMiddleware.php`
- Test: `tests/RequestTest.php`
- Test: `tests/AuthTest.php`
- Test: `tests/MiddlewareTest.php`

- [ ] **Step 1: Write the failing request/user tests**

Add these methods to `tests/RequestTest.php` after the input-source tests:

```php
public function testServerAccessorReturnsSingleValueAndAllValues(): void
{
    $request = $this->makeRequest(server: [
        'REQUEST_METHOD' => 'POST',
        'REQUEST_URI' => '/profile',
        'HTTP_ACCEPT' => 'application/json',
    ]);

    $this->assertSame('POST', $request->server('REQUEST_METHOD'));
    $this->assertSame('/profile', $request->server('REQUEST_URI'));
    $this->assertSame('fallback', $request->server('MISSING_KEY', 'fallback'));
    $this->assertSame('application/json', $request->server()['HTTP_ACCEPT']);
}

public function testUserResolverReturnsRequestScopedUser(): void
{
    $request = $this->makeRequest();
    $user = (object) ['id' => 77, 'name' => 'Request User'];

    $returned = $request->setUserResolver(fn (): ?object => $user);

    $this->assertSame($request, $returned);
    $this->assertSame($user, $request->user());
    $this->assertIsCallable($request->getUserResolver());
}
```

Add `use Bin\Request\Request;` to `tests/AuthTest.php`, then add:

```php
public function testRequestUserRestoresSessionBackedAuthAfterCacheReset(): void
{
    $user = AuthManager::loginUsingId(1);

    $this->assertNotNull($user);
    AuthManager::resetUser();

    $request = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/profile'], []);
    $request->setUserResolver(fn (): ?object => AuthManager::user());

    $resolved = $request->user();

    $this->assertNotNull($resolved);
    $this->assertEquals(1, $resolved->id);
}

public function testRequestUserIsNullAfterLogoutClearsSession(): void
{
    AuthManager::loginUsingId(1);
    AuthManager::logout();

    $request = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/profile'], []);
    $request->setUserResolver(fn (): ?object => AuthManager::user());

    $this->assertNull($request->user());
    $this->assertFalse(AuthManager::check());
}
```

Add these imports to `tests/MiddlewareTest.php`:

```php
use Bin\Exception\AuthenticationException;
use Bin\Middleware\AuthMiddleware;
```

Add these methods to `tests/MiddlewareTest.php` after the session middleware test:

```php
public function testAuthMiddlewareAllowsRequestScopedUser(): void
{
    $request = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/private'], []);
    $request->setUserResolver(fn (): ?object => (object) ['id' => 5]);

    $middleware = new AuthMiddleware();

    $result = $middleware->handle($request, fn (Request $request): string => 'ok');

    $this->assertSame('ok', $result);
}

public function testAuthMiddlewareThrowsWhenRequestScopedUserMissing(): void
{
    $request = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/private'], []);
    $request->setUserResolver(fn (): ?object => null);

    $middleware = new AuthMiddleware();

    $this->assertThrows(AuthenticationException::class, function () use ($middleware, $request): void {
        $middleware->handle($request, fn (Request $request): string => 'ok');
    });
}
```

- [ ] **Step 2: Run the focused tests to verify they fail**

Run:

```bash
php test tests/RequestTest.php tests/AuthTest.php tests/MiddlewareTest.php
```

Expected: FAIL with these red signals:

```text
Call to undefined method Bin\Request\Request::server()
Call to undefined method Bin\Request\Request::setUserResolver()
Bin\Exception\AuthenticationException
```

- [ ] **Step 3: Implement request-scoped user access**

In `bin/Request/Request.php`, replace the existing `use UnitEnum;` import with:

```php
use Bin\Auth\AuthManager;
use Closure;
use UnitEnum;
```

Add this property near the other request state properties:

```php
/** @var Closure|null Resolver for the current authenticated user in this request scope. */
protected ?Closure $userResolver = null;
```

Add this method near the other input-source accessors:

```php
public function server(?string $key = null, mixed $default = null): mixed
{
    if ($key === null) {
        return $this->server;
    }

    return data_get($this->server, $key, $default);
}
```

Add these methods before the URL/path section:

```php
public function setUserResolver(?Closure $resolver): static
{
    $this->userResolver = $resolver;

    return $this;
}

public function getUserResolver(): ?Closure
{
    return $this->userResolver;
}

public function user(): ?object
{
    if ($this->userResolver !== null) {
        return ($this->userResolver)();
    }

    return AuthManager::user();
}

public function copyRuntimeContextTo(Request $target): void
{
    $target->routeParams = $this->routeParams;
    $target->mergedInput = $this->mergedInput;
    $target->jsonPayload = $this->jsonPayload;
    $target->userResolver = $this->userResolver;
}
```

- [ ] **Step 4: Install the resolver at route dispatch entry**

In `bin/Route/RouteAction.php`, add this import:

```php
use Bin\Auth\AuthManager;
```

Replace the first line in `RouteAction::dispatch(Request $request)`:

```php
App::getInstance()->instance(Request::class, $request);
```

with:

```php
AuthManager::resetUser();
$request->setUserResolver(fn (): ?object => AuthManager::user());
App::getInstance()->instance(Request::class, $request);
```

- [ ] **Step 5: Make auth middleware consume request-scoped user**

In `bin/Middleware/AuthMiddleware.php`, add this import:

```php
use Bin\Request\Request;
```

Replace `AuthMiddleware::handle()` with:

```php
public function handle(mixed $request, \Closure $next): mixed
{
    $user = $request instanceof Request ? $request->user() : AuthManager::user();

    if ($user === null) {
        throw new AuthenticationException();
    }

    return $next($request);
}
```

Replace `GuestMiddleware::handle()` with:

```php
public function handle(mixed $request, \Closure $next): mixed
{
    $user = $request instanceof Request ? $request->user() : AuthManager::user();

    if ($user !== null) {
        $redirectUrl = $this->options[0] ?? '/';
        return redirect($redirectUrl);
    }

    return $next($request);
}
```

- [ ] **Step 6: Run the focused tests to verify they pass**

Run:

```bash
php test tests/RequestTest.php tests/AuthTest.php tests/MiddlewareTest.php
```

Expected: PASS for all three test files. Existing PHP 8.5 deprecation warnings may still appear.

- [ ] **Step 7: Commit Task 1**

```bash
git add bin/Request/Request.php bin/Route/RouteAction.php bin/Middleware/AuthMiddleware.php tests/RequestTest.php tests/AuthTest.php tests/MiddlewareTest.php
git commit -m "feat: add request scoped auth user resolution"
```

---

### Task 2: FormRequest From Active Request

**Files:**
- Modify: `bin/Validation/FormRequest.php`
- Modify: `bin/Routing/ControllerDispatcher.php`
- Test: `tests/DispatcherIntegrationTest.php`

- [ ] **Step 1: Write the failing FormRequest injection test**

Add these imports to `tests/DispatcherIntegrationTest.php`:

```php
use Bin\Validation\FormRequest;
```

Add this method inside `DispatcherIntegrationTest`:

```php
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
```

Add this helper class at the bottom of `tests/DispatcherIntegrationTest.php`, after the `DispatcherIntegrationTest` class:

```php
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
```

- [ ] **Step 2: Run the focused test to verify it fails**

Run:

```bash
php test tests/DispatcherIntegrationTest.php
```

Expected: FAIL with one of these red signals:

```text
Call to undefined method Tests\DispatcherIdentityInputRequest::user()
```

or:

```text
Bin\Exception\AuthorizationException
```

or:

```text
Bin\Exception\ValidationException
```

- [ ] **Step 3: Add FormRequest creation from the active request**

In `bin/Validation/FormRequest.php`, add this method after `setErrorBag()`:

```php
public static function fromBaseRequest(Request $request): static
{
    /** @var static $formRequest */
    $formRequest = new static(
        $request->query(),
        $request->post(),
        $request->server(),
        $request->cookie()
    );

    $request->copyRuntimeContextTo($formRequest);

    return $formRequest;
}
```

- [ ] **Step 4: Pass the active request into FormRequest resolution**

In `bin/Routing/ControllerDispatcher.php`, change the `buildParameterMap()` FormRequest branch from:

```php
if (class_exists($typeName) && is_subclass_of($typeName, FormRequest::class)) {
    $parameters[$name] = $this->resolveFormRequest($typeName);
    continue;
}
```

to:

```php
if (class_exists($typeName) && is_subclass_of($typeName, FormRequest::class)) {
    $parameters[$name] = $this->resolveFormRequest($typeName, $request);
    continue;
}
```

Replace `resolveFormRequest()` with:

```php
protected function resolveFormRequest(string $className, Request $request): FormRequest
{
    /** @var FormRequest $formRequest */
    $formRequest = $className::fromBaseRequest($request);
    $formRequest->validateResolved();

    return $formRequest;
}
```

- [ ] **Step 5: Run the focused test to verify it passes**

Run:

```bash
php test tests/DispatcherIntegrationTest.php
```

Expected: PASS for `tests/DispatcherIntegrationTest.php`.

- [ ] **Step 6: Commit Task 2**

```bash
git add bin/Validation/FormRequest.php bin/Routing/ControllerDispatcher.php tests/DispatcherIntegrationTest.php
git commit -m "feat: inject form requests from active request"
```

---

### Task 3: Validation Entry and JSON Failure Exit

**Files:**
- Modify: `bin/Validation/ValidationManager.php`
- Modify: `bin/Func/helpers/validation.php`
- Modify: `bin/Exception/ValidationException.php`
- Modify: `bin/Exception/ExceptionHandler.php`
- Test: `tests/ValidationTest.php`
- Test: `tests/ExceptionHandlerTest.php`

- [ ] **Step 1: Write the failing validation and JSON exit tests**

Add this method to `tests/ValidationTest.php`:

```php
public function testValidateOrFailThrowsValidationExceptionWithMessageBagShape(): void
{
    $validator = ValidationManager::make(
        ['email' => 'not-an-email'],
        ['email' => 'required|email']
    );

    try {
        $validator->validateOrFail();
        $this->fail('Expected ValidationException was not thrown');
    } catch (ValidationException $e) {
        $errors = $e->getErrors();

        $this->assertEquals(422, $e->getCode());
        $this->assertArrayHasKey('email', $errors);
        $this->assertIsArray($errors['email']);
        $this->assertNotSame('', $errors['email'][0] ?? '');
    }
}
```

Add `use Bin\Request\Request;` to `tests/ExceptionHandlerTest.php`, then add:

```php
public function testValidationExceptionJsonResponseForAcceptHeader(): void
{
    $app = App::getInstance();
    $app->instance(Request::class, new Request(
        query: [],
        post: [],
        server: ['HTTP_ACCEPT' => 'application/json'],
        cookies: []
    ));

    try {
        $handler = new ExceptionHandler(false);
        $response = $handler->render(new ValidationException([
            'email' => ['Invalid email'],
        ]));

        $payload = json_decode($response->getContent(), true);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeader('Content-Type'));
        $this->assertSame(422, $payload['error']['status']);
        $this->assertSame(['email' => ['Invalid email']], $payload['error']['errors']);
    } finally {
        $app->forget(Request::class);
        $app->singleton(Request::class, Request::class);
    }
}

public function testAuthenticationExceptionJsonResponseForAcceptHeader(): void
{
    $app = App::getInstance();
    $app->instance(Request::class, new Request(
        query: [],
        post: [],
        server: ['HTTP_ACCEPT' => 'application/json'],
        cookies: []
    ));

    try {
        $handler = new ExceptionHandler(false);
        $response = $handler->render(new AuthenticationException());
        $payload = json_decode($response->getContent(), true);

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeader('Content-Type'));
        $this->assertSame(401, $payload['error']['status']);
        $this->assertSame('Unauthorized', $payload['error']['message']);
    } finally {
        $app->forget(Request::class);
        $app->singleton(Request::class, Request::class);
    }
}

public function testAuthorizationExceptionJsonResponseForAcceptHeader(): void
{
    $app = App::getInstance();
    $app->instance(Request::class, new Request(
        query: [],
        post: [],
        server: ['HTTP_ACCEPT' => 'application/json'],
        cookies: []
    ));

    try {
        $handler = new ExceptionHandler(false);
        $response = $handler->render(new AuthorizationException());
        $payload = json_decode($response->getContent(), true);

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeader('Content-Type'));
        $this->assertSame(403, $payload['error']['status']);
        $this->assertSame('Forbidden', $payload['error']['message']);
    } finally {
        $app->forget(Request::class);
        $app->singleton(Request::class, Request::class);
    }
}
```

- [ ] **Step 2: Run the focused tests to verify they fail**

Run:

```bash
php test tests/ValidationTest.php tests/ExceptionHandlerTest.php
```

Expected: FAIL with these red signals:

```text
Call to undefined method Bin\Validation\ValidationManager::validateOrFail()
Failed asserting that 302 matches expected 422
```

- [ ] **Step 3: Add the named throwing validation entry point**

In `bin/Validation/ValidationManager.php`, add this method after `throwOnFail()`:

```php
public function validateOrFail(): array
{
    return $this->throwOnFail(true)->validate();
}
```

In `bin/Func/helpers/validation.php`, replace the body of `validate()` with:

```php
return \Bin\Validation\ValidationManager::make($data, $rules)
    ->validateOrFail();
```

In `bin/Exception/ValidationException.php`, replace the `$errors` docblocks with:

```php
/** @var array<string, string|array<string>> */
private array $errors;
```

```php
/**
 * @param array<string, string|array<string>> $errors
 */
```

```php
/**
 * 获取所有验证错误
 * @return array<string, string|array<string>>
 */
```

- [ ] **Step 4: Render JSON when the active request expects JSON**

In `bin/Exception/ExceptionHandler.php`, add these imports:

```php
use Bin\App\App;
use Bin\Request\Request;
```

Replace both uses of `isAjax()` inside `renderException()`:

```php
if ($this->isAjax()) {
    return $this->renderJson($e, $status);
}

if ($e instanceof ValidationException && !$this->isAjax()) {
    return $this->renderValidationRedirect($e);
}
```

with:

```php
$expectsJson = $this->expectsJsonResponse();

if ($expectsJson) {
    return $this->renderJson($e, $status);
}

if ($e instanceof ValidationException) {
    return $this->renderValidationRedirect($e);
}
```

Replace the `isAjax()` method at the bottom of the class with:

```php
protected function isAjax(): bool
{
    return is_ajax();
}

protected function expectsJsonResponse(): bool
{
    try {
        $app = App::getInstance();
        if ($app->bound(Request::class)) {
            $request = $app->make(Request::class);
            if ($request instanceof Request) {
                return $request->expectsJson();
            }
        }
    } catch (\Throwable) {
        return $this->isAjax();
    }

    return $this->isAjax();
}
```

- [ ] **Step 5: Run the focused tests to verify they pass**

Run:

```bash
php test tests/ValidationTest.php tests/ExceptionHandlerTest.php
```

Expected: PASS for both test files.

- [ ] **Step 6: Commit Task 3**

```bash
git add bin/Validation/ValidationManager.php bin/Func/helpers/validation.php bin/Exception/ValidationException.php bin/Exception/ExceptionHandler.php tests/ValidationTest.php tests/ExceptionHandlerTest.php
git commit -m "feat: unify validation failure rendering"
```

---

### Task 4: Protected Input and Identity Chain Regression

**Files:**
- Test: `tests/DispatcherIntegrationTest.php`

- [ ] **Step 1: Write the end-to-end protected route regression**

Add these imports to `tests/DispatcherIntegrationTest.php`:

```php
use Bin\Auth\AuthManager;
use Bin\Middleware\AuthMiddleware;
use Bin\Middleware\MiddlewareStack;
use Bin\Route\RouteCollection;
```

Add this method inside `DispatcherIntegrationTest`:

```php
public function testProtectedRouteControllerReceivesValidatedInputAndCurrentUser(): void
{
    $originalProvider = AuthManager::getProvider();
    $originalServer = $_SERVER;
    $originalPost = $_POST;

    try {
        IdentityInputRouteUser::reset();
        AuthManager::setProvider(IdentityInputRouteUser::class);
        AuthManager::loginUsingId(7);

        MiddlewareStack::reset();
        MiddlewareStack::loadFromConfig([
            'global' => [],
            'groups' => [],
            'aliases' => [
                'auth' => AuthMiddleware::class,
            ],
            'priority' => [
                'auth' => 10,
                AuthMiddleware::class => 10,
            ],
        ]);

        $_SERVER = array_merge($_SERVER, [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/identity/profile',
            'SERVER_NAME' => 'localhost',
        ]);
        $_POST = [
            'name' => 'Ada',
            'role' => 'admin',
            'ignored' => 'not-returned',
        ];

        RouteCollection::post('/identity/profile', function (RouteIdentityInputRequest $request): array {
            return [
                'validated' => $request->validated(),
                'user_id' => $request->user()?->id,
            ];
        })->middleware('auth');

        $response = \Bin\Route\RouteAction::dispatch(Request::capture());
        $payload = json_decode($response->getContent(), true);

        $this->assertSame(['name' => 'Ada', 'role' => 'admin'], $payload['validated']);
        $this->assertSame(7, $payload['user_id']);
    } finally {
        $_SERVER = $originalServer;
        $_POST = $originalPost;
        RouteCollection::clear();
        MiddlewareStack::reset();
        AuthManager::setProvider($originalProvider ?? 'App\\Model\\User');
        AuthManager::resetUser();
        session_manager()->clear();
    }
}
```

Add these helper classes at the bottom of `tests/DispatcherIntegrationTest.php`:

```php
class RouteIdentityInputRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->id === 7;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string',
            'role' => 'required|string',
        ];
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
        ];
    }

    public static function find(mixed $id): ?self
    {
        return self::$users[(int) $id] ?? null;
    }
}
```

- [ ] **Step 2: Run the focused integration test to verify it passes**

Run:

```bash
php test tests/DispatcherIntegrationTest.php
```

Expected: PASS for `tests/DispatcherIntegrationTest.php`. This test should fail if Task 1 or Task 2 is reverted.

- [ ] **Step 3: Run the M2-B regression set**

Run:

```bash
php test tests/RequestTest.php tests/AuthTest.php tests/MiddlewareTest.php tests/DispatcherIntegrationTest.php tests/ValidationTest.php tests/FormRequestTest.php tests/ExceptionHandlerTest.php
```

Expected: PASS for the listed tests. Existing PHP 8.5 deprecation warnings may still appear.

- [ ] **Step 4: Commit Task 4**

```bash
git add tests/DispatcherIntegrationTest.php
git commit -m "test: cover protected identity input chain"
```

---

### Task 5: Full Verification and Graph Refresh

**Files:**
- No source edits unless verification exposes a regression.

- [ ] **Step 1: Run the full suite**

Run:

```bash
php test
```

Expected: PASS with the full suite. The current baseline before this plan was:

```text
Tests: 1813, S 36 skipped, 1777 passed
```

The final count will be higher because this plan adds tests. Existing PHP 8.5 deprecation warnings from unrelated nullable parameter signatures may still appear.

- [ ] **Step 2: Inspect changed scope before final commit**

Run:

```bash
git status --short
```

Expected: only the M2-B files listed in this plan are modified.

If GitNexus MCP tools are available, run change detection for the staged or working tree scope before final completion. If they are unavailable, record that in the final handoff.

- [ ] **Step 3: Refresh graphify after code changes**

Run:

```bash
graphify update .
```

Expected: graph update completes without requiring API access.

- [ ] **Step 4: Run the full suite after graph refresh**

Run:

```bash
php test
```

Expected: PASS with the same test count as Step 1.

- [ ] **Step 5: Commit graph or generated metadata only if tracked files changed**

Run:

```bash
git status --short
```

Expected: if graphify updates tracked files, commit them with:

```bash
git add graphify-out
git commit -m "chore: update graph after m2b identity input"
```

If `git status --short` does not show tracked graphify changes, do not create an empty commit.

---

## Execution Notes

- Execute tasks in order. Task 2 depends on the request API from Task 1. Task 4 depends on Tasks 1 and 2.
- Use `superpowers:test-driven-development`: each task writes the red test first, verifies the red failure, then implements the smallest code change to turn it green.
- Use `superpowers:subagent-driven-development` for implementation: dispatch one fresh implementer per task, then run spec-compliance review and code-quality review before moving to the next task.
- Keep commits task-sized. Do not squash unless explicitly requested.
