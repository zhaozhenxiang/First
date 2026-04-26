# M2-A Web State Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.
>
> **Status:** Completed on 2026-04-26 in branch `m2a-web-state-foundation`.
> Final verification: `php test` completed with `Tests: 1812, S 36 skipped, ✓ 1776 passed`; existing PHP 8.5 deprecation warnings are still emitted by unrelated code.

**Goal:** Build request-scoped session startup, queued cookie writeback, and session-backed CSRF protection for the `web` middleware group.

**Architecture:** Put session start/save in a new `SessionMiddleware` placed before `CsrfMiddleware` inside `config/middleware.php`. Stop writing cookies directly with `setcookie()` and instead queue `Set-Cookie` headers onto `Response`; make `session_manager()` and `CsrfMiddleware` resolve the container-backed `SessionManager` so state flows through one request/response path.

**Tech Stack:** PHP 8.3+, custom Bin runtime, `App`/`Container`, `MiddlewareStack`/`Pipeline`, `ResponseFactory`, existing `php test` runner.

---

## File Structure

- Create: `bin/Middleware/SessionMiddleware.php`
  Responsibility: restore session ID from request cookies, start the session, normalize controller output to `Response`, queue the session cookie, save the session, and attach queued cookies to the outgoing response.
- Modify: `bin/Request/Request.php`
  Responsibility: expose incoming cookies through explicit request accessors instead of forcing middleware to reach into raw arrays.
- Modify: `bin/Session/SessionManager.php`
  Responsibility: configure the PHP session cookie name and default file handler from `config/session.php`, keep track of whether the session cookie should be expired on the response, and stop deleting the cookie via direct `setcookie()`.
- Modify: `bin/Cookie/CookieManager.php`
  Responsibility: keep request cookie reads as-is, but replace direct `setcookie()` writes with queued `Set-Cookie` header lines that can be drained onto `Response`.
- Modify: `bin/Response/Response.php`
  Responsibility: support multi-value headers such as repeated `Set-Cookie` and expose an accessor for all header lines under one name.
- Modify: `bin/Func/helpers/session.php`
  Responsibility: resolve the singleton `SessionManager` from `App` instead of holding a helper-local static instance.
- Modify: `config/middleware.php`
  Responsibility: place `SessionMiddleware` at the front of the `web` group and register a `session` alias/priority.
- Modify: `tests/RequestTest.php`
  Responsibility: cover request cookie accessors.
- Modify: `tests/ApplicationLifecycleTest.php`
  Responsibility: verify `web` middleware order and alias registration after HTTP bootstrap.
- Modify: `tests/SessionTest.php`
  Responsibility: verify configured session cookie naming and cookie-expiration flags after session destruction.
- Modify: `tests/CookieUploadTest.php`
  Responsibility: verify cookie queue behavior without CLI `setcookie()` noise.
- Modify: `tests/ResponseTest.php`
  Responsibility: verify repeated `Set-Cookie` headers are stored and inspectable.
- Modify: `tests/MiddlewareTest.php`
  Responsibility: verify `SessionMiddleware` restores/saves session state and `CsrfMiddleware` uses the session-backed token path.

---

### Task 1: Expose Request Cookies and Wire Session Entry Points

**Files:**
- Modify: `bin/Request/Request.php`
- Modify: `bin/Session/SessionManager.php`
- Modify: `bin/Func/helpers/session.php`
- Modify: `config/middleware.php`
- Test: `tests/RequestTest.php`
- Test: `tests/ApplicationLifecycleTest.php`
- Test: `tests/SessionTest.php`

- [x] **Step 1: Write the failing tests**

Add these methods to `tests/RequestTest.php`:

```php
public function testCookieAccessorReturnsSingleCookie(): void
{
    $request = $this->makeRequest(cookies: ['session' => 'abc123', 'theme' => 'dark']);

    $this->assertSame('abc123', $request->cookie('session'));
    $this->assertNull($request->cookie('missing'));
}

public function testCookieAccessorReturnsAllCookies(): void
{
    $request = $this->makeRequest(cookies: ['session' => 'abc123', 'theme' => 'dark']);

    $this->assertSame(['session' => 'abc123', 'theme' => 'dark'], $request->cookie());
}
```

