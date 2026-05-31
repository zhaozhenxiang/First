# Runtime PHP 8.5 Hygiene Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remove PHP 8.5 deprecation noise from framework linting and tests while keeping the full First test suite green.

**Architecture:** Add a focused runtime compatibility test that lints framework files with `E_ALL` and scans for deprecated reflection access calls. Then make surgical signature-only nullable type updates and remove no-op `setAccessible()` calls, without changing runtime behavior. Document the PHP runtime policy and run graphify after code-file changes.

**Tech Stack:** PHP 8.3+, custom `php test` runner, First `Bin\Testing\TestCase`, shell `php -l`, graphify.

---

## Scope

This plan implements Track A from `docs/superpowers/specs/2026-05-31-laravel13-architecture-gap-spec.md`.

In scope:

- Add a regression test for framework PHP deprecation output.
- Add a regression test banning `ReflectionMethod::setAccessible()` and `ReflectionProperty::setAccessible()` usage in `bin/` and `tests/`.
- Convert implicit nullable parameter declarations to explicit nullable types.
- Remove no-op `setAccessible(true)` calls now deprecated on PHP 8.5.
- Document the runtime compatibility policy.

Out of scope:

- Laravel 13 container attributes.
- Routing parity.
- Validation or Blade feature expansion.
- Queue/filesystem/mail driver expansion.
- AI/MCP/search architecture.

## File Structure

- Create: `tests/RuntimeCompatibilityTest.php`
  - Owns runtime hygiene regression checks.
- Modify: `bin/Contracts/ContainerInterface.php`
  - Keeps container API signatures explicit about nullable concrete bindings.
- Modify: `bin/Container/Container.php`
  - Updates container implementation signatures.
- Modify: `bin/App/App.php`
  - Updates App proxy signatures to match the container.
- Modify: `bin/Providers/ServiceProvider.php`
  - Updates provider helper signatures.
- Modify: `bin/Cache/CacheManager.php`
  - Updates deprecated static cache setter TTL signature.
- Modify: `bin/Database/Collection.php`
  - Updates nullable callback signatures.
- Modify: `bin/Database/Migrations/MigrationCreator.php`
  - Updates nullable table signature.
- Modify: `bin/Database/Model/HasRelationships.php`
  - Updates relationship helper nullable key signatures.
- Modify: `bin/Database/Relations/BelongsToMany.php`
  - Updates nullable detach ID signature.
- Modify: `bin/Database/Relations/HasOneOrMany.php`
  - Updates nullable key signature.
- Modify: `bin/Database/Schema/Blueprint.php`
  - Updates nullable schema builder signatures.
- Modify: `bin/Database/Seeders/SeederCreator.php`
  - Updates nullable path signatures.
- Modify: `bin/Log/LogManager.php`
  - Updates nullable channel signatures.
- Modify: `bin/Session/SessionFacade.php`
  - Updates nullable old-input key signature.
- Modify: `bin/Session/SessionManager.php`
  - Updates nullable old-input key signature.
- Modify: `bin/Mail/Mailable.php`
  - Removes no-op reflection `setAccessible(true)` calls.
- Modify: test files containing `setAccessible(true)`
  - Removes no-op reflection calls while leaving reflection reads/writes intact.
- Modify: `docs/Testing.md`
  - Adds runtime compatibility policy.

## Task 1: Runtime Compatibility Regression Test

**Files:**
- Create: `tests/RuntimeCompatibilityTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/RuntimeCompatibilityTest.php` with this exact content:

