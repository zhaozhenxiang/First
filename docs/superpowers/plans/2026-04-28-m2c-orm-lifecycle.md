# M2-C ORM Lifecycle Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stabilize the ORM data-access lifecycle for model lookup, hydration, persistence, explicit not-found failures, refresh failures, and query helper semantics.

**Architecture:** Keep the existing `Model` / `QueryBuilder` structure and make surgical lifecycle fixes instead of replacing the ORM. Centralize model hydration on a public model lifecycle method, make primary-key semantics come from the model, and expose a data-layer not-found exception that upper layers can translate without message-string guessing.

**Tech Stack:** PHP 8.5, PDO SQLite in-memory tests, existing `Bin\Database\Model`, `Bin\Database\QueryBuilder`, `Bin\Exception\ExceptionHandler`, `Bin\Route\RouteBinding`, project `php test` runner, graphify.

---

## Context and Constraints

- Worktree: `/home/x/src/install/php/First/.worktrees/m2c-orm-lifecycle-plan`
- Branch: `m2c-orm-lifecycle-plan`
- Base: `c916fe4 test: strengthen protected identity input chain coverage`
- Spec: `docs/superpowers/specs/2026-04-18-m2c-orm-lifecycle-spec.md`
- Baseline verification already run in this worktree: `php test` passed with `1832` tests, `36` skipped, `1796` passed.
- The project AGENTS instructions require code-review-graph/GitNexus first. MCP resources were unavailable when this plan was written. Before editing any symbol, implementers must try the relevant GitNexus impact command if available; if unavailable, record that in the task summary and continue with local graph/report/file context.
- Do not delete any existing worktree. The user explicitly requested preserving worktrees.
- Use TDD. For every production code change below, write the failing test first and verify the expected failure before implementation.

## File Structure

- Create: `bin/Database/ModelNotFoundException.php`
  Responsibility: Typed ORM not-found exception with model class and searched identifiers, while preserving `InvalidArgumentException` compatibility for existing callers.
- Modify: `bin/Database/Model.php`
  Responsibility: Model-level primary-key lookup signatures, non-incrementing insert lifecycle, public hydration lifecycle, `findOrFail()` exception, `refreshOrFail()`.
- Modify: `bin/Database/QueryBuilder.php`
  Responsibility: Primary-key-aware `find()` / `findMany()` and hydration through `Model::newFromBuilder()`.
- Modify: `bin/Exception/ExceptionHandler.php`
  Responsibility: Render `ModelNotFoundException` through the existing 404 HTTP/JSON boundary and suppress normal reporting.
- Modify: `bin/Route/RouteBinding.php`
  Responsibility: Translate `ModelNotFoundException` to `NotFoundHttpException` directly instead of relying only on message-string heuristics.
- Create: `tests/OrmLifecycleTest.php`
  Responsibility: M2-C lifecycle regression coverage for custom keys, hydration, persistence state, explicit not-found exceptions, upper-layer exception rendering, and helper semantics.

---

### Task 1: Primary-Key-Aware Lookup and Non-Incrementing Persistence

**Files:**
- Create: `tests/OrmLifecycleTest.php`
- Modify: `bin/Database/Model.php`
- Modify: `bin/Database/QueryBuilder.php`

- [ ] **Step 1: Run impact analysis for edited symbols**

Run if GitNexus MCP is available:

```text
gitnexus_impact({target: "Bin\\Database\\Model::find", direction: "upstream"})
gitnexus_impact({target: "Bin\\Database\\Model::performInsert", direction: "upstream"})
gitnexus_impact({target: "Bin\\Database\\QueryBuilder::find", direction: "upstream"})
gitnexus_impact({target: "Bin\\Database\\QueryBuilder::findMany", direction: "upstream"})
```

Expected: direct callers include ORM tests, route binding/model consumers, and static model forwarding. If GitNexus is unavailable, state that in the task summary and rely on local `rg` plus focused tests.

- [ ] **Step 2: Write failing custom primary-key lifecycle tests**

Create `tests/OrmLifecycleTest.php` with this initial content:

```php
<?php

declare(strict_types=1);

namespace Tests;

use Bin\Database\Collection;
use Bin\Database\Model;
use Bin\Testing\TestCase;
use PDO;

class OrmLifecycleTest extends TestCase
{
    private PDO $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = new PDO('sqlite::memory:');
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->connection->exec('
            CREATE TABLE orm_lifecycle_tokens (
                uuid TEXT PRIMARY KEY,
                label TEXT NOT NULL,
                active INTEGER NOT NULL DEFAULT 1
            )
        ');
        $this->connection->exec(
            "INSERT INTO orm_lifecycle_tokens (uuid, label, active) VALUES ('tok_1', 'First token', 1)"
        );
        $this->connection->exec(
            "INSERT INTO orm_lifecycle_tokens (uuid, label, active) VALUES ('tok_2', 'Second token', 0)"
        );

        OrmLifecycleToken::resetBooted();
        OrmLifecycleToken::flushEventListeners();
        OrmLifecycleToken::setConnection($this->connection);
    }

    protected function tearDown(): void
    {
        OrmLifecycleToken::flushEventListeners();
        OrmLifecycleToken::resetBooted();
        OrmLifecycleToken::setConnection(null);

        parent::tearDown();
    }

    public function testFindUsesModelPrimaryKeyForStringKeys(): void
    {
        $token = OrmLifecycleToken::find('tok_1');

        $this->assertInstanceOf(OrmLifecycleToken::class, $token);
        $this->assertSame('tok_1', $token->uuid);
        $this->assertSame('First token', $token->label);
        $this->assertTrue($token->exists);
        $this->assertTrue($token->isClean());
    }

    public function testFindManyUsesModelPrimaryKeyForStringKeys(): void
    {
        $tokens = OrmLifecycleToken::findMany(['tok_1', 'tok_2']);

        $this->assertInstanceOf(Collection::class, $tokens);
        $this->assertCount(2, $tokens);
        $this->assertSame(['tok_1', 'tok_2'], $tokens->pluck('uuid'));
    }

    public function testNonIncrementingSavePreservesCallerProvidedPrimaryKey(): void
    {
        $token = new OrmLifecycleToken([
            'uuid' => 'tok_3',
            'label' => 'Third token',
            'active' => 1,
        ]);

        $this->assertFalse($token->exists);

        $this->assertTrue($token->save());

        $this->assertTrue($token->exists);
        $this->assertTrue($token->wasRecentlyCreated());
        $this->assertSame('tok_3', $token->uuid);
        $this->assertTrue($token->isClean());
        $this->assertSame('Third token', OrmLifecycleToken::find('tok_3')->label);
    }
}

class OrmLifecycleToken extends Model
{
    protected string $table = 'orm_lifecycle_tokens';
    protected string $primaryKey = 'uuid';
    protected string $keyType = 'string';
    protected bool $incrementing = false;
    protected bool $timestamps = false;
    protected array $guarded = [];
}

return new OrmLifecycleTest();
```

- [ ] **Step 3: Verify the tests fail for the expected reasons**

Run:

```bash
php test tests/OrmLifecycleTest.php
```

Expected: failures show custom-key lookup still searches `id` or rejects non-int IDs, and non-incrementing `save()` does not preserve `tok_3`.

- [ ] **Step 4: Make `Model` lookup signatures accept the model key type**

In `bin/Database/Model.php`, change the finder signatures and bodies to:

```php
public static function find(mixed $id): ?self
{
    return static::query()->find($id);
}

public static function findMany(array $ids): Collection
{
    return static::query()->findMany($ids);
}

public static function findOrFail(mixed $id): self
{
    $result = static::find($id);

    if ($result === null) {
        throw new InvalidArgumentException("No query results for model [{$id}]");
    }

    return $result;
}

public static function findOrNew(mixed $id): self
{
    return static::find($id) ?? new static();
}
```

Keep the existing exception type in this task. Task 3 will replace it with a typed data-layer exception after tests define that boundary.

- [ ] **Step 5: Make `QueryBuilder` use the model key name**