Add `use Bin\Middleware\SessionMiddleware;` to `tests/ApplicationLifecycleTest.php`, then add:

```php
public function testHttpBootstrapLoadsSessionBeforeCsrfInWebGroup(): void
{
    $app = App::getInstance();
    $http = new HttpKernel($app);
    $tempBasePath = $this->createTempBootstrapBasePath();
    $this->setAppBasePath($app, $tempBasePath);

    $reflection = new \ReflectionMethod(HttpKernel::class, 'bootstrap');
    $reflection->invoke($http);

    $stack = MiddlewareStack::getInstance();

    $this->assertSame(SessionMiddleware::class, $stack->getGroup('web')[0]);
    $this->assertSame(CsrfMiddleware::class, $stack->getGroup('web')[1]);
    $this->assertSame(SessionMiddleware::class, $stack->getAliases()['session']);
}
```

Replace `tests/SessionTest.php::testSessionName()` with:

```php
public function testSessionName(): void
{
    $name = $this->session->getName();
    $config = require config_path('session.php');

    $this->assertEquals($config['cookie']['name'], $name);
}
```

- [x] **Step 2: Run the focused tests to verify they fail**

Run: `php test tests/RequestTest.php tests/ApplicationLifecycleTest.php tests/SessionTest.php`

Expected: FAIL with all three classes of errors visible:

```text
Call to undefined method Bin\Request\Request::cookie()
Failed asserting that two strings are identical
Failed asserting that 'PHPSESSID' matches configured session cookie name
```

- [x] **Step 3: Implement the request/session entry-point changes**

In `bin/Request/Request.php`, add explicit cookie accessors near the other input-source methods:

```php
public function cookie(?string $key = null, mixed $default = null): mixed
{
    if ($key === null) {
        return $this->cookies;
    }

    return data_get($this->cookies, $key, $default);
}

public function hasCookie(string $key): bool
{
    return data_has($this->cookies, $key);
}
```

In `bin/Func/helpers/session.php`, replace the helper-local singleton with container resolution:

```php
function session_manager(): \Bin\Session\SessionManager
{
    return \Bin\App\App::getInstance()->make(\Bin\Session\SessionManager::class);
}
```

In `bin/Session/SessionManager.php`, import the file handler and configure the cookie name/default file handler from `config/session.php`:

```php
use Bin\Session\FileSessionHandler;
```

```php
public function __construct()
{
    if (function_exists('config')) {
        $this->lifetime = (int) config('session.lifetime', 7200);
    }

    $this->configure();
    $this->configureDefaultHandler();
}

private function configureDefaultHandler(): void
{
    if ($this->handler !== null) {
        return;
    }

    if (!function_exists('config')) {
        return;
    }

    if ((string) config('session.driver', 'file') !== 'file') {
        return;
    }

    $path = (string) config('session.files', storage_path('sessions'));
    $minutes = (int) ceil($this->lifetime / 60);

    $this->handler = new FileSessionHandler($path, $minutes);
}

private function configure(): void
{
    $cookieConfig = function_exists('config') ? config('session.cookie', []) : [];

    @session_name((string) ($cookieConfig['name'] ?? 'first_session'));
    @ini_set('session.cookie_path', (string) ($cookieConfig['path'] ?? '/'));

    if (($domain = $cookieConfig['domain'] ?? null) !== null) {
        @ini_set('session.cookie_domain', (string) $domain);
    }

    $options = [
        'session.use_cookies' => '1',
        'session.use_only_cookies' => '1',
        'session.cookie_httponly' => ($cookieConfig['http_only'] ?? true) ? '1' : '0',
        'session.use_strict_mode' => '1',
        'session.cookie_lifetime' => (string) $this->lifetime,
    ];

    foreach ($options as $key => $value) {
        @ini_set($key, $value);
    }

    @ini_set('session.cookie_samesite', $cookieConfig['same_site'] ?? 'Lax');

    $secure = $cookieConfig['secure'] ?? false;
    if ($secure || (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')) {
        @ini_set('session.cookie_secure', '1');
    }
}
```