```php
<?php

declare(strict_types=1);

namespace Tests;

use Bin\Testing\TestCase;

class RuntimeCompatibilityTest extends TestCase
{
    public function testFrameworkFilesLintWithoutDeprecations(): void
    {
        $failures = [];

        foreach ($this->phpFiles(BASE_PATH . '/bin') as $file) {
            $command = sprintf(
                '%s -d error_reporting=32767 -d display_errors=1 -l %s 2>&1',
                escapeshellarg(PHP_BINARY),
                escapeshellarg($file)
            );

            exec($command, $output, $exitCode);
            $text = implode("\n", $output);

            if ($exitCode !== 0 || str_contains($text, 'Deprecated:') || str_contains($text, 'PHP Deprecated:')) {
                $failures[] = $file . "\n" . $text;
            }
        }

        $this->assertEquals([], $failures, "Framework files emitted lint errors or deprecations:\n" . implode("\n\n", $failures));
    }

    public function testNoDeprecatedReflectionSetAccessibleCallsRemain(): void
    {
        $matches = [];
        $deprecatedCall = '->set' . 'Accessible(';

        foreach ([BASE_PATH . '/bin', BASE_PATH . '/tests'] as $directory) {
            foreach ($this->phpFiles($directory) as $file) {
                $contents = file_get_contents($file);
                if ($contents !== false && str_contains($contents, $deprecatedCall)) {
                    $matches[] = $file;
                }
            }
        }

        $this->assertEquals([], $matches, "Deprecated Reflection::setAccessible() calls remain:\n" . implode("\n", $matches));
    }

    /**
     * @return string[]
     */
    private function phpFiles(string $directory): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }
}
```

- [ ] **Step 2: Run the new test to verify it fails**

Run:

```bash
php test tests/RuntimeCompatibilityTest.php --filter=testFrameworkFilesLintWithoutDeprecations
```

Expected: FAIL. The failure output should list framework files such as `bin/Container/Container.php`, `bin/Database/Schema/Blueprint.php`, and `bin/Database/Model/HasRelationships.php` with implicit nullable deprecation messages.

- [ ] **Step 3: Run the reflection scan test to verify it fails**

Run:

```bash
php test tests/RuntimeCompatibilityTest.php --filter=testNoDeprecatedReflectionSetAccessibleCallsRemain
```

Expected: FAIL. The failure output should list files such as `tests/MakeCommandsTest.php`, `tests/RouteTest.php`, `tests/HttpClientTest.php`, and `bin/Mail/Mailable.php`.

- [ ] **Step 4: Commit the failing test**

```bash
git add tests/RuntimeCompatibilityTest.php
git commit -m "test: cover PHP runtime compatibility hygiene"
```

## Task 2: Container, App, Provider, and Manager Nullable Signatures

**Files:**
- Modify: `bin/Contracts/ContainerInterface.php`
- Modify: `bin/Container/Container.php`
- Modify: `bin/App/App.php`
- Modify: `bin/Providers/ServiceProvider.php`
- Modify: `bin/Cache/CacheManager.php`
- Modify: `bin/Log/LogManager.php`
- Modify: `bin/Session/SessionManager.php`
- Modify: `bin/Session/SessionFacade.php`

- [ ] **Step 1: Update container contract signatures**

In `bin/Contracts/ContainerInterface.php`, change the signatures to:

```php
public function bind(string $abstract, callable|string|null $concrete = null, bool $shared = false): void;

public function singleton(string $abstract, callable|string|null $concrete = null): void;
```

- [ ] **Step 2: Update `Container` signatures**

In `bin/Container/Container.php`, change only these signatures:

```php
public function bind(string $abstract, callable|string|null $concrete = null, bool $shared = false): void
public function singleton(string $abstract, callable|string|null $concrete = null): void
public function scoped(string $abstract, callable|string|null $concrete = null): void
public function bindIf(string $abstract, callable|string|null $concrete = null, bool $shared = false): void
public function singletonIf(string $abstract, callable|string|null $concrete = null): void
public function bindAndMake(string $abstract, callable|string|null $concrete = null): object
public function singletonAndMake(string $abstract, callable|string|null $concrete = null): object
```

Do not change method bodies.

- [ ] **Step 3: Update `App` proxy signatures**

In `bin/App/App.php`, change only these signatures:

```php
public function bind(string $abstract, callable|string|null $concrete = null, bool $shared = false): void
public function singleton(string $abstract, callable|string|null $concrete = null): void
public function scoped(string $abstract, callable|string|null $concrete = null): void
public function bindIf(string $abstract, callable|string|null $concrete = null, bool $shared = false): void
public function singletonIf(string $abstract, callable|string|null $concrete = null): void
```

Do not change method bodies.

- [ ] **Step 4: Update service provider helper signatures**

In `bin/Providers/ServiceProvider.php`, change only these signatures:

```php
protected function singleton(string $abstract, callable|string|null $concrete = null): void
protected function bind(string $abstract, callable|string|null $concrete = null, bool $shared = false): void
```

- [ ] **Step 5: Update manager nullable signatures**

Make these exact signature changes:

```php
// bin/Cache/CacheManager.php
public static function set(string $key, mixed $value, ?int $ttl = null): bool

// bin/Log/LogManager.php
public function channelFor(?string $name = null): Logger
public static function channel(?string $name = null): Logger

// bin/Session/SessionManager.php
public function getOldInput(?string $key = null, mixed $default = null): mixed

// bin/Session/SessionFacade.php
public static function getOldInput(?string $key = null, mixed $default = null): mixed
```

- [ ] **Step 6: Run targeted tests**

Run:

```bash
php test tests/ContainerTest.php tests/AppTest.php tests/CacheTest.php tests/LoggerTest.php tests/SessionTest.php
```

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add bin/Contracts/ContainerInterface.php bin/Container/Container.php bin/App/App.php bin/Providers/ServiceProvider.php bin/Cache/CacheManager.php bin/Log/LogManager.php bin/Session/SessionManager.php bin/Session/SessionFacade.php
git commit -m "fix: make runtime nullable signatures explicit"
```

## Task 3: Database, ORM, Validation, and Schema Nullable Signatures

**Files:**
- Modify: `bin/Database/Collection.php`
- Modify: `bin/Database/Migrations/MigrationCreator.php`
- Modify: `bin/Database/Model/HasRelationships.php`
- Modify: `bin/Database/Relations/BelongsToMany.php`
- Modify: `bin/Database/Relations/HasOneOrMany.php`
- Modify: `bin/Database/Schema/Blueprint.php`
- Modify: `bin/Database/Seeders/SeederCreator.php`
- Modify: `bin/Validation/MessageBag.php`
- Modify: `bin/Validation/Validator.php`

- [ ] **Step 1: Update collection and migration signatures**

Make these exact signature changes:

```php
// bin/Database/Collection.php
public function first(?callable $callback = null, mixed $default = null): mixed
public function last(?callable $callback = null, mixed $default = null): mixed
public function sort(?callable $callback = null): self

// bin/Database/Migrations/MigrationCreator.php
public function create(string $name, ?string $table = null): string
```

- [ ] **Step 2: Update relationship helper signatures**

In `bin/Database/Model/HasRelationships.php`, change only signatures to:

```php
protected function hasOne(string $related, ?string $foreignKey = null, ?string $localKey = null): \Bin\Database\Relations\HasOne
protected function hasMany(string $related, ?string $foreignKey = null, ?string $localKey = null): \Bin\Database\Relations\HasMany
protected function belongsTo(string $related, ?string $foreignKey = null, ?string $ownerKey = null, ?string $relation = null): \Bin\Database\Relations\BelongsTo
protected function belongsToMany(
    string $related,
    ?string $table = null,
    ?string $foreignPivotKey = null,
    ?string $relatedPivotKey = null,
    ?string $parentKey = null,
    ?string $relatedKey = null
): \Bin\Database\Relations\BelongsToMany
protected function hasOneThrough(
    string $related,
    string $through,
    ?string $firstKey = null,
    ?string $secondKey = null,
    ?string $localKey = null,
    ?string $secondLocalKey = null
): \Bin\Database\Relations\HasOneThrough
protected function hasManyThrough(
    string $related,
    string $through,
    ?string $firstKey = null,
    ?string $secondKey = null,
    ?string $localKey = null,
    ?string $secondLocalKey = null
): \Bin\Database\Relations\HasManyThrough
protected function morphOne(string $related, string $name, ?string $type = null, ?string $id = null, ?string $localKey = null): Relations\MorphOne
protected function morphMany(string $related, string $name, ?string $type = null, ?string $id = null, ?string $localKey = null): Relations\MorphMany
protected function morphToMany(
    string $related,
    string $name,
    ?string $table = null,
    ?string $foreignPivotKey = null,
    ?string $relatedPivotKey = null,
    ?string $parentKey = null,
    ?string $relatedKey = null
): Relations\MorphToMany
protected function morphedByMany(
    string $related,
    string $name,
    ?string $table = null,
    ?string $foreignPivotKey = null,
    ?string $relatedPivotKey = null,
    ?string $parentKey = null,
    ?string $relatedKey = null
): Relations\MorphToMany
```

Do not change `morphTo()`, because it already uses explicit nullable types.

- [ ] **Step 3: Update relation class signatures**

Make these exact signature changes:

```php
// bin/Database/Relations/HasOneOrMany.php
protected function getKeys(array $models, ?string $key = null): array