In `bin/Database/QueryBuilder.php`, replace `find()` and `findMany()` with:

```php
public function find(mixed $id, ?string $column = null): mixed
{
    return $this->where($column ?? $this->getModelKeyName(), $id)->first();
}

public function findMany(array $ids, ?string $column = null): Collection
{
    if (empty($ids)) {
        return new Collection();
    }

    return $this->whereIn($column ?? $this->getModelKeyName(), $ids)->get();
}
```

Add this private helper near the finder methods:

```php
private function getModelKeyName(): string
{
    if ($this->modelClass !== '' && is_subclass_of($this->modelClass, Model::class)) {
        /** @var Model $model */
        $model = new $this->modelClass();
        return $model->getKeyName();
    }

    return 'id';
}
```

- [ ] **Step 6: Preserve non-incrementing primary keys during insert**

In `bin/Database/Model.php`, update `performInsert()` so incrementing models still use `insertGetId()`, and non-incrementing models use `insert()` without overwriting the caller-provided key:

```php
protected function performInsert(QueryBuilder $query): bool
{
    if ($this->fireModelEvent('creating') === false) {
        return false;
    }

    if ($this->timestamps) {
        $this->setCreatedAt();
        $this->setUpdatedAt();
    }

    $attributes = $this->getAttributes();

    if ($this->getIncrementing()) {
        $id = $query->insertGetId($attributes);
        $this->setAttribute($this->getKeyName(), $id);
    } else {
        $query->insert($attributes);
    }

    $this->exists = true;
    $this->wasRecentlyCreated = true;

    $this->fireModelEvent('created');

    return true;
}
```

- [ ] **Step 7: Verify Task 1 tests pass**

Run:

```bash
php test tests/OrmLifecycleTest.php
```

Expected: the three Task 1 tests pass.

- [ ] **Step 8: Run related existing ORM tests**

Run:

```bash
php test tests/ConvenienceFindersTest.php
php test tests/EloquentParityTest.php
php test tests/ErrorPathTest.php
php test tests/RouteEnhancementTest.php
```

Expected: all selected tests pass. Existing PHP 8.5 deprecation warnings may appear but must not produce failures.

- [ ] **Step 9: Commit Task 1**

```bash
git add tests/OrmLifecycleTest.php bin/Database/Model.php bin/Database/QueryBuilder.php
git commit -m "feat: stabilize orm primary key lifecycle"
```

---

### Task 2: Public Hydration Lifecycle

**Files:**
- Modify: `tests/OrmLifecycleTest.php`
- Modify: `bin/Database/Model.php`
- Modify: `bin/Database/QueryBuilder.php`

- [ ] **Step 1: Run impact analysis for hydration symbols**

Run if GitNexus MCP is available:

```text
gitnexus_impact({target: "Bin\\Database\\QueryBuilder::hydrateModel", direction: "upstream"})
gitnexus_impact({target: "Bin\\Database\\Model", direction: "upstream"})
```

Expected: affected callers include query result hydration, eager loading paths, aggregate hydration paths, and model tests.

- [ ] **Step 2: Add failing hydration lifecycle tests**

Append these tests to `OrmLifecycleTest` before the closing class brace:

```php
public function testHydrateBypassesMassAssignmentAndSynchronizesOriginal(): void
{
    $models = OrmLifecycleGuardedToken::hydrate([
        ['uuid' => 'raw_1', 'label' => 'Raw guarded token', 'active' => 1],
    ]);

    $this->assertInstanceOf(Collection::class, $models);
    $this->assertCount(1, $models);

    $model = $models->first();

    $this->assertInstanceOf(OrmLifecycleGuardedToken::class, $model);
    $this->assertTrue($model->exists);
    $this->assertFalse($model->wasRecentlyCreated());
    $this->assertSame('raw_1', $model->uuid);
    $this->assertSame('Raw guarded token', $model->label);
    $this->assertSame($model->getAttributes(), $model->getOriginal());
    $this->assertTrue($model->isClean());
}

public function testQueryHydrationUsesModelLifecycleAndFiresRetrievedOnce(): void
{
    $retrieved = [];
    OrmLifecycleToken::retrieved(function (OrmLifecycleToken $token) use (&$retrieved): void {
        $retrieved[] = $token->uuid;
    });

    $token = OrmLifecycleToken::where('uuid', 'tok_1')->first();

    $this->assertInstanceOf(OrmLifecycleToken::class, $token);
    $this->assertSame(['tok_1'], $retrieved);
    $this->assertTrue($token->exists);
    $this->assertTrue($token->isClean());
}
```

