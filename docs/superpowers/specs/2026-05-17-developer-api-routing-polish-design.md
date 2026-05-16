# Developer API Routing Polish Design

Date: 2026-05-17
Status: Ready for user review

## Goal

Align First's day-to-day routing developer API with the Laravel-style
application bootstrap introduced by the foundation alignment phase.

This phase polishes existing routing features instead of rebuilding them. First
already has resource routes, route groups, explicit and implicit model binding,
named routes, fallback routes, redirect routes, and view routes. The work here
connects those capabilities to `routes/web.php` and `routes/api.php`, adds a
small route inspection surface, and improves named URL generation.

## Context

The previous foundation alignment introduced:

- `App::configure(...)->withRouting(...)->withMiddleware(...)->create()`
- `routes/web.php` and `routes/api.php` loading
- `ApplicationConfiguration`
- `RoutingConfigurator`
- `LoadRoutes`
- HTTP-only route and middleware bootstrap

That phase intentionally did not add API route prefixes or middleware groups.
This phase fills that gap for the developer-facing routing API.

Current routing capabilities are already present in:

- `Bin\Route\RouteCollection`
- `Bin\Route\Route`
- `Bin\Route\ResourceRegistrar`
- `Bin\Route\RouteBinding`
- `Bin\Routing\ControllerDispatcher`
- `Bin\Facade\Route`
- `Bin\Facade\URL`

Relevant current tests already pass:

```bash
php test tests/RouteEnhancementTest.php
php test tests/RouteTest.php
php test tests/ApplicationLifecycleTest.php
```

Reference docs:

- Laravel 13 Routing: https://laravel.com/docs/13.x/routing
- Laravel 13 Controllers and Resource Controllers:
  https://laravel.com/docs/13.x/controllers
- Laravel URL Generation: https://laravel.com/docs/13.x/urls

## Scope

### In Scope

- Treat route files as typed route file entries rather than anonymous strings.
- Load configured API route files inside a routing group with:
  - `prefix` set to `api`
  - `middleware_group` set to `api`
- Keep strict Laravel-style API route semantics:
  - `routes/api.php` should define `/users`
  - the final route URI becomes `/api/users`
  - an explicitly written `/api/users` becomes `/api/api/users`
- Add route-current introspection:
  - `RouteCollection::current()`
  - `RouteCollection::currentRouteName()`
  - `RouteCollection::currentRouteAction()`
- Add a route metadata surface for developer tooling.
- Add a basic `route:list` command showing method, URI, name, action, and
  middleware.
- Improve named URL generation so unused parameters become query string
  parameters.
- Keep existing resource routes and model binding behavior unchanged.
- Add focused tests for the new API file behavior, route introspection,
  `route:list`, and query string generation.

### Out of Scope

- Route cache commands.
- Signed URLs and temporary signed URLs.
- Advanced `route:list` filtering or sorting options.
- A rewrite of `RouteCollection` away from static storage.
- Changing resource route naming or generated action rules.
- Changing implicit model binding lookup behavior.
- Removing legacy `app/routes.php` fallback loading.

## Architecture

The design keeps the current static routing model and adds metadata around it.
This gives the developer API the expected Laravel-style behavior without a
large router rewrite.

The main boundary is route loading:

1. `ApplicationBuilder::withRouting()` records whether a route file is web,
   api, or extra.
2. `ApplicationConfiguration` stores those typed route file entries.
3. `LoadRoutes` loads entries in deterministic order.
4. API entries are required inside a `RouteCollection::group()` call.
5. `RouteCollection` remains the source of truth for registered routes.

## Components

### ApplicationConfiguration

`ApplicationConfiguration` should expose a typed route file list for
bootstrappers while preserving compatibility with current callers. Each entry
should use this shape:

```php
[
    'path' => '/absolute/path/to/routes/api.php',
    'type' => 'api',
]
```

The stored route entries should distinguish:

- `web`
- `api`
- `extra`

The existing `routeFiles()` accessor must remain for compatibility and continue
returning path strings. `LoadRoutes` should use the typed entries so it can
apply API defaults.

### RoutingConfigurator

`RoutingConfigurator` can stay small. It only needs to preserve route file
paths and kinds collected by `ApplicationBuilder::withRouting()`.

No public filtering or route cache behavior belongs here.

### LoadRoutes

`LoadRoutes` should load configured route files in the same effective order as
the foundation alignment:

1. configured web route file
2. configured api route file
3. configured extra route files in insertion order
4. `app/routes.php` only when no new route configuration was supplied

When loading an API route file, it should require that file inside:

```php
RouteCollection::group([
    'prefix' => 'api',
    'middleware_group' => 'api',
], function () use ($routeFile): void {
    require $routeFile;
});
```

This is intentionally strict. The loader should not inspect the route file for
existing `/api` prefixes and should not deduplicate the prefix.

### RouteCollection

`RouteCollection` should expose a small read API for the current matched route
and route-table metadata.

Current route APIs:

- `current(): ?Route`
- `currentRouteName(): ?string`
- `currentRouteAction(): mixed`

`resolve()` should set the current route when a static, dynamic, or fallback
route matches. `clear()` should reset the current route.

Route table metadata should return normalized rows for tooling, not raw
internal arrays. Each row should include:

- method
- uri
- name
- action
- middleware

