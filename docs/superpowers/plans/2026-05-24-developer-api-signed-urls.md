# Developer API Signed URLs Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add Laravel-style signed route generation, temporary signatures, request validation, and a default `signed` middleware alias to First.

**Architecture:** Keep named URL generation in `RouteCollection::url()` and add a focused `Bin\Route\SignedUrl` helper for canonicalization, HMAC signing, and request validation. Expose generation through `Bin\Facade\URL`, validation through `Bin\Request\Request`, and enforcement through a small middleware class registered as the `signed` alias.

**Tech Stack:** PHP 8.3+, First framework routing and middleware stack, `config('app.key')`, HMAC-SHA256, custom `php test` runner, graphify knowledge graph.

---

## Spec

- Source spec: `docs/superpowers/specs/2026-05-24-developer-api-signed-urls-design.md`
- Parent spec: `docs/superpowers/specs/2026-05-17-developer-api-routing-polish-design.md`
- Follow-up phase: "Signed URLs: signed route generation, temporary signatures, and signature validation middleware."
- This phase intentionally does not add absolute URL signatures, ignored query keys, key rotation, global helper functions, route cache changes, or broader request/response/controller API polish.

## File Structure

- Create `bin/Route/SignedUrl.php`
  - Responsibility: canonicalize relative route URLs, sign named routes, sign temporary named routes, validate request signatures, and resolve the signing key from config.
- Modify `bin/Facade/URL.php`
  - Responsibility: expose `signedRoute()` and `temporarySignedRoute()` alongside the existing `route()` method.
- Modify `bin/Request/Request.php`
  - Responsibility: expose `hasValidSignature()` as the request-level validation API.
- Create `bin/Middleware/ValidateSignature.php`
  - Responsibility: enforce valid signed requests and throw HTTP 403 for invalid signatures.
- Modify `config/middleware.php`
  - Responsibility: register the default `signed` middleware alias.
- Create `tests/SignedUrlTest.php`
  - Coverage: URL generation, temporary expiration parameter, query sorting, reserved parameter rejection, and missing key generation failure.
- Modify `tests/RequestTest.php`
  - Coverage: valid signed request detection, tampered query rejection, missing signature rejection, and expired signature rejection.
- Modify `tests/MiddlewareTest.php`
  - Coverage: signed middleware allows valid requests and rejects tampered requests with HTTP 403.
- Modify `tests/MiddlewarePipelineTest.php`
  - Coverage: default middleware config includes the `signed` alias and the alias points to an existing class.
- Update `graphify-out/` by running `graphify update .` after code changes.

---

### Task 1: Signed Route Generation Tests

**Files:**
- Create: `tests/SignedUrlTest.php`

- [ ] **Step 1: Write failing tests for signed URL generation**

Create `tests/SignedUrlTest.php` with this full content:

