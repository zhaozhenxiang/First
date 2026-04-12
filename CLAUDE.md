# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Custom PHP 8.3+ MVC framework inspired by Laravel. Implements IoC/DI container, routing, middleware, Eloquent-style ORM, event system, authentication/authorization, caching, logging, validation, console commands, migrations, and pagination.

## Commands

```bash
php test                              # Run all tests (1085 tests)
php test tests/QueryBuilderTest.php   # Run single test file
php test --filter=testBind            # Filter tests by name
php test --verbose                    # Verbose output
php test --stop-on-failure            # Stop on first failure
php -l <file>                         # Syntax check a PHP file
composer dump-autoload                # Regenerate autoloader
php -S localhost:8000 -t public       # Dev server
php migrate migrate                   # Run migrations
php command db:seed                   # Run seeders
php command list                      # List CLI commands
```

## Architecture

### Request Flow

```
public/index.php → autoload → app/routes.php
  → RouteAction::action() → RouteCollection::getRoute()
  → Middleware chain (must return true to continue)
  → Controller method / Closure (with DI via reflection)
  → Response echoed
```

### Namespace Mapping

| Namespace | Directory |
|-----------|-----------|
| `Bin\` | `bin/` |
| `App\` | `app/` |

### Core Modules

| Module | Location | Key Classes |
|--------|----------|-------------|
| **IoC Container** | `bin/Container/` | `Container` (底层), `ContextualBindingBuilder` |
| **App Facade** | `bin/App/` | `App` extends `Container`，22+ 透传方法标 `@deprecated` |
| **Router** | `bin/Route/` | `RouteCollection`, `RouteAction` |
| **Request** | `bin/Request/` | `Request` (ArrayAccess + Iterator) |
| **Response** | `bin/Response/` | `Response` (string/array/view) |
| **View** | `bin/View/` | `View` (template compiler) |
| **Database ORM** | `bin/Database/` | See ORM section below |
| **Auth** | `bin/Auth/` | `AuthManager`, `Gate`, `Rbac`, `HashManager`, `RateLimiter` |
| **Cache** | `bin/Cache/` | `CacheManager`, `FileStore`, `RedisStore`, `ArrayStore` |
| **Log** | `bin/Log/` | `LogManager`, `Logger` |
| **Session** | `bin/Session/` | `SessionManager`, handlers for file/db/redis |
| **Validation** | `bin/Validation/` | `ValidationManager`, `Validator` |
| **Cookie** | `bin/Cookie/` | `CookieManager` (AES-256-CBC encryption) |
| **Events** | `bin/Events/` | `EventDispatcher`, `Event`, `Subscriber` |
| **Console** | `bin/Console/` | `Kernel`, `Command`, `Input`, `Output`, `ProgressBar` |
| **Config** | `bin/Config/` | `ConfigRepository` (dot-notation, compiled cache) |
| **Middleware** | `bin/Middleware/` | `Middleware` base, `AuthMiddleware`, `RateLimitMiddleware` |
| **Facade** | `bin/Facade/` | `Facade` (static proxy to container) |
| **Helpers** | `bin/Func/helpers/` | 17 domain-split files loaded via glob |

### ORM Architecture

```
Bin\Database\Model (665 lines, abstract base)
  ├── use HasAttributes        (421 lines) — 属性访问/修改/类型转换/fillable/guarded/hidden/visible
  ├── use HasEvents            (168 lines) — 模型事件 + Observer + withoutEvents
  ├── use HasRelationships     (421 lines) — 11 种关联 + eager loading + morph map
  ├── use HasTimestamps        (65 lines)  — created_at/updated_at
  └── use HasSerialization     (85 lines)  — toArray/toJson/append

Bin\Database\QueryBuilder (1996 lines)
  └── use CompilesQueries      (252 lines) — SQL 语法编译 (WHERE/JOIN/GROUP/HAVING/ORDER/LIMIT/OFFSET/UNION/LOCK)