Append this model class after `OrmLifecycleToken`:

```php
class OrmLifecycleGuardedToken extends OrmLifecycleToken
{
    protected array $guarded = ['*'];
}
```

- [ ] **Step 3: Verify the hydration tests fail**

Run:

```bash
php test tests/OrmLifecycleTest.php --filter=Hydrat
```

Expected: failure because `Model::hydrate()` does not exist yet.

- [ ] **Step 4: Add public model hydration APIs**

In `bin/Database/Model.php`, add these methods after the constructor:

```php
/**
 * Hydrate a model instance from trusted database attributes.
 */
public function newFromBuilder(array $attributes): static
{
    $model = new static();
    $model->setRawAttributes($attributes);
    $model->exists = true;
    $model->wasRecentlyCreated = false;
    $model->fireModelEvent('retrieved');

    return $model;
}

/**
 * Hydrate a collection of model instances from trusted database rows.
 */
public static function hydrate(array $items): Collection
{
    $instance = new static();

    return new Collection(array_map(
        fn (array $attributes): static => $instance->newFromBuilder($attributes),
        $items
    ));
}
```

- [ ] **Step 5: Route QueryBuilder hydration through the model API**

In `bin/Database/QueryBuilder.php`, replace `hydrateModel()` with:

```php
protected function hydrateModel(array $attributes): Model
{
    /** @var Model $model */
    $model = new $this->modelClass();

    return $model->newFromBuilder($attributes);
}
```

- [ ] **Step 6: Verify Task 2 tests pass**

Run:

```bash
php test tests/OrmLifecycleTest.php
```

Expected: Task 1 and Task 2 tests pass.

- [ ] **Step 7: Run related ORM tests**

Run:

```bash
php test tests/EloquentParityTest.php
php test tests/ModelEventTest.php
php test tests/LoadAggregateTest.php
php test tests/RelationshipQueryTest.php
```

Expected: all selected tests pass.

- [ ] **Step 8: Commit Task 2**

```bash
git add tests/OrmLifecycleTest.php bin/Database/Model.php bin/Database/QueryBuilder.php
git commit -m "feat: expose orm hydration lifecycle"
```

---

### Task 3: Typed ORM Not-Found Boundary

**Files:**
- Create: `bin/Database/ModelNotFoundException.php`
- Modify: `tests/OrmLifecycleTest.php`
- Modify: `bin/Database/Model.php`
- Modify: `bin/Database/QueryBuilder.php`
- Modify: `bin/Exception/ExceptionHandler.php`
- Modify: `bin/Route/RouteBinding.php`

- [ ] **Step 1: Run impact analysis for exception boundary symbols**

Run if GitNexus MCP is available:

```text
gitnexus_impact({target: "Bin\\Database\\Model::findOrFail", direction: "upstream"})
gitnexus_impact({target: "Bin\\Database\\QueryBuilder::findOrFail", direction: "upstream"})
gitnexus_impact({target: "Bin\\Exception\\ExceptionHandler::getStatus", direction: "upstream"})
gitnexus_impact({target: "Bin\\Route\\RouteBinding::resolveFromClass", direction: "upstream"})
```

Expected: affected callers include route model binding, exception rendering, existing finder tests, and M2-B controller dispatch integration.

- [ ] **Step 2: Add failing typed not-found tests**

Add these imports to `tests/OrmLifecycleTest.php`:

```php
use Bin\App\App;
use Bin\Database\ModelNotFoundException;
use Bin\Exception\ExceptionHandler;
use Bin\Exception\NotFoundHttpException;
use Bin\Request\Request;
use Bin\Route\RouteBinding;
```

Append these tests to `OrmLifecycleTest`:

```php
public function testFindOrFailThrowsModelNotFoundExceptionWithModelAndIds(): void
{
    try {
        OrmLifecycleToken::findOrFail('missing_token');
        $this->fail('Expected ModelNotFoundException was not thrown');
    } catch (ModelNotFoundException $e) {
        $this->assertSame(OrmLifecycleToken::class, $e->getModel());
        $this->assertSame(['missing_token'], $e->getIds());
        $this->assertStringContainsString(OrmLifecycleToken::class, $e->getMessage());
        $this->assertStringContainsString('missing_token', $e->getMessage());
    }
}

public function testQueryBuilderFindOrFailThrowsModelNotFoundException(): void
{
    try {
        OrmLifecycleToken::query()->findOrFail('missing_token');
        $this->fail('Expected ModelNotFoundException was not thrown');
    } catch (ModelNotFoundException $e) {
        $this->assertSame(OrmLifecycleToken::class, $e->getModel());
        $this->assertSame(['missing_token'], $e->getIds());
    }
}

public function testRouteBindingTranslatesModelNotFoundExceptionToHttp404(): void
{
    RouteBinding::model('token', OrmLifecycleToken::class);

    try {
        RouteBinding::resolve('token', 'missing_token');
        $this->fail('Expected NotFoundHttpException was not thrown');
    } catch (NotFoundHttpException $e) {
        $this->assertStringContainsString(OrmLifecycleToken::class, $e->getMessage());
    } finally {
        RouteBinding::clear();
    }
}

public function testExceptionHandlerRendersModelNotFoundAsJson404(): void
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
        $response = $handler->render(new ModelNotFoundException(OrmLifecycleToken::class, ['missing_token']));

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeader('Content-Type'));
        $this->assertStringContainsString('"status":404', (string) $response);
        $this->assertStringContainsString('Not Found', (string) $response);
    } finally {
        $app->forget(Request::class);
        $app->singleton(Request::class, Request::class);
    }
}
```

- [ ] **Step 3: Verify the not-found tests fail**

Run:

```bash
php test tests/OrmLifecycleTest.php --filter=NotFound
php test tests/OrmLifecycleTest.php --filter=FindOrFail
```

Expected: failures because `ModelNotFoundException` does not exist and `findOrFail()` still throws `InvalidArgumentException`.

- [ ] **Step 4: Add the typed ORM exception**

Create `bin/Database/ModelNotFoundException.php`:

```php
<?php

declare(strict_types=1);

namespace Bin\Database;

use InvalidArgumentException;

class ModelNotFoundException extends InvalidArgumentException
{
    /** @var array<int, mixed> */
    private array $ids;

    /**
     * @param class-string<Model> $model
     * @param array<int, mixed>|mixed $ids
     */
    public function __construct(
        private string $model,
        array|int|string|null $ids = [],
        ?\Throwable $previous = null
    ) {
        $this->ids = is_array($ids) ? array_values($ids) : [$ids];

        parent::__construct($this->buildMessage(), 0, $previous);
    }

    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * @return array<int, mixed>
     */
    public function getIds(): array
    {
        return $this->ids;
    }

    private function buildMessage(): string
    {
        $message = "No query results for model [{$this->model}]";

        if ($this->ids !== []) {
            $message .= ' ' . implode(', ', array_map('strval', $this->ids));
        }

        return $message;
    }
}
```

- [ ] **Step 5: Throw `ModelNotFoundException` from Model and QueryBuilder**

In `bin/Database/Model.php`, keep the existing `use InvalidArgumentException;` for `sole()`, then update `findOrFail()`:

```php
public static function findOrFail(mixed $id): self
{
    $result = static::find($id);

    if ($result === null) {
        throw new ModelNotFoundException(static::class, [$id]);
    }

    return $result;
}
```

In `bin/Database/QueryBuilder.php`, update `findOrFail()`:

```php
public function findOrFail(mixed $id): mixed
{
    $result = $this->find($id);

    if ($result === null) {
        throw new ModelNotFoundException($this->modelClass ?: Model::class, [$id]);
    }

    return $result;
}
```

Add `use Bin\Database\ModelNotFoundException;` if the file needs an explicit import.

- [ ] **Step 6: Teach upper layers the typed exception**

In `bin/Exception/ExceptionHandler.php`, add:

```php
use Bin\Database\ModelNotFoundException;
```

Add `ModelNotFoundException::class` to `$this->dontReport` in the constructor:

```php
$this->dontReport = [
    AuthenticationException::class,
    ValidationException::class,
    NotFoundHttpException::class,
    ModelNotFoundException::class,
];
```

Add this branch at the top of `getStatus()` before `NotFoundHttpException`:

```php
if ($e instanceof ModelNotFoundException) {
    return 404;
}
```

In `bin/Route/RouteBinding.php`, add a catch before the existing `InvalidArgumentException` catch in both `resolveFromClass()` and `resolveWithCallback()`:

```php
} catch (\Bin\Database\ModelNotFoundException $e) {
    throw new \Bin\Exception\NotFoundHttpException($e->getMessage(), $e);
}
```

- [ ] **Step 7: Verify Task 3 tests pass**

Run:

```bash
php test tests/OrmLifecycleTest.php
```

Expected: all M2-C lifecycle tests added so far pass.

- [ ] **Step 8: Run related existing tests**

Run:

```bash
php test tests/ConvenienceFindersTest.php
php test tests/ErrorPathTest.php
php test tests/RouteEnhancementTest.php
php test tests/ExceptionHandlerTest.php
```

Expected: all selected tests pass. Existing tests that catch `InvalidArgumentException` continue to pass because `ModelNotFoundException` extends `InvalidArgumentException`.

- [ ] **Step 9: Commit Task 3**

```bash
git add bin/Database/ModelNotFoundException.php tests/OrmLifecycleTest.php bin/Database/Model.php bin/Database/QueryBuilder.php bin/Exception/ExceptionHandler.php bin/Route/RouteBinding.php
git commit -m "feat: add typed orm not found boundary"
```

---

### Task 4: Explicit Refresh Failure and Documented `updateWhere` Semantics

**Files:**
- Modify: `tests/OrmLifecycleTest.php`
- Modify: `bin/Database/Model.php`

- [ ] **Step 1: Run impact analysis for refresh and update helper symbols**

Run if GitNexus MCP is available:

```text
gitnexus_impact({target: "Bin\\Database\\Model::refresh", direction: "upstream"})
gitnexus_impact({target: "Bin\\Database\\Model::updateWhere", direction: "upstream"})
```

Expected: affected callers include ORM tests and any static data-access helper usage. Local `rg "updateWhere"` should show no production callers beyond docs before changing semantics.

- [ ] **Step 2: Add failing refresh and helper semantics tests**

Append these tests to `OrmLifecycleTest`:

```php
public function testRefreshOrFailThrowsWhenPersistedRowDisappears(): void
{
    $token = OrmLifecycleToken::findOrFail('tok_1');
    $this->connection->exec("DELETE FROM orm_lifecycle_tokens WHERE uuid = 'tok_1'");

    try {
        $token->refreshOrFail();
        $this->fail('Expected ModelNotFoundException was not thrown');
    } catch (ModelNotFoundException $e) {
        $this->assertSame(OrmLifecycleToken::class, $e->getModel());
        $this->assertSame(['tok_1'], $e->getIds());
    }
}

public function testRefreshOrFailReloadsDatabaseStateAndClearsDirtyAttributes(): void
{
    $token = OrmLifecycleToken::findOrFail('tok_1');
    $token->label = 'Dirty local label';
    $this->assertTrue($token->isDirty('label'));

    $this->connection->exec("UPDATE orm_lifecycle_tokens SET label = 'Reloaded token' WHERE uuid = 'tok_1'");

    $same = $token->refreshOrFail();

    $this->assertSame($token, $same);
    $this->assertSame('Reloaded token', $token->label);
    $this->assertTrue($token->isClean());
}

public function testUpdateWhereTreatsFirstArgumentAsConditionsAndSecondAsValues(): void
{
    $affected = OrmLifecycleToken::updateWhere(
        ['uuid' => 'tok_2'],
        ['label' => 'Updated through helper', 'active' => 1]
    );

    $this->assertSame(1, $affected);

    $token = OrmLifecycleToken::findOrFail('tok_2');
    $this->assertSame('Updated through helper', $token->label);
    $this->assertSame(1, $token->active);
}
```