// bin/Database/Relations/BelongsToMany.php
public function detach(int|array|null $ids = null): int
```

- [ ] **Step 4: Update validation and seeder signatures**

Make these exact signature changes:

```php
// bin/Validation/Validator.php
public static function int(mixed $value, ?int $min = null, ?int $max = null): int

// bin/Validation/MessageBag.php
public function has(?string $key = null): bool

// bin/Database/Seeders/SeederCreator.php
public function __construct(?string $path = null)
public function create(string $name, ?string $path = null): string
```

- [ ] **Step 5: Update schema blueprint signatures**

In `bin/Database/Schema/Blueprint.php`, change only signatures to:

```php
public function foreign(string $column, ?string $table = null, string $columnOnTable = 'id'): ForeignKey
public function double(string $column, ?int $total = null, ?int $places = null): ColumnDefinition
public function float(string $column, ?int $total = null, ?int $places = null): ColumnDefinition
public function binary(string $column, ?int $length = null): ColumnDefinition
public function dropPrimary(?string $index = null): void
public function primary(string|array $columns, ?string $name = null): void
public function unique(string|array $columns, ?string $name = null, ?string $algorithm = null): void
public function index(string|array $columns, ?string $name = null, ?string $algorithm = null): void
public function fullText(string|array $columns, ?string $name = null, ?string $algorithm = null): void
public function spatialIndex(string|array $columns, ?string $name = null): void
public function foreignKey(
    string|array $columns,
    ?string $name = null,
    ?string $on = null,
    string $references = 'id',
    ?string $onDelete = null,
    ?string $onUpdate = null
): void
```

Do not change method bodies.

- [ ] **Step 6: Run targeted database and validation tests**

Run:

```bash
php test tests/CollectionTest.php tests/MigrationTest.php tests/MigrateCommandTest.php tests/RelationshipQueryTest.php tests/RelationRefactorTest.php tests/ThroughRelationshipTest.php tests/PivotModelTest.php tests/SchemaTest.php tests/ValidationTest.php tests/ValidationEngineTest.php tests/SeederTest.php
```

Expected: PASS.

- [ ] **Step 7: Run the lint-deprecation regression test**

Run:

```bash
php test tests/RuntimeCompatibilityTest.php --filter=testFrameworkFilesLintWithoutDeprecations
```

Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add bin/Database/Collection.php bin/Database/Migrations/MigrationCreator.php bin/Database/Model/HasRelationships.php bin/Database/Relations/BelongsToMany.php bin/Database/Relations/HasOneOrMany.php bin/Database/Schema/Blueprint.php bin/Database/Seeders/SeederCreator.php bin/Validation/MessageBag.php bin/Validation/Validator.php
git commit -m "fix: remove database and validation nullable deprecations"
```

## Task 4: Remove Deprecated Reflection `setAccessible()` Calls

**Files:**
- Modify: `bin/Mail/Mailable.php`
- Modify: `tests/DispatcherIntegrationTest.php`
- Modify: `tests/EloquentParityTest.php`
- Modify: `tests/FacadeExpandTest.php`
- Modify: `tests/HttpClientTest.php`
- Modify: `tests/LegacyExceptionHandlerTest.php`
- Modify: `tests/MakeCommandsTest.php`
- Modify: `tests/QueueTest.php`
- Modify: `tests/RouteEnhancementTest.php`
- Modify: `tests/RouteTest.php`

- [ ] **Step 1: Remove no-op reflection access calls**

Delete every line that calls:

```php
->setAccessible(true);
```

from the files listed in this task.

Do not remove the surrounding `ReflectionClass`, `ReflectionMethod`,
`ReflectionProperty`, `getValue()`, `setValue()`, or `invoke()` calls. On PHP
8.3+ reflection members are already invokable/readable for these test use cases,
and `setAccessible()` is deprecated on PHP 8.5.

- [ ] **Step 2: Run reflection-heavy targeted tests**

Run:

```bash
php test tests/DispatcherIntegrationTest.php tests/EloquentParityTest.php tests/FacadeExpandTest.php tests/HttpClientTest.php tests/LegacyExceptionHandlerTest.php tests/MakeCommandsTest.php tests/QueueTest.php tests/RouteEnhancementTest.php tests/RouteTest.php tests/MailTest.php
```