Middleware should include both direct middleware and middleware groups so
`route:list` reflects how a route will be dispatched. The metadata should use
stable string values:

- string controller actions are returned as-is
- closures are returned as `Closure`
- object callables are returned as their class name
- empty names and middleware lists are returned as empty strings for command
  display
- multiple middleware entries are joined with `, `

### RouteListCommand

Add `Bin\Console\Commands\RouteListCommand` with signature `route:list`.

The command should ensure the application is bootstrapped through the existing
console kernel path, then render a table with:

- Method
- URI
- Name
- Action
- Middleware

If no routes exist, it should output an empty table and return `0`.

No filters are included in this phase.

### URL Generation

`Route::url()` currently replaces required and optional path parameters. It
should also track which input keys were consumed by path placeholders.

After path replacement:

- missing required path parameters still throw `UrlGenerationException`
- consumed path parameters are not included in the query string
- unused parameters with non-null values are appended through `http_build_query`
- null unused parameters are omitted

Example:

```php
RouteCollection::get('/users/{user}', fn () => 'ok')->name('users.show');

RouteCollection::url('users.show', [
    'user' => 5,
    'tab' => 'posts',
]);
```

Expected result:

```text
/users/5?tab=posts
```

## Data Flow

### API Route Loading

1. `bootstrap/app.php` calls `withRouting(api: __DIR__ . '/../routes/api.php')`.
2. `ApplicationBuilder` records the API route file as an API entry.
3. `HttpKernel` runs `LoadRoutes` during HTTP bootstrap.
4. `LoadRoutes` sees the API entry and opens a route group with prefix `api`
   and middleware group `api`.
5. The API route file is required inside that group.
6. Routes declared as `/users` are registered as `/api/users`.

### Current Route Introspection

1. `RouteCollection::getRoute()` delegates to `resolve()`.
2. `resolve()` finds a static, dynamic, or fallback route.
3. The matched route is stored as the current route.
4. Runtime code can call `RouteCollection::current()` or facade equivalents.
5. `clear()` resets the current route for tests and long-running processes.

### Route List

1. `php command route:list` enters through the existing console kernel.
2. The console kernel bootstraps providers and route loading as already
   configured.
3. `RouteListCommand` reads normalized metadata from `RouteCollection`.
4. The command renders a table and exits successfully.

## Compatibility

- Existing `app/routes.php` fallback behavior remains unchanged.
- Existing `routes/web.php` behavior remains unchanged.
- Existing resource, API resource, route group, model binding, fallback,
  redirect, view, and named route behavior remains unchanged.
- Existing calls to `RouteCollection::getRoutes()` remain valid.
- If current code depends on `routeFiles()` returning strings, that accessor can
  continue returning paths while new typed accessors serve `LoadRoutes`.
- API route prefixing is intentionally not backward-compatible with manually
  prefixed `/api` routes inside `routes/api.php`.

## Error Handling

- Missing configured route files continue to be skipped.
- Exceptions thrown while requiring a route file continue to propagate.
- `RouteCollection::current()` returns `null` before any route is matched.
- `currentRouteName()` and `currentRouteAction()` return `null` before any
  route is matched.
- `route:list` returns `0` for an empty route table.
- URL generation keeps the existing missing-parameter exception behavior.
- Query string generation omits null unused parameters.

## Testing

Add or extend focused tests:

- API route files are loaded under `/api`.
- API route files receive the `api` middleware group.
- Web route files do not receive API prefixing.
- Legacy `app/routes.php` fallback still works when no new route files are
  configured.
- `RouteCollection::current()` is set for static route matches.
- `RouteCollection::current()` is set for dynamic route matches.
- `RouteCollection::current()` is set for fallback route matches.
- `RouteCollection::clear()` resets the current route.
- `currentRouteName()` returns the current route name.
- `currentRouteAction()` returns the current route action.
- Named URL generation appends unused parameters as a query string.
- Named URL generation omits null unused query parameters.
- Named URL generation still throws for missing required path parameters.
- `route:list` outputs method, URI, name, action, and middleware.
- `route:list` returns success when no routes are registered.

Focused verification:

```bash
php test tests/ApplicationLifecycleTest.php
php test tests/RouteTest.php
php test tests/RouteEnhancementTest.php
php test tests/ConsoleArtisanParityTest.php
```

Final verification:

```bash
php test
```

## Acceptance Criteria

1. `routes/api.php` routes are automatically registered under `/api`.
2. `routes/api.php` routes automatically receive the `api` middleware group.
3. `routes/web.php` and legacy `app/routes.php` behavior remain compatible.
4. `RouteCollection::current()` reports the matched route after route
   resolution.
5. `currentRouteName()` and `currentRouteAction()` expose the current route
   metadata.
6. `php command route:list` lists loaded routes with method, URI, name, action,
   and middleware.
7. Named route URL generation appends unused parameters as a query string.
8. Existing route enhancement, route, lifecycle, and console parity tests pass.

## Follow-Up Phases

After this routing polish lands, the next developer API phases can be scoped
independently:

1. Production routing tools: route cache, route clear, and route filtering.
2. Signed URLs: signed route generation, temporary signatures, and signature
   validation middleware.
3. Broader facade/API polish across request, response, validation, resources,
   and controller helpers.