- [ ] **Step 3: Verify these tests fail**

Run:

```bash
php test tests/OrmLifecycleTest.php --filter=RefreshOrFail
php test tests/OrmLifecycleTest.php --filter=UpdateWhere
```

Expected: `refreshOrFail()` is undefined, and `updateWhere()` updates the wrong columns because its implementation currently treats the first argument as values.

- [ ] **Step 4: Add `refreshOrFail()`**

In `bin/Database/Model.php`, add this method after `refresh()`:

```php
public function refreshOrFail(): self
{
    if (!$this->exists) {
        throw new ModelNotFoundException(static::class, [$this->getKey()]);
    }

    $fresh = $this->fresh();

    if ($fresh === null) {
        throw new ModelNotFoundException(static::class, [$this->getKey()]);
    }

    $this->attributes = $fresh->getAttributes();
    $this->original = $fresh->getOriginal();
    $this->changes = [];

    return $this;
}
```

- [ ] **Step 5: Align `updateWhere()` with documented helper semantics**

In `bin/Database/Model.php`, replace `updateWhere()` with:

```php
public static function updateWhere(array $where, array $values): int
{
    return static::query()->where($where)->update($values);
}
```

Do not add a compatibility overload. Existing docs show `updateWhere($where, $values)`, and local search should confirm no production caller depends on the old reversed implementation.

- [ ] **Step 6: Verify Task 4 tests pass**

Run:

```bash
php test tests/OrmLifecycleTest.php
```

Expected: all lifecycle tests pass.

- [ ] **Step 7: Run related existing tests**

Run:

```bash
php test tests/EloquentParityTest.php
php test tests/ConvenienceFindersTest.php
php test tests/ErrorPathTest.php
```

Expected: all selected tests pass.

- [ ] **Step 8: Commit Task 4**

```bash
git add tests/OrmLifecycleTest.php bin/Database/Model.php
git commit -m "feat: clarify orm refresh and update helper semantics"
```

---

### Task 5: End-to-End M2-C Data Access Regression and Final Verification

**Files:**
- Modify: `tests/OrmLifecycleTest.php`
- Possibly modify: files changed by earlier tasks if this integration test reveals a bug.

- [ ] **Step 1: Add an end-to-end lifecycle regression test**

Append this test to `OrmLifecycleTest`:

```php
public function testM2cDataAccessMainLifecycleIsStableEndToEnd(): void
{
    $created = new OrmLifecycleToken([
        'uuid' => 'tok_e2e',
        'label' => 'Created token',
        'active' => 1,
    ]);

    $this->assertTrue($created->save());
    $this->assertTrue($created->exists);
    $this->assertTrue($created->wasRecentlyCreated());
    $this->assertTrue($created->isClean());

    $queried = OrmLifecycleToken::where('active', 1)
        ->whereIn('uuid', ['tok_1', 'tok_e2e'])
        ->orderBy('uuid')
        ->get();

    $this->assertCount(2, $queried);
    $this->assertContains('tok_e2e', $queried->pluck('uuid'));
    $this->assertContains('tok_1', $queried->pluck('uuid'));
    $this->assertInstanceOf(OrmLifecycleToken::class, $queried->first());

    $created->label = 'Updated locally';
    $this->assertTrue($created->isDirty('label'));
    $this->assertTrue($created->save());
    $this->assertTrue($created->isClean());

    $this->connection->exec("UPDATE orm_lifecycle_tokens SET label = 'Externally updated' WHERE uuid = 'tok_e2e'");
    $created->refreshOrFail();

    $this->assertSame('Externally updated', $created->label);
    $this->assertTrue($created->delete());
    $this->assertFalse($created->exists);
    $this->assertNull(OrmLifecycleToken::find('tok_e2e'));
}
```

