# Developer API Controller Response Polish Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Normalize controller, closure, JSON resource, and resource collection return values through the response factory so they produce predictable `Response` objects.

**Architecture:** Keep `ResponseFactory` as the single response normalization boundary. `ControllerDispatcher` already passes controller and closure return values through the factory, so this plan strengthens the factory and then routes `JsonResource::toResponse()` and `ResourceCollection::toResponse()` through the same JSON path.

**Tech Stack:** PHP 8.3+, First framework `Response`, `ResponseFactory`, `ControllerDispatcher`, `JsonResource`, `ResourceCollection`, custom `php test` runner, graphify knowledge graph.

---

## Spec

- Source spec: `docs/superpowers/specs/2026-05-31-developer-api-controller-response-polish-design.md`
- Parent spec: `docs/superpowers/specs/2026-05-17-developer-api-routing-polish-design.md`
- Follow-up phase: "Broader facade/API polish across request, response, validation, resources, and controller helpers."
- This slice intentionally does not add request validation helpers, FormRequest failure response changes, validation facade expansion, a base controller class, or content negotiation.

## File Structure

- Modify `bin/Response/ResponseFactory.php`
  - Responsibility: normalize arrays and `JsonSerializable` payloads into JSON responses, preserve scalar/null/`Response` behavior, and keep redirect construction centralized.
- Modify `bin/Resource/JsonResource.php`
  - Responsibility: make `toResponse()` use `ResponseFactory::json()` so direct resource responses match controller return behavior.
- Modify `bin/Resource/ResourceCollection.php`
  - Responsibility: make `toResponse()` use `ResponseFactory::json()` so collection responses match closure/controller return behavior.
- Modify `tests/ResponseTest.php`
  - Coverage: `ResponseFactory::make()` and `ResponseFactory::json()` support `JsonSerializable`, and JSON header defaults can be overridden by explicit caller headers.
- Modify `tests/DispatcherIntegrationTest.php`
  - Coverage: controller methods returning `JsonResource` and closures returning `ResourceCollection` are normalized into JSON `Response` objects.
- Modify `tests/ResourceTest.php`
  - Coverage: resource and collection `toResponse()` methods emit JSON body, JSON content type, and preserve supplied status codes.
- Update `graphify-out/` by running `graphify update .` after code changes.

---

### Task 1: Response Factory JSON-Serializable Normalization

**Files:**
- Modify: `tests/ResponseTest.php`
- Modify: `tests/DispatcherIntegrationTest.php`
- Modify: `bin/Response/ResponseFactory.php`

- [ ] **Step 1: Add failing response factory tests**

In `tests/ResponseTest.php`, add these methods inside `class ResponseTest` after `testResponseFactoryCastsIntegerPayloadsToStrings()`:

```php
    public function testResponseFactoryMakesJsonSerializablePayloadsJsonResponses(): void
    {
        $payload = new class implements \JsonSerializable {
            public function jsonSerialize(): array
            {
                return ['status' => 'ok'];
            }
        };

        $response = (new ResponseFactory())->make($payload, 202);

        $this->assertEquals(202, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeader('Content-Type'));
        $this->assertEquals(['status' => 'ok'], json_decode($response->getContent(), true));
    }

    public function testResponseFactoryJsonAcceptsJsonSerializablePayloads(): void
    {
        $payload = new class implements \JsonSerializable {
            public function jsonSerialize(): array
            {
                return ['kind' => 'resource'];
            }
        };

        $response = (new ResponseFactory())->json($payload, 201);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeader('Content-Type'));
        $this->assertEquals(['kind' => 'resource'], json_decode($response->getContent(), true));
    }

    public function testResponseFactoryJsonAllowsCallerContentTypeOverride(): void
    {
        $response = (new ResponseFactory())->json(
            ['ok' => true],
            200,
            ['Content-Type' => 'application/vnd.api+json']
        );

        $this->assertEquals('application/vnd.api+json', $response->getHeader('Content-Type'));
        $this->assertEquals(['ok' => true], json_decode($response->getContent(), true));
    }
```

- [ ] **Step 2: Add failing dispatcher integration tests**

In `tests/DispatcherIntegrationTest.php`, add these methods inside `class DispatcherIntegrationTest` after `testDispatcherUsesResponseFactoryForArrayResults()`:

```php
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
```

- [ ] **Step 3: Run the new focused tests and verify they fail**

Run:

```bash
php test tests/ResponseTest.php
php test tests/DispatcherIntegrationTest.php
```