```php
<?php

declare(strict_types=1);

namespace Tests;

use Bin\Facade\URL;
use Bin\Route\RouteCollection as Route;
use Bin\Testing\TestCase;

class SignedUrlTest extends TestCase
{
    private mixed $previousAppKey = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousAppKey = config('app.key');
        config(['app.key' => 'testing-secret']);
        Route::clear();
    }

    protected function tearDown(): void
    {
        Route::clear();
        config(['app.key' => $this->previousAppKey]);

        parent::tearDown();
    }

    public function testSignedRouteGeneratesHmacForNamedRoute(): void
    {
        Route::get('/invites/{invite}', 'InviteController@show')->name('invites.show');

        $signature = hash_hmac(
            'sha256',
            '/invites/42?email=taylor%40example.com',
            'testing-secret'
        );

        $this->assertSame(
            '/invites/42?email=taylor%40example.com&signature=' . $signature,
            URL::signedRoute('invites.show', [
                'invite' => 42,
                'email' => 'taylor@example.com',
            ])
        );
    }

    public function testTemporarySignedRouteAddsExpiresBeforeSigning(): void
    {
        Route::get('/files/{file}', 'FileController@show')->name('files.show');

        $expires = 1893456000;
        $signature = hash_hmac(
            'sha256',
            '/files/report?expires=1893456000',
            'testing-secret'
        );

        $this->assertSame(
            '/files/report?expires=1893456000&signature=' . $signature,
            URL::temporarySignedRoute('files.show', $expires, ['file' => 'report'])
        );
    }

    public function testSignedRouteSortsQueryParametersBeforeSigning(): void
    {
        Route::get('/download/{file}', 'DownloadController@show')->name('download.show');

        $signature = hash_hmac(
            'sha256',
            '/download/report.pdf?a=first&z=last',
            'testing-secret'
        );

        $this->assertSame(
            '/download/report.pdf?a=first&signature=' . $signature . '&z=last',
            URL::signedRoute('download.show', [
                'file' => 'report.pdf',
                'z' => 'last',
                'a' => 'first',
            ])
        );
    }

    public function testSignedRouteRejectsReservedSignatureParameter(): void
    {
        Route::get('/download/{file}', 'DownloadController@show')->name('download.show');

        $this->assertThrows(\InvalidArgumentException::class, function (): void {
            URL::signedRoute('download.show', [
                'file' => 'report.pdf',
                'signature' => 'user-supplied',
            ]);
        });
    }

    public function testTemporarySignedRouteRejectsReservedExpiresParameter(): void
    {
        Route::get('/download/{file}', 'DownloadController@show')->name('download.show');

        $this->assertThrows(\InvalidArgumentException::class, function (): void {
            URL::temporarySignedRoute('download.show', 1893456000, [
                'file' => 'report.pdf',
                'expires' => 1,
            ]);
        });
    }

    public function testSignedRouteRequiresConfiguredAppKey(): void
    {
        Route::get('/download/{file}', 'DownloadController@show')->name('download.show');
        config(['app.key' => '']);

        $this->assertThrows(\RuntimeException::class, function (): void {
            URL::signedRoute('download.show', ['file' => 'report.pdf']);
        });
    }
}
```

- [ ] **Step 2: Run the new generation tests and verify they fail**

Run:

```bash
php test tests/SignedUrlTest.php
```

Expected: FAIL with an error that `Bin\Facade\URL::signedRoute()` or `Bin\Facade\URL::temporarySignedRoute()` does not exist.

- [ ] **Step 3: Commit the failing tests**

```bash
git add tests/SignedUrlTest.php
git commit -m "test: cover signed URL generation"
```

---

### Task 2: Signed URL Helper And URL Facade API

**Files:**
- Create: `bin/Route/SignedUrl.php`
- Modify: `bin/Facade/URL.php`
- Test: `tests/SignedUrlTest.php`

- [ ] **Step 1: Implement the signed URL helper**

Create `bin/Route/SignedUrl.php` with this full content:

```php
<?php

declare(strict_types=1);

namespace Bin\Route;

use Bin\Request\Request;
use DateTimeInterface;
use InvalidArgumentException;
use RuntimeException;

class SignedUrl
{
    public const SIGNATURE_KEY = 'signature';
    public const EXPIRES_KEY = 'expires';

    public static function signedRoute(string $name, array $parameters = []): string
    {
        return self::route($name, $parameters, null);
    }

    public static function temporarySignedRoute(string $name, DateTimeInterface|int $expiration, array $parameters = []): string
    {
        return self::route($name, $parameters, self::expirationTimestamp($expiration));
    }

    public static function hasValidSignature(Request $request): bool
    {
        $query = $request->query();

        if (
            !isset($query[self::SIGNATURE_KEY])
            || !is_string($query[self::SIGNATURE_KEY])
            || $query[self::SIGNATURE_KEY] === ''
        ) {
            return false;
        }

        $provided = $query[self::SIGNATURE_KEY];
        unset($query[self::SIGNATURE_KEY]);

        if (isset($query[self::EXPIRES_KEY])) {
            $expires = $query[self::EXPIRES_KEY];

            if (!is_scalar($expires) || !ctype_digit((string) $expires)) {
                return false;
            }

            if ((int) $expires < time()) {
                return false;
            }
        }

        $path = parse_url((string) $request->server('REQUEST_URI', '/'), PHP_URL_PATH) ?: '/';
        $canonical = self::canonicalUrl($path, $query);

        try {
            $expected = self::signature($canonical);
        } catch (RuntimeException) {
            return false;
        }

        return hash_equals($expected, $provided);
    }

    private static function route(string $name, array $parameters, ?int $expires): string
    {
        self::assertNoReservedParameters($parameters);

        $url = RouteCollection::url($name, $parameters);
        [$path, $query] = self::splitUrl($url);

        if ($expires !== null) {
            $query[self::EXPIRES_KEY] = $expires;
        }

        $canonical = self::canonicalUrl($path, $query);
        $query[self::SIGNATURE_KEY] = self::signature($canonical);

        return self::canonicalUrl($path, $query);
    }

    private static function assertNoReservedParameters(array $parameters): void
    {
        foreach ([self::SIGNATURE_KEY, self::EXPIRES_KEY] as $reserved) {
            if (array_key_exists($reserved, $parameters)) {
                throw new InvalidArgumentException("Signed route parameters may not contain reserved key [{$reserved}].");
            }
        }
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private static function splitUrl(string $url): array
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '/';
        $queryString = parse_url($url, PHP_URL_QUERY) ?? '';
        $query = [];

        if ($queryString !== '') {
            parse_str($queryString, $query);
        }

        return [$path, $query];
    }

    private static function expirationTimestamp(DateTimeInterface|int $expiration): int
    {
        return $expiration instanceof DateTimeInterface
            ? $expiration->getTimestamp()
            : $expiration;
    }

    private static function canonicalUrl(string $path, array $query): string
    {
        $path = '/' . ltrim($path, '/');
        self::sortQuery($query);

        $queryString = http_build_query($query);

        return $queryString === '' ? $path : $path . '?' . $queryString;
    }

    private static function sortQuery(array &$query): void
    {
        ksort($query);

        foreach ($query as &$value) {
            if (is_array($value)) {
                self::sortQuery($value);
            }
        }
    }

    private static function signature(string $canonicalUrl): string
    {
        return hash_hmac('sha256', $canonicalUrl, self::signingKey());
    }

    private static function signingKey(): string
    {
        $key = config('app.key');

        if (!is_string($key) || trim($key) === '') {
            throw new RuntimeException("Unable to sign URL because config('app.key') is not set.");
        }

        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);

            if ($decoded !== false && $decoded !== '') {
                return $decoded;
            }
        }

        return $key;
    }
}
```

- [ ] **Step 2: Expose signed route methods on the URL facade**

In `bin/Facade/URL.php`, add this import below the existing imports:

```php
use Bin\Route\SignedUrl;
use DateTimeInterface;
```

Update the class docblock method list to include:

```php
 * @method static string signedRoute(string $name, array $params = [])
 * @method static string temporarySignedRoute(string $name, DateTimeInterface|int $expiration, array $params = [])
```

Add these methods below the existing `route()` method:

```php
    /**
     * Generate a signed named route URL.
     */
    public static function signedRoute(string $name, array $params = []): string
    {
        return SignedUrl::signedRoute($name, $params);
    }

    /**
     * Generate a temporary signed named route URL.
     */
    public static function temporarySignedRoute(string $name, DateTimeInterface|int $expiration, array $params = []): string
    {
        return SignedUrl::temporarySignedRoute($name, $expiration, $params);
    }
```

- [ ] **Step 3: Run generation tests and verify they pass**

Run:

```bash
php test tests/SignedUrlTest.php
```

Expected: PASS, with all `SignedUrlTest` tests passing.

- [ ] **Step 4: Run route tests for URL generation compatibility**

Run:

```bash
php test tests/RouteTest.php
```

Expected: PASS. Existing route URL generation tests must still pass.

- [ ] **Step 5: Commit the helper and facade API**

```bash
git add bin/Route/SignedUrl.php bin/Facade/URL.php
git commit -m "feat: add signed route URL generation"
```

---

### Task 3: Request Signature Validation

**Files:**
- Modify: `tests/RequestTest.php`
- Modify: `bin/Request/Request.php`
- Test: `tests/RequestTest.php`

- [ ] **Step 1: Add failing request validation tests**

In `tests/RequestTest.php`, add these imports below the existing imports:

```php
use Bin\Facade\URL;
use Bin\Route\RouteCollection as Route;
```

Add these tests near the URL/path test section:

```php
    public function testRequestHasValidSignatureForSignedUrl(): void
    {
        $previousKey = config('app.key');
        config(['app.key' => 'testing-secret']);
        Route::clear();

        try {
            Route::get('/invites/{invite}', 'InviteController@show')->name('invites.show');

            $url = URL::signedRoute('invites.show', [
                'invite' => 42,
                'email' => 'taylor@example.com',
            ]);

            $this->assertTrue($this->requestFromSignedUrl($url)->hasValidSignature());
        } finally {
            Route::clear();
            config(['app.key' => $previousKey]);
        }
    }

    public function testRequestRejectsTamperedSignedQuery(): void
    {
        $previousKey = config('app.key');
        config(['app.key' => 'testing-secret']);
        Route::clear();

        try {
            Route::get('/invites/{invite}', 'InviteController@show')->name('invites.show');

            $url = URL::signedRoute('invites.show', [
                'invite' => 42,
                'email' => 'taylor@example.com',
            ]);

            $request = $this->requestFromSignedUrl($url, ['email' => 'mallory@example.com']);

            $this->assertFalse($request->hasValidSignature());
        } finally {
            Route::clear();
            config(['app.key' => $previousKey]);
        }
    }

    public function testRequestRejectsMissingSignature(): void
    {
        $previousKey = config('app.key');
        config(['app.key' => 'testing-secret']);

        try {
            $request = $this->makeRequest(
                query: ['email' => 'taylor@example.com'],
                server: ['REQUEST_URI' => '/invites/42?email=taylor%40example.com']
            );

            $this->assertFalse($request->hasValidSignature());
        } finally {
            config(['app.key' => $previousKey]);
        }
    }

    public function testRequestRejectsExpiredTemporarySignedUrl(): void
    {
        $previousKey = config('app.key');
        config(['app.key' => 'testing-secret']);
        Route::clear();

        try {
            Route::get('/invites/{invite}', 'InviteController@show')->name('invites.show');

            $url = URL::temporarySignedRoute('invites.show', time() - 10, ['invite' => 42]);

            $this->assertFalse($this->requestFromSignedUrl($url)->hasValidSignature());
        } finally {
            Route::clear();
            config(['app.key' => $previousKey]);
        }
    }
```

Add this helper method before the closing brace of `RequestTest`:

```php
    private function requestFromSignedUrl(string $url, array $queryOverrides = []): Request
    {
        $parts = parse_url($url);
        $path = $parts['path'] ?? '/';
        $query = [];

        parse_str($parts['query'] ?? '', $query);

        $query = array_replace($query, $queryOverrides);
        $queryString = http_build_query($query);

        return $this->makeRequest(
            query: $query,
            server: [
                'REQUEST_URI' => $path . ($queryString === '' ? '' : '?' . $queryString),
            ]
        );
    }
```

- [ ] **Step 2: Run request tests and verify the new tests fail**

Run:

```bash
php test tests/RequestTest.php
```

Expected: FAIL with an error that `Bin\Request\Request::hasValidSignature()` does not exist.

- [ ] **Step 3: Add request-level signature validation**

In `bin/Request/Request.php`, add this import below the existing imports:

```php
use Bin\Route\SignedUrl;
```

Add this method near the URL/path methods, after `fullUrlWithoutQuery()`:

```php
    public function hasValidSignature(): bool
    {
        return SignedUrl::hasValidSignature($this);
    }
```

- [ ] **Step 4: Run request tests and verify they pass**

Run:

```bash
php test tests/RequestTest.php
```

Expected: PASS, including the signed URL validation tests.

- [ ] **Step 5: Run signed URL tests again**

Run:

```bash
php test tests/SignedUrlTest.php
```

Expected: PASS.

- [ ] **Step 6: Commit request validation**

```bash
git add tests/RequestTest.php bin/Request/Request.php
git commit -m "feat: validate signed URLs on requests"
```

---

### Task 4: Signature Validation Middleware

**Files:**
- Create: `bin/Middleware/ValidateSignature.php`
- Modify: `tests/MiddlewareTest.php`
- Test: `tests/MiddlewareTest.php`

- [ ] **Step 1: Add failing middleware tests**

In `tests/MiddlewareTest.php`, add these imports below the existing imports:

```php
use Bin\Exception\HttpException;
use Bin\Facade\URL;
use Bin\Middleware\ValidateSignature;
use Bin\Route\RouteCollection as Route;
```

