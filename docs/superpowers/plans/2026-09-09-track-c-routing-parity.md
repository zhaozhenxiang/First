# Track C Routing Parity Implementation Plan

> **For agentic workers:** Steps use checkbox (`- [ ]`) syntax for tracking. Implement task-by-task with tests green after each task.

**Goal:** Close the remaining Track C routing gaps from `docs/superpowers/specs/2026-05-31-laravel13-architecture-gap-spec.md`: shallow nested resources, implicit-binding `missing()` callbacks and scoped nested bindings, route-group `controller` attribute composition, and closure-rejection coverage for `route:cache`.

**Architecture:** All changes stay inside `Bin\Route` plus the implicit-binding branch of `Bin\Routing\ControllerDispatcher`. The registrar continues to expand resources into `RouteCollection::action()` calls so group attributes keep applying automatically. Scoped bindings flow through `RouteBinding` with a new optional context argument; default behavior without context is unchanged.

**Tech Stack:** PHP 8.3+, custom `php test` runner, `Bin\Testing\TestCase`, graphify.

---

## Scope

In scope:

- Nested resource names (`photos.comments`) generate parent-scoped URIs (`/photos/{photo}/comments/{comment}`) for nested actions.
- `shallow` option drops the parent prefix for member actions (show/edit/update/destroy).
- `Route::missing(callable)` runs when an implicit binding resolves to nothing, replacing the default 404.
- `Route::scoped(['comment' => 'post'])` constrains implicit child binding queries by the parent URL parameter's foreign key.
- Route groups accept a `controller` attribute that composes with `prefix`/`name`/`namespace` (child overrides parent, Laravel semantics).
- Composition audit tests for nested groups (prefix/name/middleware/domain/where/controller).
- Route cache closure rejection already throws (`exportCacheableAction`); add focused test coverage.

Out of scope:

- Track D validation/FormRequest/Blade expansion.
- Track E queue/mail/filesystem driver expansion.
- Rate limiter integration with route groups.
- Customizing resource route parameter names beyond the existing `parameters` option.

## Current State (verified 2026-09-09)

- `ResourceRegistrar` supports `only/except/names/parameters` but treats a dotted name as one segment: `photos.comments` yields `/photos/comments/{comment}` — no `{photo}` parent parameter, no `shallow` support.
- `RouteBinding` resolves implicit bindings via `findOrFail` with no sibling-parameter context; no `missing()` handling anywhere.
- Group stack merges prefix/name/namespace/domain/where/middleware/middleware_group — no `controller` attribute.
- `RouteCollection::exportCacheableAction()` already throws `RuntimeException` for closure actions.

## File Structure

- Modify: `bin/Route/ResourceRegistrar.php`
  - Split dotted resource names into segments; build nested URIs with parent parameters; honor `shallow`.
- Modify: `bin/Route/Route.php`
  - Add `missing()` / `getMissingCallback()`, `scoped()` / `getScoped()`.
- Modify: `bin/Route/RouteBinding.php`
  - `resolveForClass()` accepts optional scoping context (foreign key + parent value) and applies `where()` before `find()`.
- Modify: `bin/Routing/ControllerDispatcher.php`
  - Pass scoped context and route parameter siblings into implicit binding; route binding misses through the route's missing callback.
- Modify: `bin/Route/RouteCollection.php`
  - Group stack merges a `controller` attribute; `action()` composes it into string actions without `@`.
- Modify: `bin/Route/RouteCache.php` (only if export needs the new keys) and `RouteCollection::exportRoute()` for `missing`/`scoped` persistence.
- Create/extend tests: `tests/ResourceRouteShallowTest.php`, `tests/RouteBindingScopingTest.php`, extend route group and cache tests.

## Tasks

### Task 1 — C-1 Nested + shallow resources

- [x] `ResourceRegistrar::buildRoutes()` splits `$name` on `.`; builds `/photos/{photo}/comments/{comment}` URIs.
- [x] `shallow` option (bool) strips parent segments for member actions.
- [x] Nested names compose route names as `photos.comments.show`; `parameters` per segment.
- [x] Tests: nested URIs, shallow URIs, names, only/except with nesting.

### Task 2 — C-2 `missing()` callback

- [x] `Route::missing(callable)` stores callback.
- [x] `ControllerDispatcher` implicit-binding failures invoke the callback and use its return value as the response.
- [x] Without a callback, behavior unchanged (404).
- [x] Tests: callback invoked for missing implicit binding, response returned, 404 without callback.

### Task 3 — C-2 Scoped nested bindings

- [x] `Route::scoped(array $bindings)` maps child parameter → parent parameter (or explicit foreign key array).
- [x] `RouteBinding::resolveForClass($class, $value, $context)` applies `where($fk, $parentValue)` via the model query before `find()`.
- [x] Default foreign key: parent parameter singularized + `_id`.
- [x] Missing scoped model still flows through `missing()`/404.
- [x] Tests: scoped query constrains by parent; unscoped unchanged.

### Task 4 — C-3 Group `controller` attribute + composition audit

- [x] `mergeGroupAttributes()` merges `controller` (child wins; not concatenated).
- [x] `action()` prefixes bare `method` string actions with the group controller.
- [x] Composition tests: nested groups for prefix/name/namespace/domain/where/middleware/controller.

### Task 5 — C-4 Route cache coverage

- [x] Test: `exportForCache()` with a closure route throws with a message naming the URI.
- [x] Test: cached payload round-trips a named route with wheres/middleware.

### Task 6 — Verification

- [x] `php test` green (2057 passed vs. 2034 baseline; +23 new tests).
- [x] `graphify update .`
- [x] Update `docs/superpowers/specs/2026-05-31-laravel13-architecture-gap-spec.md` Track C status note (or companion spec note) reflecting delivered items.

## Verification

- Full suite green; new tests fail before implementation and pass after.
- No behavior change for existing single-segment resources and non-scoped bindings.