Bin\Database\ConnectionManager (79 lines) — PDO 连接管理，替代遗留 Bin\Model\Model
Bin\Database\ModelEventDispatcher — 模型事件委托到 EventDispatcher
```

**关联类型**：`hasOne`, `hasMany`, `belongsTo`, `belongsToMany`, `hasOneThrough`, `hasManyThrough`, `morphOne`, `morphMany`, `morphToMany`, `morphTo`, `morphedByMany`

**分页**：`paginate()` (LengthAwarePaginator), `simplePaginate()` (Paginator), `cursorPaginate()` (CursorPaginator)

**软删除**：`Bin\Database\SoftDeletes` trait

### Helper Functions

`bin/Func/helpers.php` is a glob loader; actual functions live in `bin/Func/helpers/*.php`:

| File | Key Functions |
|------|---------------|
| `array.php` | `data_get`, `data_set`, `data_has` |
| `http.php` | `getUrl`, `getMethod`, `abort`, `response`, `redirect`, `back`, `is_ajax` |
| `config.php` | `config`, `env` |
| `container.php` | `app` |
| `session.php` | `session`, `session_*` (11 functions) |
| `auth.php` | `auth`, `auth_*`, `csrf_token`, `csrf_field` |
| `cache.php` | `cache`, `remember`, `cache_forever`, `cache_forget` |
| `cookie.php` | `cookie`, `cookie_*` |
| `validation.php` | `validate` (delegates to ValidationManager), `escape` |
| `authorization.php` | `gate`, `can`, `cannot`, `allows`, `denies` |
| `log.php` | `logger`, `info`, `error` |
| `debug.php` | `db_debug*`, `profiler*` (20 functions) |

All functions use `function_exists()` guards for safe loading.

### Static Managers (Migration in Progress)

AuthManager, CacheManager, LogManager, and Gate have been converted to instance-based with singleton pattern. Static methods are retained as `@deprecated` compatibility layer delegating to `getInstance()`:

```php
// Legacy (still works, @deprecated)
AuthManager::user();

// New approach
AuthManager::getInstance()->userFor();
```

### Container API

```php
// Bindings
$container->bind('service', ClassName::class);
$container->singleton('service', ClassName::class);
$container->instance('service', new ClassName());
$container->scoped('service', ClassName::class);  // resetScope() re-creates

// Tags
$container->tag(['redis', 'file'], 'cache');
$container->tagged('cache');

// Contextual
$container->when(AuditService::class)->needs(LoggerInterface::class)->give(fn() => new CloudLogger());

// Resolving callbacks
$container->resolving(function ($obj, $container) { /* ... */ });
$container->rebinding('cache', function ($container, $instance) { /* ... */ });

// Method injection
$container->call(ServiceImpl::class . '@handle', ['message' => 'hello']);
```

### PHP 8.5 Constraint

PHP 8.5 不允许对已有实例方法使用静态调用，`__callStatic` 不会拦截。所有静态调用需通过 `App::getInstance()` 获取实例后调用。

## Testing

1085 tests across 50 test files. Custom test runner (`php test`).

**IoC behavior tests** use PHP built-in server on port 9876 (`tests/IocBehaviorTest.php`).

### Test Conventions

- TDD driven: write tests first, then implement
- Test files in `tests/` follow `<Module>Test.php` naming
- All helpers (session, cache, auth, etc.) use test mode flags to avoid side effects
- `Bin\Database\Model::setConnection()` injects in-memory SQLite for database tests

## Code Conventions

- `declare(strict_types=1)` in all files
- PSR-4 autoloading
- Type hints on all public method parameters and return types
- `@deprecated` annotations for backward-compatible method transitions (not deleted immediately)

## Claude Code Automation

### Skills

| Command | Purpose |
|---------|---------|
| `/gen-test <source>` | Generate test skeleton from source file |
| `/new-feature <module>` | Create module scaffold (manager, tests, config) |

### Hooks

PHP syntax check auto-runs on `.php` file edits (configured in `.claude/settings.json`).

### Permissions

Wildcard patterns in `.claude/settings.local.json` (not committed): `./test:*`, `Bash(php:*)`, `Bash(git:*)`, `Bash(curl:*)`

<!-- code-review-graph MCP tools -->
## MCP Tools: code-review-graph

**IMPORTANT: This project has a knowledge graph. ALWAYS use the
code-review-graph MCP tools BEFORE using Grep/Glob/Read to explore
the codebase.** The graph is faster, cheaper (fewer tokens), and gives
you structural context (callers, dependents, test coverage) that file
scanning cannot.

### When to use graph tools FIRST

- **Exploring code**: `semantic_search_nodes` or `query_graph` instead of Grep
- **Understanding impact**: `get_impact_radius` instead of manually tracing imports
- **Code review**: `detect_changes` + `get_review_context` instead of reading entire files
- **Finding relationships**: `query_graph` with callers_of/callees_of/imports_of/tests_for
- **Architecture questions**: `get_architecture_overview` + `list_communities`

Fall back to Grep/Glob/Read **only** when the graph doesn't cover what you need.

### Key Tools

| Tool | Use when |
|------|----------|
| `detect_changes` | Reviewing code changes — gives risk-scored analysis |
| `get_review_context` | Need source snippets for review — token-efficient |
| `get_impact_radius` | Understanding blast radius of a change |
| `get_affected_flows` | Finding which execution paths are impacted |
| `query_graph` | Tracing callers, callees, imports, tests, dependencies |
| `semantic_search_nodes` | Finding functions/classes by name or keyword |
| `get_architecture_overview` | Understanding high-level codebase structure |
| `refactor_tool` | Planning renames, finding dead code |

### Workflow

1. The graph auto-updates on file changes (via hooks).
2. Use `detect_changes` for code review.
3. Use `get_affected_flows` to understand impact.
4. Use `query_graph` pattern="tests_for" to check coverage.

<!-- gitnexus:start -->
# GitNexus — Code Intelligence

This project is indexed by GitNexus as **First** (4474 symbols, 8078 relationships, 0 execution flows). Use the GitNexus MCP tools to understand code, assess impact, and navigate safely.

> If any GitNexus tool warns the index is stale, run `npx gitnexus analyze` in terminal first.

## Always Do

- **MUST run impact analysis before editing any symbol.** Before modifying a function, class, or method, run `gitnexus_impact({target: "symbolName", direction: "upstream"})` and report the blast radius (direct callers, affected processes, risk level) to the user.
- **MUST run `gitnexus_detect_changes()` before committing** to verify your changes only affect expected symbols and execution flows.
- **MUST warn the user** if impact analysis returns HIGH or CRITICAL risk before proceeding with edits.
- When exploring unfamiliar code, use `gitnexus_query({query: "concept"})` to find execution flows instead of grepping. It returns process-grouped results ranked by relevance.
- When you need full context on a specific symbol — callers, callees, which execution flows it participates in — use `gitnexus_context({name: "symbolName"})`.

## When Debugging

1. `gitnexus_query({query: "<error or symptom>"})` — find execution flows related to the issue
2. `gitnexus_context({name: "<suspect function>"})` — see all callers, callees, and process participation
3. `READ gitnexus://repo/First/process/{processName}` — trace the full execution flow step by step
4. For regressions: `gitnexus_detect_changes({scope: "compare", base_ref: "main"})` — see what your branch changed