Add these tests near the other middleware behavior tests:

```php
    public function testValidateSignatureMiddlewareAllowsValidSignedRequest(): void
    {
        $previousKey = config('app.key');
        config(['app.key' => 'testing-secret']);
        Route::clear();

        try {
            Route::get('/signed/{id}', 'SignedController@show')->name('signed.show');

            $request = $this->middlewareRequestFromSignedUrl(
                URL::signedRoute('signed.show', ['id' => 5])
            );

            $middleware = new ValidateSignature();

            $this->assertSame('next', $middleware->handle($request, fn (Request $request): string => 'next'));
        } finally {
            Route::clear();
            config(['app.key' => $previousKey]);
        }
    }

    public function testValidateSignatureMiddlewareRejectsTamperedRequestWith403(): void
    {
        $previousKey = config('app.key');
        config(['app.key' => 'testing-secret']);
        Route::clear();

        try {
            Route::get('/signed/{id}', 'SignedController@show')->name('signed.show');

            $signedUrl = URL::signedRoute('signed.show', ['id' => 5]);
            $request = $this->middlewareRequestFromSignedUrl(str_replace('/signed/5', '/signed/6', $signedUrl));
            $middleware = new ValidateSignature();

            try {
                $middleware->handle($request, fn (Request $request): string => 'next');
                $this->fail('Expected invalid signature to throw HttpException.');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
                $this->assertSame('Invalid signature.', $exception->getMessage());
            }
        } finally {
            Route::clear();
            config(['app.key' => $previousKey]);
        }
    }
```

Add this helper method before the closing brace of `MiddlewareTest`:

```php
    private function middlewareRequestFromSignedUrl(string $url): Request
    {
        $parts = parse_url($url);
        $path = $parts['path'] ?? '/';
        $query = [];

        parse_str($parts['query'] ?? '', $query);

        $queryString = http_build_query($query);

        return new Request(
            query: $query,
            post: [],
            server: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => $path . ($queryString === '' ? '' : '?' . $queryString),
                'SERVER_NAME' => 'localhost',
            ],
            cookies: []
        );
    }
```

- [ ] **Step 2: Run middleware tests and verify the new tests fail**

Run:

```bash
php test tests/MiddlewareTest.php
```

Expected: FAIL with an error that class `Bin\Middleware\ValidateSignature` does not exist.

- [ ] **Step 3: Implement the middleware**

Create `bin/Middleware/ValidateSignature.php` with this full content:

```php
<?php

declare(strict_types=1);

namespace Bin\Middleware;

use Bin\Exception\HttpException;
use Bin\Request\Request;
use Closure;

class ValidateSignature extends Middleware
{
    public function handle(mixed $request, Closure $next): mixed
    {
        if (!$request instanceof Request || !$request->hasValidSignature()) {
            throw new HttpException(403, 'Invalid signature.');
        }

        return $next($request);
    }
}
```

- [ ] **Step 4: Run middleware tests and verify they pass**

Run:

```bash
php test tests/MiddlewareTest.php
```

Expected: PASS.

- [ ] **Step 5: Run request and signed URL tests again**

Run:

```bash
php test tests/RequestTest.php
php test tests/SignedUrlTest.php
```

Expected: both commands pass.

- [ ] **Step 6: Commit middleware enforcement**

```bash
git add tests/MiddlewareTest.php bin/Middleware/ValidateSignature.php
git commit -m "feat: add signed URL middleware"
```

---

### Task 5: Default Middleware Alias

**Files:**
- Modify: `config/middleware.php`
- Modify: `tests/MiddlewarePipelineTest.php`
- Test: `tests/MiddlewarePipelineTest.php`

- [ ] **Step 1: Add failing config coverage for the signed alias**

In `tests/MiddlewarePipelineTest.php`, update `testMiddlewareConfigHasDefaultAliases()` so it includes:

```php
        $this->assertArrayHasKey('signed', $config['aliases']);
```

The full method should be:

```php
    public function testMiddlewareConfigHasDefaultAliases(): void
    {
        $config = require BASE_PATH . '/config/middleware.php';

        $this->assertArrayHasKey('auth', $config['aliases']);
        $this->assertArrayHasKey('guest', $config['aliases']);
        $this->assertArrayHasKey('csrf', $config['aliases']);
        $this->assertArrayHasKey('throttle', $config['aliases']);
        $this->assertArrayHasKey('signed', $config['aliases']);
    }
```