Expected:
- `tests/ResponseTest.php` fails because `ResponseFactory::json()` currently requires `array` and `ResponseFactory::make()` does not encode `JsonSerializable` payloads.
- `tests/DispatcherIntegrationTest.php` fails because direct `JsonResource` and `ResourceCollection` returns currently produce an empty body or missing JSON content type.

- [ ] **Step 4: Replace `ResponseFactory` with JSON-serializable support**

Replace the full contents of `bin/Response/ResponseFactory.php` with:

```php
<?php

declare(strict_types=1);

namespace Bin\Response;

use JsonSerializable;

class ResponseFactory
{
    public function make(mixed $payload = '', int $status = 200, array $headers = []): Response
    {
        if ($payload instanceof Response) {
            foreach ($headers as $name => $value) {
                $payload->setHeader($name, $value);
            }

            return $payload;
        }

        if (is_array($payload) || $payload instanceof JsonSerializable) {
            return $this->json($payload, $status, $headers);
        }

        if (is_scalar($payload)) {
            $payload = (string) $payload;
        }

        return new Response($payload, $status, $headers);
    }

    public function json(mixed $payload, int $status = 200, array $headers = []): Response
    {
        $headers = array_merge(['Content-Type' => 'application/json'], $headers);

        return new Response(json_encode($payload, JSON_THROW_ON_ERROR), $status, $headers);
    }

    public function redirect(string $url, int $status = 302, array $headers = []): Response
    {
        return $this->make('', $status, ['Location' => $url] + $headers);
    }
}
```

- [ ] **Step 5: Run focused tests and verify they pass**

Run:

```bash
php test tests/ResponseTest.php
php test tests/DispatcherIntegrationTest.php
```

Expected:
- `tests/ResponseTest.php`: all tests pass.
- `tests/DispatcherIntegrationTest.php`: all tests pass.

- [ ] **Step 6: Commit response factory normalization**

Run:

```bash
git add tests/ResponseTest.php tests/DispatcherIntegrationTest.php bin/Response/ResponseFactory.php
git commit -m "feat: normalize json serializable responses"
```

---

### Task 2: Resource `toResponse()` Factory Alignment

**Files:**
- Modify: `tests/ResourceTest.php`
- Modify: `bin/Resource/JsonResource.php`
- Modify: `bin/Resource/ResourceCollection.php`

- [ ] **Step 1: Add failing resource response tests**

In `tests/ResourceTest.php`, add this method after `testResourceToResponse()`:

```php
    public function testResourceToResponseUsesJsonResponseFactory(): void
    {
        $user = ['id' => 1, 'name' => 'John', 'email' => 'john@example.com'];
        $resource = $this->createUserResource($user);

        $response = $resource->toResponse(201);

        $this->assertInstanceOf(\Bin\Response\Response::class, $response);
        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeader('Content-Type'));
        $this->assertEquals([
            'id' => 1,
            'name' => 'John',
            'email' => 'john@example.com',
        ], json_decode($response->getContent(), true));
    }
```

In the same file, add this method after `testResourceCollectionWithPagination()`:

```php
    public function testResourceCollectionToResponseUsesJsonResponseFactory(): void
    {
        $users = [
            ['id' => 1, 'name' => 'John', 'email' => 'john@example.com'],
            ['id' => 2, 'name' => 'Jane', 'email' => 'jane@example.com'],
        ];

        $collection = ResourceCollection::make($users, get_class($this->createUserResource([])))
            ->pagination([
                'total' => 10,
                'per_page' => 2,
                'current_page' => 1,
            ]);

        $response = $collection->toResponse(202);

        $this->assertInstanceOf(\Bin\Response\Response::class, $response);
        $this->assertEquals(202, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeader('Content-Type'));
        $this->assertEquals([
            'data' => [
                ['id' => 1, 'name' => 'John', 'email' => 'john@example.com'],
                ['id' => 2, 'name' => 'Jane', 'email' => 'jane@example.com'],
            ],
            'meta' => [
                'pagination' => [
                    'total' => 10,
                    'per_page' => 2,
                    'current_page' => 1,
                ],
            ],
        ], json_decode($response->getContent(), true));
    }
```

- [ ] **Step 2: Run resource tests and verify the new tests fail**

Run:

```bash
php test tests/ResourceTest.php
```

Expected: FAIL because `JsonResource::toResponse()` and `ResourceCollection::toResponse()` currently instantiate `Response` directly, which does not set a JSON content type for resource objects.