- [ ] **Step 2: Run the integration test**

Run:

```bash
php test tests/OrmLifecycleTest.php --filter=M2cDataAccessMainLifecycle
```

Expected: pass after Tasks 1-4. If it fails, fix the production code with the smallest change and rerun the focused test.

- [ ] **Step 3: Run the complete M2-C focused test file**

Run:

```bash
php test tests/OrmLifecycleTest.php
```

Expected: all `OrmLifecycleTest` tests pass.

- [ ] **Step 4: Run ORM and upper-boundary regression sweep**

Run:

```bash
php test tests/ConvenienceFindersTest.php
php test tests/EloquentParityTest.php
php test tests/ErrorPathTest.php
php test tests/RouteEnhancementTest.php
php test tests/ExceptionHandlerTest.php
php test tests/RelationshipQueryTest.php
php test tests/LoadAggregateTest.php
php test tests/ModelEventTest.php
php test tests/SoftDeletesTest.php
```

Expected: all selected tests pass. Existing PHP 8.5 deprecation warnings may appear but must not produce failures.

- [ ] **Step 5: Run formatting and full suite verification**

Run:

```bash
git diff --check
php test
```

Expected: `git diff --check` exits 0. `php test` passes with no failures; skipped count may remain at the repository baseline.

- [ ] **Step 6: Refresh graphify**

Run:

```bash
graphify update .
git status --short
```

Expected: graphify rebuilds successfully. If tracked graph files change, inspect the diff and commit them with the task changes. If no tracked files change, note that graphify left the worktree clean.

- [ ] **Step 7: Commit Task 5**

```bash
git add tests/OrmLifecycleTest.php
git commit -m "test: cover m2c orm lifecycle chain"
```

If Task 5 required production fixes, include those files in the same commit and use:

```bash
git add tests/OrmLifecycleTest.php bin/Database/Model.php bin/Database/QueryBuilder.php bin/Exception/ExceptionHandler.php bin/Route/RouteBinding.php
git commit -m "fix: complete m2c orm lifecycle chain"
```

---

## Self-Review Notes

### Spec Coverage

- Model creation, query, hydration, persistence, refresh, and delete lifecycle: Tasks 1, 2, 4, 5.
- Common query and result-return semantics: Tasks 1, 4, 5.
- Data-layer exception boundary with upper HTTP/JSON integration: Task 3.
- Reuse by route binding and upper layers without rewriting M2-A/M2-B boundaries: Task 3.
- Regression tests for the data-access main chain: Task 5.

### Placeholder Scan

Search command:

```bash
php -r '$path="docs/superpowers/plans/2026-04-28-m2c-orm-lifecycle.md"; $terms=["TB"."D","TO"."DO","待"."定","占"."位","implement "."later","fill "."in details","Similar "."to","适"."当"]; $text=file_get_contents($path); foreach ($terms as $term) { if (str_contains($text, $term)) { fwrite(STDERR, "placeholder: {$term}\n"); exit(1); } }'
```

Expected: no matches.

### Type Consistency

- `Model::find()`, `Model::findOrFail()`, `Model::findOrNew()`, `QueryBuilder::find()`, and `QueryBuilder::findOrFail()` all accept `mixed $id` because models may use integer or string keys.
- `QueryBuilder::findMany()` accepts `array $ids` and chooses the model primary key by default.
- `ModelNotFoundException` extends `InvalidArgumentException` so existing callers that catch `InvalidArgumentException` remain compatible.
- `refreshOrFail()` returns `self` and throws `ModelNotFoundException`; existing `refresh()` remains non-throwing to avoid breaking current callers.
- `updateWhere(array $where, array $values)` matches the documented usage in `docs/ORM.md`.