- [ ] **Step 2: Run middleware pipeline tests and verify the new assertion fails**

Run:

```bash
php test tests/MiddlewarePipelineTest.php
```

Expected: FAIL because the `signed` alias is missing from `config/middleware.php`.

- [ ] **Step 3: Register the default alias**

In `config/middleware.php`, add this import:

```php
use Bin\Middleware\ValidateSignature;
```

Then add this alias inside the `aliases` array:

```php
        'signed' => ValidateSignature::class,
```

The alias block should be:

```php
    'aliases' => [
        'session' => SessionMiddleware::class,
        'auth' => AuthMiddleware::class,
        'guest' => GuestMiddleware::class,
        'csrf' => CsrfMiddleware::class,
        'throttle' => RateLimitMiddleware::class,
        'signed' => ValidateSignature::class,
    ],
```

- [ ] **Step 4: Run middleware pipeline tests and verify they pass**

Run:

```bash
php test tests/MiddlewarePipelineTest.php
```

Expected: PASS. The existing `testMiddlewareConfigAliasesPointToRealClasses()` test must also pass, proving `ValidateSignature::class` autoloads.

- [ ] **Step 5: Run route metadata tests for middleware alias compatibility**

Run:

```bash
php test tests/RouteTest.php
```

Expected: PASS.

- [ ] **Step 6: Commit the alias**

```bash
git add config/middleware.php tests/MiddlewarePipelineTest.php
git commit -m "feat: register signed middleware alias"
```

---

### Task 6: Full Verification And Graph Update

**Files:**
- Verify all modified files.
- Update: `graphify-out/GRAPH_REPORT.md`
- Update: `graphify-out/graph.json`

- [ ] **Step 1: Run focused signed URL verification**

Run:

```bash
php test tests/SignedUrlTest.php
php test tests/RequestTest.php
php test tests/MiddlewareTest.php
php test tests/MiddlewarePipelineTest.php
php test tests/RouteTest.php
```

Expected: every command exits `0`.

- [ ] **Step 2: Run the full test suite**

Run:

```bash
php test
```

Expected: exits `0`. Existing PHP 8.5 deprecation notices may appear; they do not fail the command.

- [ ] **Step 3: Update the project graph**

Run:

```bash
graphify update .
```

Expected: exits `0` and rebuilds `graphify-out/graph.json` plus `graphify-out/GRAPH_REPORT.md`.

- [ ] **Step 4: Check whitespace and status**

Run:

```bash
git diff --check
git status --short
```

Expected: `git diff --check` exits `0`. `git status --short` only shows expected code, test, config, and graphify files.

- [ ] **Step 5: Commit verification graph updates if graph files changed**

If `git status --short` shows tracked graphify changes, run:

```bash
git add graphify-out/GRAPH_REPORT.md graphify-out/graph.json
git commit -m "chore: update graph after signed URLs"
```

If `git status --short` shows no graphify changes, skip this commit.

- [ ] **Step 6: Final implementation checkpoint**

Run:

```bash
git log --oneline -5
git status --short
```

Expected: recent commits include the signed URL generation, request validation, middleware, and alias commits. The working tree is clean before requesting review or merging.

## Self-Review

- Spec coverage:
  - `URL::signedRoute()` and `URL::temporarySignedRoute()` are covered by Task 1 and Task 2.
  - `Request::hasValidSignature()` is covered by Task 3.
  - `ValidateSignature` and HTTP 403 invalid behavior are covered by Task 4.
  - Default `signed` alias is covered by Task 5.
  - Query sorting, reserved keys, expiration, tampering, missing signature, and missing key behavior are covered by Tasks 1 through 4.
- Placeholder scan:
  - The plan contains no unresolved implementation placeholders.
  - Every code-changing step includes concrete code or exact snippets.
- Type consistency:
  - `temporarySignedRoute()` uses `DateTimeInterface|int` consistently in the spec, facade, helper, and tests.
  - `signature` and `expires` reserved keys match across generation and validation.
  - Middleware uses `Bin\Exception\HttpException` with status code `403`, matching the middleware tests.