- [ ] **Step 3: Route `JsonResource::toResponse()` through `ResponseFactory`**

In `bin/Resource/JsonResource.php`, add these imports after the namespace declaration:

```php
use Bin\App\App;
use Bin\Response\Response;
use Bin\Response\ResponseFactory;
use ArrayObject;
use JsonSerializable;
```

Replace the existing `toResponse()` method with:

```php
    /**
     * 转换为响应
     */
    public function toResponse(int $status = 200): Response
    {
        return App::getInstance()
            ->make(ResponseFactory::class)
            ->json($this, $status);
    }
```

When applying this edit, remove the old duplicate import lines:

```php
use ArrayObject;
use JsonSerializable;
```

- [ ] **Step 4: Route `ResourceCollection::toResponse()` through `ResponseFactory`**

In `bin/Resource/ResourceCollection.php`, replace the import block after the namespace declaration with:

```php
use Bin\App\App;
use Bin\Response\Response;
use Bin\Response\ResponseFactory;
use Countable;
use IteratorAggregate;
use JsonSerializable;
```

Replace the existing `toResponse()` method with:

```php
    /**
     * 转换为响应
     */
    public function toResponse(int $status = 200): Response
    {
        return App::getInstance()
            ->make(ResponseFactory::class)
            ->json($this, $status);
    }
```

- [ ] **Step 5: Run resource tests and verify they pass**

Run:

```bash
php test tests/ResourceTest.php
```

Expected: all resource tests pass, including resource and collection `toResponse()` JSON header/body assertions.

- [ ] **Step 6: Run response and dispatcher tests again**

Run:

```bash
php test tests/ResponseTest.php
php test tests/DispatcherIntegrationTest.php
```

Expected:
- `tests/ResponseTest.php`: all tests pass.
- `tests/DispatcherIntegrationTest.php`: all tests pass.

- [ ] **Step 7: Commit resource response alignment**

Run:

```bash
git add tests/ResourceTest.php bin/Resource/JsonResource.php bin/Resource/ResourceCollection.php
git commit -m "feat: align resource response serialization"
```

---

### Task 3: Final Verification And Graph Update

**Files:**
- Verify: `bin/Response/ResponseFactory.php`
- Verify: `bin/Resource/JsonResource.php`
- Verify: `bin/Resource/ResourceCollection.php`
- Verify: `tests/ResponseTest.php`
- Verify: `tests/ResourceTest.php`
- Verify: `tests/DispatcherIntegrationTest.php`
- Update if changed: `graphify-out/`

- [ ] **Step 1: Run focused verification**

Run:

```bash
php test tests/ResponseTest.php
php test tests/ResourceTest.php
php test tests/DispatcherIntegrationTest.php
```

Expected:
- `tests/ResponseTest.php`: all tests pass.
- `tests/ResourceTest.php`: all tests pass.
- `tests/DispatcherIntegrationTest.php`: all tests pass.

- [ ] **Step 2: Run the full test suite**

Run:

```bash
php test
```

Expected: the full suite exits with code `0`. Existing PHP deprecation notices may appear, but there must be `0` failed tests.

- [ ] **Step 3: Update the project graph**

Run:

```bash
graphify update .
```

Expected: graph update completes successfully. It may leave no tracked file changes.

- [ ] **Step 4: Check status**

Run:

```bash
git status --short
```

Expected:
- no uncommitted code changes, or
- only graph output changes from `graphify update .`.

- [ ] **Step 5: Commit graph updates if graph files changed**

If `git status --short` shows tracked `graphify-out/` changes, run:

```bash
git add graphify-out
git commit -m "chore: update graph after controller response polish"
```

Expected: a commit is created only when graph files changed.

- [ ] **Step 6: Final implementation checkpoint**

Confirm all acceptance criteria from the spec:

- Controller methods can return `JsonResource` and receive a JSON `Response`.
- Route closures can return `ResourceCollection` and receive a JSON `Response`.
- `JsonResource::toResponse()` emits a JSON body and JSON content type.
- `ResourceCollection::toResponse()` emits a JSON body and JSON content type.
- `ResponseFactory::make()` handles `JsonSerializable` payloads through the JSON response path.
- Existing array, scalar, `Response`, view, null, and redirect response behavior remains compatible.
- Focused response, resource, and dispatcher tests pass.
- Full test suite passes after implementation.

Expected: all items are backed by tests or verification output from the previous steps.