In `config/middleware.php`, register `SessionMiddleware` ahead of CSRF:

```php
use Bin\Middleware\SessionMiddleware;
```

```php
'groups' => [
    'web' => [
        SessionMiddleware::class,
        CsrfMiddleware::class,
    ],
    'api' => [
        RateLimitMiddleware::class,
    ],
],

'aliases' => [
    'session' => SessionMiddleware::class,
    'auth' => AuthMiddleware::class,
    'guest' => GuestMiddleware::class,
    'csrf' => CsrfMiddleware::class,
    'throttle' => RateLimitMiddleware::class,
],

'priority' => [
    'session' => 30,
    'csrf' => 20,
    'auth' => 10,
    'throttle' => 0,
],
```

- [x] **Step 4: Run the focused tests again**

Run: `php test tests/RequestTest.php tests/ApplicationLifecycleTest.php tests/SessionTest.php`

Expected: PASS with `Failed: 0`.

- [x] **Step 5: Commit the entry-point changes**

```bash
git add bin/Request/Request.php bin/Session/SessionManager.php bin/Func/helpers/session.php config/middleware.php tests/RequestTest.php tests/ApplicationLifecycleTest.php tests/SessionTest.php
git commit -m "feat: wire session entry points into web middleware"
```

### Task 2: Queue Cookies Onto Responses Instead of Calling setcookie()

**Files:**
- Modify: `bin/Cookie/CookieManager.php`
- Modify: `bin/Response/Response.php`
- Test: `tests/CookieUploadTest.php`
- Test: `tests/ResponseTest.php`

- [x] **Step 1: Write the failing tests**

Add these methods to `tests/ResponseTest.php`:

```php
public function testResponseAppendsMultipleSetCookieHeaders(): void
{
    $response = new Response('ok');

    $response->appendHeader('Set-Cookie', 'first=value; Path=/; HttpOnly');
    $response->appendHeader('Set-Cookie', 'second=value; Path=/; HttpOnly');

    $this->assertSame('first=value; Path=/; HttpOnly', $response->getHeader('Set-Cookie'));
    $this->assertSame(
        ['first=value; Path=/; HttpOnly', 'second=value; Path=/; HttpOnly'],
        $response->getHeaderLines('Set-Cookie')
    );
}
```

Add these methods to `tests/CookieUploadTest.php`:

```php
public function testCookieSetQueuesHeaderLine(): void
{
    CookieManager::setDefaults([
        'path' => '/',
        'same_site' => 'Lax',
        'http_only' => true,
    ]);

    CookieManager::set($this->testCookiePrefix . 'queued', 'value', 10);

    $queued = CookieManager::drainQueue();

    $this->assertCount(1, $queued);
    $this->assertStringContainsString($this->testCookiePrefix . 'queued=', $queued[0]);
    $this->assertStringContainsString('Path=/', $queued[0]);
    $this->assertStringContainsString('SameSite=Lax', $queued[0]);
}

public function testCookieForgetQueuesExpiredHeader(): void
{
    CookieManager::forget($this->testCookiePrefix . 'expired');

    $queued = CookieManager::drainQueue();

    $this->assertCount(1, $queued);
    $this->assertStringContainsString($this->testCookiePrefix . 'expired=', $queued[0]);
    $this->assertStringContainsString('Expires=', $queued[0]);
}
```

- [x] **Step 2: Run the cookie/response tests to verify they fail**

Run: `php test tests/CookieUploadTest.php tests/ResponseTest.php`

Expected: FAIL with missing-method errors such as:

```text
Call to undefined method Bin\Response\Response::appendHeader()
Call to undefined method Bin\Response\Response::getHeaderLines()
Call to undefined method Bin\Cookie\CookieManager::drainQueue()
```

- [x] **Step 3: Implement queued cookie headers and multi-value response headers**

In `bin/Cookie/CookieManager.php`, replace direct `setcookie()` writes with a drainable queue:

```php
private static array $queued = [];
```