## When Refactoring

- **Renaming**: MUST use `gitnexus_rename({symbol_name: "old", new_name: "new", dry_run: true})` first. Review the preview — graph edits are safe, text_search edits need manual review. Then run with `dry_run: false`.
- **Extracting/Splitting**: MUST run `gitnexus_context({name: "target"})` to see all incoming/outgoing refs, then `gitnexus_impact({target: "target", direction: "upstream"})` to find all external callers before moving code.
- After any refactor: run `gitnexus_detect_changes({scope: "all"})` to verify only expected files changed.

## Never Do

- NEVER edit a function, class, or method without first running `gitnexus_impact` on it.
- NEVER ignore HIGH or CRITICAL risk warnings from impact analysis.
- NEVER rename symbols with find-and-replace — use `gitnexus_rename` which understands the call graph.
- NEVER commit changes without running `gitnexus_detect_changes()` to check affected scope.

## Tools Quick Reference

| Tool | When to use | Command |
|------|-------------|---------|
| `query` | Find code by concept | `gitnexus_query({query: "auth validation"})` |
| `context` | 360-degree view of one symbol | `gitnexus_context({name: "validateUser"})` |
| `impact` | Blast radius before editing | `gitnexus_impact({target: "X", direction: "upstream"})` |
| `detect_changes` | Pre-commit scope check | `gitnexus_detect_changes({scope: "staged"})` |
| `rename` | Safe multi-file rename | `gitnexus_rename({symbol_name: "old", new_name: "new", dry_run: true})` |
| `cypher` | Custom graph queries | `gitnexus_cypher({query: "MATCH ..."})` |

## Impact Risk Levels

| Depth | Meaning | Action |
|-------|---------|--------|
| d=1 | WILL BREAK — direct callers/importers | MUST update these |
| d=2 | LIKELY AFFECTED — indirect deps | Should test |
| d=3 | MAY NEED TESTING — transitive | Test if critical path |

## Resources

| Resource | Use for |
|----------|---------|
| `gitnexus://repo/First/context` | Codebase overview, check index freshness |
| `gitnexus://repo/First/clusters` | All functional areas |
| `gitnexus://repo/First/processes` | All execution flows |
| `gitnexus://repo/First/process/{name}` | Step-by-step execution trace |

## Self-Check Before Finishing

Before completing any code modification task, verify:
1. `gitnexus_impact` was run for all modified symbols
2. No HIGH/CRITICAL risk warnings were ignored
3. `gitnexus_detect_changes()` confirms changes match expected scope
4. All d=1 (WILL BREAK) dependents were updated

## Keeping the Index Fresh

After committing code changes, the GitNexus index becomes stale. Re-run analyze to update it:

```bash
npx gitnexus analyze
```

If the index previously included embeddings, preserve them by adding `--embeddings`:

```bash
npx gitnexus analyze --embeddings
```

To check whether embeddings exist, inspect `.gitnexus/meta.json` — the `stats.embeddings` field shows the count (0 means no embeddings). **Running analyze without `--embeddings` will delete any previously generated embeddings.**

> Claude Code users: A PostToolUse hook handles this automatically after `git commit` and `git merge`.

## CLI

| Task | Read this skill file |
|------|---------------------|
| Understand architecture / "How does X work?" | `.claude/skills/gitnexus/gitnexus-exploring/SKILL.md` |
| Blast radius / "What breaks if I change X?" | `.claude/skills/gitnexus/gitnexus-impact-analysis/SKILL.md` |
| Trace bugs / "Why is X failing?" | `.claude/skills/gitnexus/gitnexus-debugging/SKILL.md` |
| Rename / extract / split / refactor | `.claude/skills/gitnexus/gitnexus-refactoring/SKILL.md` |
| Tools, resources, schema reference | `.claude/skills/gitnexus/gitnexus-guide/SKILL.md` |
| Index, status, clean, wiki CLI commands | `.claude/skills/gitnexus/gitnexus-cli/SKILL.md` |

<!-- gitnexus:end -->