Expected: PASS.

- [ ] **Step 3: Run the setAccessible regression test**

Run:

```bash
php test tests/RuntimeCompatibilityTest.php --filter=testNoDeprecatedReflectionSetAccessibleCallsRemain
```

Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add bin/Mail/Mailable.php tests/DispatcherIntegrationTest.php tests/EloquentParityTest.php tests/FacadeExpandTest.php tests/HttpClientTest.php tests/LegacyExceptionHandlerTest.php tests/MakeCommandsTest.php tests/QueueTest.php tests/RouteEnhancementTest.php tests/RouteTest.php
git commit -m "fix: remove deprecated reflection access calls"
```

## Task 5: Runtime Compatibility Policy Documentation

**Files:**
- Modify: `docs/Testing.md`

- [ ] **Step 1: Add runtime policy section**

Append this section to `docs/Testing.md`:

````markdown
## Runtime Compatibility

First supports PHP 8.3 and newer. Framework code must not emit PHP deprecation
warnings when linted with `E_ALL` on the currently supported runtime.

Runtime compatibility checks:

```bash
php test tests/RuntimeCompatibilityTest.php
```

The compatibility test lints all files under `bin/` with deprecation reporting
enabled and scans `bin/` plus `tests/` for deprecated reflection
`setAccessible()` calls. When PHP introduces new deprecations, fix framework
signatures or test helpers instead of suppressing warnings globally.
````

- [ ] **Step 2: Run documentation grep**

Run:

```bash
rg -n "Runtime Compatibility|RuntimeCompatibilityTest|setAccessible" docs/Testing.md
```

Expected: output contains the new section title, `RuntimeCompatibilityTest`, and `setAccessible`.

- [ ] **Step 3: Commit**

```bash
git add docs/Testing.md
git commit -m "docs: document runtime compatibility checks"
```

## Task 6: Final Verification and Graph Update

**Files:**
- Update generated graph artifacts only if `graphify update .` changes them.

- [ ] **Step 1: Run runtime compatibility tests**

Run:

```bash
php test tests/RuntimeCompatibilityTest.php
```

Expected: PASS with no deprecation output.

- [ ] **Step 2: Run full test suite**

Run:

```bash
php test
```

Expected: `1998+` tests pass, skipped count may remain, and output contains no `Deprecated:` or `PHP Deprecated:` lines.

- [ ] **Step 3: Run graphify update after code changes**

Run:

```bash
graphify update .
```

Expected: graph update completes successfully. If the command is unavailable, run the repo's documented fallback:

```bash
python3 -c "from graphify.watch import _rebuild_code; from pathlib import Path; _rebuild_code(Path('.'))"
```

- [ ] **Step 4: Inspect changed files**

Run:

```bash
git status --short
git diff --stat
```

Expected: changed files are limited to runtime hygiene code, tests, docs, and graphify outputs if regenerated.

- [ ] **Step 5: Commit final graph updates if present**

If graphify changed tracked files, commit them:

```bash
git add graphify-out
git commit -m "chore: update graph after runtime hygiene changes"
```

If graphify changed no tracked files, skip this commit.

## Self-Review Checklist

- Spec coverage:
  - Track A `php test` passes: covered by Task 6.
  - No implicit nullable framework deprecations: covered by Tasks 1-3 and Task 6.
  - Reflection `setAccessible()` deprecation removed/isolated: covered by Tasks 1 and 4.
  - `composer.json` still requires `php >=8.3`: no task changes `composer.json`.
  - Runtime policy documented: covered by Task 5.
- Placeholder scan: this plan contains no unfinished-marker text or open-ended implementation steps.
- Type consistency: all nullable concrete bindings use `callable|string|null`; optional strings use `?string`; optional ints use `?int`; optional callables use `?callable`; optional union IDs use `int|array|null`.

## Execution Choice

Plan complete and saved to `docs/superpowers/plans/2026-05-31-runtime-php85-hygiene.md`. Two execution options:

1. **Subagent-Driven (recommended)** - Dispatch a fresh subagent per task, review between tasks, fast iteration.
2. **Inline Execution** - Execute tasks in this session using executing-plans, batch execution with checkpoints.