```php
public static function set(
    string $name,
    string $value,
    int $minutes = 0,
    ?string $path = null,
    ?string $domain = null,
    ?bool $secure = null,
    ?bool $httpOnly = null,
    ?string $sameSite = null
): bool {
    $path = $path ?? self::$path;
    $domain = $domain ?? self::$domain;
    $secure = $secure ?? self::$secure;
    $httpOnly = $httpOnly ?? self::$httpOnly;
    $sameSite = $sameSite ?? self::$sameSite;

    $value = self::$encryptionKey !== null
        ? self::encrypt($value)
        : $value;

    $expires = $minutes > 0 ? time() + ($minutes * 60) : 0;

    self::$queued[$name] = self::buildHeaderLine(
        $name,
        $value,
        $expires,
        $path,
        $domain,
        $secure,
        $httpOnly,
        $sameSite
    );

    return true;
}

public static function drainQueue(): array
{
    $queued = array_values(self::$queued);
    self::$queued = [];

    return $queued;
}

private static function buildHeaderLine(
    string $name,
    string $value,
    int $expires,
    string $path,
    string $domain,
    bool $secure,
    bool $httpOnly,
    string $sameSite
): string {
    $parts = [rawurlencode($name) . '=' . rawurlencode($value)];

    if ($expires !== 0) {
        $parts[] = 'Expires=' . gmdate('D, d M Y H:i:s T', $expires);
        $parts[] = 'Max-Age=' . max(0, $expires - time());
    }

    $parts[] = 'Path=' . $path;

    if ($domain !== '') {
        $parts[] = 'Domain=' . $domain;
    }

    if ($secure) {
        $parts[] = 'Secure';
    }

    if ($httpOnly) {
        $parts[] = 'HttpOnly';
    }

    if ($sameSite !== '') {
        $parts[] = 'SameSite=' . $sameSite;
    }

    return implode('; ', $parts);
}
```

Also in `bin/Cookie/CookieManager.php`, make `setDefaults()` understand both camelCase and snake_case config keys:

```php
if (isset($config['httpOnly'])) {
    self::$httpOnly = (bool) $config['httpOnly'];
}
if (isset($config['http_only'])) {
    self::$httpOnly = (bool) $config['http_only'];
}
if (isset($config['sameSite'])) {
    self::$sameSite = (string) $config['sameSite'];
}
if (isset($config['same_site'])) {
    self::$sameSite = (string) $config['same_site'];
}
```

In `bin/Response/Response.php`, let one header name carry multiple values:

```php
/** @var array<string, string|array<int, string>> */
private array $headers = [];
```

```php
public function getHeader(string $name): ?string
{
    $value = $this->headers[$name] ?? null;

    if (is_array($value)) {
        return $value[0] ?? null;
    }

    return $value;
}

public function getHeaderLines(string $name): array
{
    $value = $this->headers[$name] ?? [];

    if (is_array($value)) {
        return $value;
    }

    return $value === null ? [] : [$value];
}

public function appendHeader(string $name, string $value): self
{
    $current = $this->headers[$name] ?? [];
    $current = is_array($current) ? $current : [$current];
    $current[] = $value;
    $this->headers[$name] = $current;

    return $this;
}

public function send(): void
{
    if (!headers_sent()) {
        http_response_code($this->statusCode);

        foreach ($this->headers as $name => $value) {
            if (is_array($value)) {
                foreach ($value as $line) {
                    header("{$name}: {$line}", false);
                }
                continue;
            }

            header("{$name}: {$value}", true);
        }
    }

    echo $this->getContent();
}
```

- [x] **Step 4: Run the cookie/response tests again**

Run: `php test tests/CookieUploadTest.php tests/ResponseTest.php`

Expected: PASS with `Failed: 0`, and no new CLI `setcookie()` warnings during these tests.

- [x] **Step 5: Commit the cookie queue changes**

```bash
git add bin/Cookie/CookieManager.php bin/Response/Response.php tests/CookieUploadTest.php tests/ResponseTest.php
git commit -m "feat: queue cookies onto responses"
```

### Task 3: Add SessionMiddleware for Request Restore and Response Save

**Files:**
- Create: `bin/Middleware/SessionMiddleware.php`
- Modify: `bin/Session/SessionManager.php`
- Test: `tests/MiddlewareTest.php`
- Test: `tests/SessionTest.php`

- [x] **Step 1: Write the failing tests**

Add `use Bin\App\App;`, `use Bin\Middleware\SessionMiddleware;`, `use Bin\Session\FileSessionHandler;`, and `use Bin\Session\SessionManager;` to `tests/MiddlewareTest.php`, then add:

```php
public function testSessionMiddlewareRestoresSessionFromRequestCookieAndQueuesResponseCookie(): void
{
    $tempPath = sys_get_temp_dir() . '/session_middleware_' . uniqid();
    mkdir($tempPath, 0777, true);
    $app = App::getInstance();
    $previous = $app->make(SessionManager::class);

    try {
        $seed = new SessionManager();
        $seed->setHandler(new FileSessionHandler($tempPath));
        $seed->start();
        $seed->set('user_id', 7);
        $seedId = $seed->getId();
        $seed->save();

        $bound = new SessionManager();
        $bound->setHandler(new FileSessionHandler($tempPath));
        $app->instance(SessionManager::class, $bound);

        $request = new Request(
            query: [],
            post: [],
            server: ['REQUEST_METHOD' => 'GET'],
            cookies: [$bound->getName() => $seedId]
        );

        $middleware = new SessionMiddleware();

        $response = $middleware->handle($request, function () use ($bound) {
            return ['user_id' => $bound->get('user_id')];
        });

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(7, json_decode($response->getContent(), true)['user_id']);
        $this->assertNotEmpty($response->getHeaderLines('Set-Cookie'));
    } finally {
        $app->instance(SessionManager::class, $previous);
        array_map('unlink', glob($tempPath . '/sess_*') ?: []);
        @rmdir($tempPath);
    }
}
```

Add this method to `tests/SessionTest.php`:

```php
public function testDestroyMarksSessionCookieForExpiration(): void
{
    $this->session->destroy();

    $this->assertTrue($this->session->shouldExpireCookieOnResponse());

    $this->session->clearCookieExpirationFlag();

    $this->assertFalse($this->session->shouldExpireCookieOnResponse());
}
```

- [x] **Step 2: Run the middleware/session tests to verify they fail**

Run: `php test tests/MiddlewareTest.php tests/SessionTest.php`

Expected: FAIL with missing-class / missing-method errors such as:

```text
Class "Bin\Middleware\SessionMiddleware" not found
Call to undefined method Bin\Session\SessionManager::shouldExpireCookieOnResponse()
```

- [x] **Step 3: Implement SessionMiddleware and session cookie state**

In `bin/Session/SessionManager.php`, add the response-expiration flag and remove direct cookie deletion from `destroy()`:

```php
private bool $expireCookieOnResponse = false;
```

```php
public function destroy(): void
{
    if (!$this->started) {
        $this->start();
    }

    $_SESSION = [];

    @session_destroy();

    $this->started = false;
    $this->tempId = null;
    $this->expireCookieOnResponse = true;
}

public function shouldExpireCookieOnResponse(): bool
{
    return $this->expireCookieOnResponse;
}

public function clearCookieExpirationFlag(): void
{
    $this->expireCookieOnResponse = false;
}
```

Create `bin/Middleware/SessionMiddleware.php`:

```php
<?php

declare(strict_types=1);

namespace Bin\Middleware;

use Bin\App\App;
use Bin\Cookie\CookieManager;
use Bin\Request\Request;
use Bin\Response\Response;
use Bin\Response\ResponseFactory;
use Bin\Session\SessionManager;
use Closure;

class SessionMiddleware extends Middleware
{
    public function handle(mixed $request, Closure $next): mixed
    {
        if (!$request instanceof Request) {
            return $next($request);
        }

        $app = App::getInstance();
        /** @var SessionManager $session */
        $session = $app->make(SessionManager::class);

        $incomingId = $request->cookie($session->getName());
        if (is_string($incomingId) && $incomingId !== '' && !$session->isStarted()) {
            $session->setId($incomingId);
        }

        $session->start();

        $payload = $next($request);
        /** @var ResponseFactory $factory */
        $factory = $app->make(ResponseFactory::class);
        $response = $factory->make($payload);

        $this->syncCookieDefaults();
        $this->queueSessionCookie($session);
        $session->save();

        foreach (CookieManager::drainQueue() as $headerLine) {
            $response->appendHeader('Set-Cookie', $headerLine);
        }

        return $response;
    }

    private function syncCookieDefaults(): void
    {
        $config = (array) config('session.cookie', []);

        CookieManager::setDefaults([
            'path' => $config['path'] ?? '/',
            'domain' => $config['domain'] ?? '',
            'secure' => (bool) ($config['secure'] ?? false),
            'http_only' => (bool) ($config['http_only'] ?? true),
            'same_site' => $config['same_site'] ?? 'Lax',
        ]);
    }

    private function queueSessionCookie(SessionManager $session): void
    {
        if ($session->shouldExpireCookieOnResponse()) {
            CookieManager::forget($session->getName());
            $session->clearCookieExpirationFlag();
            return;
        }

        $minutes = (int) ceil($session->getLifetime() / 60);

        CookieManager::set($session->getName(), $session->getId(), $minutes);
    }
}
```

- [x] **Step 4: Run the middleware/session tests again**

Run: `php test tests/MiddlewareTest.php tests/SessionTest.php`

Expected: PASS with `Failed: 0`.

- [x] **Step 5: Commit the session middleware changes**

```bash
git add bin/Middleware/SessionMiddleware.php bin/Session/SessionManager.php tests/MiddlewareTest.php tests/SessionTest.php
git commit -m "feat: add session middleware and session cookie writeback"
```

### Task 4: Move CSRF Onto the Session Lifecycle

**Files:**
- Modify: `bin/Middleware/CsrfMiddleware.php`
- Test: `tests/MiddlewareTest.php`

- [x] **Step 1: Write the failing tests**

Add these methods to `tests/MiddlewareTest.php`:

```php
public function testCsrfTokenGenerationStoresTokenInSessionManager(): void
{
    $app = \Bin\App\App::getInstance();
    $previous = $app->make(\Bin\Session\SessionManager::class);

    try {
        $session = new \Bin\Session\SessionManager();
        $app->instance(\Bin\Session\SessionManager::class, $session);

        $token = \Bin\Middleware\CsrfMiddleware::generateToken();

        $this->assertSame($token, $session->getCsrfToken());
        $this->assertSame($token, \Bin\Middleware\CsrfMiddleware::generateToken());
    } finally {
        $app->instance(\Bin\Session\SessionManager::class, $previous);
    }
}

public function testCsrfMiddlewareThrowsHttpExceptionOnInvalidToken(): void
{
    $app = \Bin\App\App::getInstance();
    $previous = $app->make(\Bin\Session\SessionManager::class);

    try {
        $session = new \Bin\Session\SessionManager();
        $app->instance(\Bin\Session\SessionManager::class, $session);
        $session->start();
        $session->putCsrfToken();

        $middleware = new \Bin\Middleware\CsrfMiddleware();
        $request = new Request(
            query: [],
            post: ['_csrf_token' => 'wrong-token'],
            server: ['REQUEST_METHOD' => 'POST']
        );

        $this->assertThrows(\Bin\Exception\HttpException::class, function () use ($middleware, $request) {
            $middleware->handle($request, fn($req) => 'ok');
        });
    } finally {
        $app->instance(\Bin\Session\SessionManager::class, $previous);
    }
}
```

Delete the old response-based invalid-token assertion block from `tests/MiddlewareTest.php`:

Remove the existing `testCsrfMiddlewareReturnsResponseOnInvalidToken()` method entirely, because the new behavior is exception-based and should not keep the old response assertion around.

- [x] **Step 2: Run the CSRF tests to verify they fail**

Run: `php test tests/MiddlewareTest.php`

Expected: FAIL because `CsrfMiddleware` still reads `$_SESSION` directly and still returns a `Response` on invalid token instead of throwing `HttpException`.

- [x] **Step 3: Implement session-backed CSRF token storage and failure behavior**

In `bin/Middleware/CsrfMiddleware.php`, import `App` and `HttpException`, remove the static `$token` cache, and route all token access through the singleton `SessionManager`:

```php
use Bin\App\App;
use Bin\Exception\HttpException;
```

```php
private static string $tokenName = '_csrf_token';
```

```php
public function handle(mixed $request, \Closure $next): mixed
{
    $method = $request instanceof Request
        ? $request->getMethod()
        : strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

    if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
        return $next($request);
    }

    $token = null;
    if ($request instanceof Request) {
        $token = $request->input(self::$tokenName)
            ?? $request->header('X-CSRF-Token')
            ?? $request->header('X-XSRF-Token');
    }

    if (!self::validateToken($token)) {
        throw new HttpException(403, 'CSRF token validation failed');
    }

    return $next($request);
}

public static function generateToken(): string
{
    $session = self::session();
    $token = $session->getCsrfToken();

    if ($token === null) {
        $token = $session->putCsrfToken();
    }

    return $token;
}

public static function validateToken(?string $token): bool
{
    if ($token === null || $token === '') {
        return false;
    }

    return self::session()->verifyCsrfToken($token);
}

private static function session(): \Bin\Session\SessionManager
{
    return App::getInstance()->make(\Bin\Session\SessionManager::class);
}
```

- [x] **Step 4: Run the CSRF tests again**

Run: `php test tests/MiddlewareTest.php`

Expected: PASS with `Failed: 0`.

- [x] **Step 5: Commit the CSRF changes**

```bash
git add bin/Middleware/CsrfMiddleware.php tests/MiddlewareTest.php
git commit -m "feat: move csrf onto session lifecycle"
```

### Task 5: Run the M2-A Regression Sweep

**Files:**
- Verify: `tests/ApplicationLifecycleTest.php`
- Verify: `tests/RequestTest.php`
- Verify: `tests/SessionTest.php`
- Verify: `tests/CookieUploadTest.php`
- Verify: `tests/MiddlewareTest.php`
- Verify: `tests/ResponseTest.php`
- Verify: `tests/AuthTest.php`
- Verify: `tests/AuthorizationTest.php`
- Verify: `tests/ValidationTest.php`
- Verify: `tests/ExceptionHandlerTest.php`
- Verify: `tests/BladeCompilerTest.php`

- [x] **Step 1: Run the focused web-state suite**

Run: `php test tests/ApplicationLifecycleTest.php tests/RequestTest.php tests/SessionTest.php tests/CookieUploadTest.php tests/MiddlewareTest.php tests/ResponseTest.php`

Expected: PASS with `Failed: 0`.

- [x] **Step 2: Run the secondary affected suites**

Run: `php test tests/AuthTest.php tests/AuthorizationTest.php tests/ValidationTest.php tests/ExceptionHandlerTest.php tests/BladeCompilerTest.php`

Expected: PASS with `Failed: 0`.

- [x] **Step 3: Check the final diff scope**

Run: `git diff --stat HEAD~5..HEAD`

Expected: Only the files listed in this plan changed, plus the `tests/TestCommandScriptTest.php` assertion-count update required by the added tests.

---

## Self-Review Notes

- `M2-A` spec coverage:
  - `Session` 启动/读取/写回：Task 1 + Task 3
  - `Cookie` 统一写回出口：Task 2 + Task 3
  - `CSRF` token 生命周期与失败路径：Task 4
  - 回归测试锚点：Task 1-5
- No placeholder scan:
  - This plan contains exact file paths, concrete test code, concrete implementation code, concrete commands, and explicit commit messages.
- Type consistency:
  - Cookie queue API is `CookieManager::drainQueue()`
  - Response multi-header API is `Response::appendHeader()` + `Response::getHeaderLines()`
  - Session cookie-expiration API is `SessionManager::shouldExpireCookieOnResponse()` + `SessionManager::clearCookieExpirationFlag()`
