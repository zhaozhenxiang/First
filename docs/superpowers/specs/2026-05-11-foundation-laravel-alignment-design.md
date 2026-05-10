# Foundation Laravel Alignment Design

Date: 2026-05-11
Status: Ready for user review

## Goal

Align First's foundation lifecycle with Laravel 13 enough that later developer
API and ecosystem work can build on a stable application boot model.

This stage introduces a Laravel-like application assembly surface while keeping
the existing runtime compatible. It does not implement the later `Route`
resource API, implicit model binding, scheduling, notifications, or additional
ecosystem modules.

## Context

First already has the main foundation pieces:

- `bootstrap/app.php`
- `public/index.php`
- `command`
- `Bin\App\App`
- `Bin\Foundation\HttpKernel`
- `Bin\Foundation\ConsoleKernel`
- foundation bootstrappers for env, config, exceptions, providers, middleware,
  and routes
- `ProviderRepository`
- `MiddlewareStack`
- `RouteAction` with middleware pipeline termination

Laravel 13's public lifecycle provides the target shape:

- requests enter through `public/index.php` and load the application from
  `bootstrap/app.php`
- HTTP and console traffic flow through separate kernels
- service providers are registered before their `boot()` methods run
- user-defined providers are listed in `bootstrap/providers.php`
- middleware can be configured from `bootstrap/app.php`
- route files such as `routes/web.php` and `routes/api.php` are configured from
  application bootstrap

The current First implementation is close in spirit, but configuration is split
across `config/app.php`, `config/middleware.php`, and `app/routes.php`. The
alignment should add the Laravel-like entry points without breaking those
legacy files.

Reference docs:

- Laravel 13 Request Lifecycle: https://laravel.com/docs/13.x/lifecycle
- Laravel 13 Service Providers: https://laravel.com/docs/13.x/providers
- Laravel 13 Middleware: https://laravel.com/docs/13.x/middleware

## Scope

### In Scope

- Add the builder-style application assembly API
  `App::configure($basePath)->withRouting(...)->withMiddleware(...)->withProviders(...)->create()`.
- Store builder output in an application configuration object attached to `App`.
- Add support for `bootstrap/providers.php` as the preferred application
  provider list.
- Keep `config/app.php['providers']` as a provider compatibility source.
- Add support for Laravel-like route file configuration:
  - `routes/web.php`
  - `routes/api.php`
  - optional extra route files
- Keep `app/routes.php` as the route compatibility fallback.
- Add middleware configuration through a configurator object used by
  `bootstrap/app.php`.
- Keep `config/middleware.php` as the middleware compatibility source.
- Keep HTTP-only bootstrappers out of console bootstrap.
- Add tests for new and legacy lifecycle paths.
- Document the migration model for the next implementation plan.

### Out of Scope

- `Route::resource()`, `apiResource()`, route cache, signed routes, and implicit
  model binding.
- Facade expansion beyond what is needed to support lifecycle configuration.
- Scheduling, notifications, broadcasting, Horizon/Telescope-style monitoring,
  or new queue/mail/filesystem behavior.
- A large rewrite of `HttpKernel`, `ConsoleKernel`, `RouteAction`, or the
  container.
- Removing `config/app.php`, `config/middleware.php`, or `app/routes.php`.

## Architecture

The design uses a mixed migration:

1. `bootstrap/app.php` becomes the preferred assembly point.
2. Existing config files remain valid compatibility sources.
3. Bootstrappers continue to perform runtime work.
4. Kernel responsibilities stay narrow: bootstrap, dispatch, and terminate.

The builder collects intent; it does not execute requests. Runtime execution
still happens through `HttpKernel` and `ConsoleKernel`.

## Components

### ApplicationBuilder

`ApplicationBuilder` creates or retrieves an `App` instance, collects
configuration, and returns the configured application.

Required surface:

```php
return App::configure(dirname(__DIR__))
    ->withProviders()
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
    )
    ->withMiddleware(function (MiddlewareConfigurator $middleware): void {
        $middleware->group('web', [
            \Bin\Middleware\SessionMiddleware::class,
            \Bin\Middleware\CsrfMiddleware::class,
        ]);
    })
    ->create();
```

The implementation uses these class names:

- `Bin\Foundation\ApplicationBuilder`
- `Bin\Foundation\ApplicationConfiguration`
- `Bin\Foundation\Configuration\MiddlewareConfigurator`
- `Bin\Foundation\Configuration\RoutingConfigurator`

### ApplicationConfiguration

`ApplicationConfiguration` is attached to `App` and stores:

- provider file paths and explicit provider classes
- web, api, and extra route files
- middleware arrays for global middleware, groups, aliases, and priority

It should expose read-only accessors for bootstrappers. It should not perform
file loading or mutate global middleware state directly.

### MiddlewareConfigurator

`MiddlewareConfigurator` provides a small Laravel-like API that maps to the
existing `MiddlewareStack` structure:

- `append()` / `prepend()` for global middleware
- `group()` for full group replacement
- `appendToGroup()` / `prependToGroup()` for group extension
- `web()` / `api()` convenience methods
- `alias()` for middleware aliases
- `priority()` for execution ordering

The configurator should output the same shape currently accepted by
`MiddlewareStack::loadFromConfig()`.

### RoutingConfigurator

`RoutingConfigurator` stores route file paths. `LoadRoutes` reads this
configuration and requires files in deterministic order:

1. configured web route file
2. configured api route file
3. configured extra route files in insertion order
4. `app/routes.php` only when no new route configuration was supplied

The design intentionally does not add automatic URI prefixes or middleware
groups for API routes in this phase. Those belong to the Developer API
alignment phase.

### Provider Loading

`RegisterProviders` should collect providers from:

1. builder-provided explicit providers
2. `bootstrap/providers.php`
3. legacy `config/app.php['providers']`

Provider class names are deduplicated while preserving first occurrence order.
Registration still flows through `App::register()` and `ProviderRepository`.

## Data Flow

### HTTP

1. `public/index.php` requires `bootstrap/app.php`.
2. `bootstrap/app.php` configures and returns `App`.
3. `public/index.php` gets `HttpKernel` from the app.
4. `HttpKernel::handle()` runs HTTP bootstrappers.
5. Provider registration reads new and legacy provider sources.
6. Provider boot runs after registration.
7. Middleware configuration loads builder config and legacy config.
8. Routes load from configured route files or the legacy fallback.
9. `RouteAction` dispatches through the existing middleware pipeline.
10. `HttpKernel::terminate()` calls terminable middleware after response
    generation.

### Console

1. `command` requires `bootstrap/app.php`.
2. `bootstrap/app.php` configures and returns `App`.
3. `command` gets `ConsoleKernel` from the app.
4. `ConsoleKernel::handle()` runs console bootstrappers.
5. Console bootstrap loads env, exceptions, config, providers, and provider boot.
6. Console bootstrap does not load middleware or HTTP routes.
7. Command dispatch remains delegated to `Bin\Console\Kernel`.

## Compatibility

- Existing applications that leave `bootstrap/app.php` unchanged continue to
  use `config/app.php`, `config/middleware.php`, and `app/routes.php`.
- New applications can move provider registration to `bootstrap/providers.php`
  without deleting `config/app.php`.
- New route files can be added under `routes/` without deleting
  `app/routes.php`.
- New middleware configuration can live in `bootstrap/app.php` while
  `config/middleware.php` continues to provide defaults or legacy entries.
- Duplicate providers and duplicate middleware entries are deduplicated.
- New configuration wins when it explicitly defines the same alias or group;
  legacy configuration fills gaps.

## Error Handling

- Missing `bootstrap/providers.php` is allowed.
- Missing configured route files are skipped if they are optional defaults.
- If no new route configuration exists, missing `app/routes.php` is allowed and
  results in no loaded routes.
- Invalid provider classes fail during registration through the existing
  container path.
- Invalid middleware classes are not prevalidated in this phase; request
  dispatch continues to expose those errors.
- Malformed configuration arrays throw clear runtime exceptions from the
  configurator or bootstrapper that reads them.

## Testing

Add or extend focused lifecycle tests:

- builder-created `App` exposes base path and attached configuration
- `bootstrap/providers.php` providers register and boot
- legacy `config/app.php['providers']` providers still register
- duplicate providers register once
- builder middleware config loads into `MiddlewareStack`
- legacy `config/middleware.php` still loads
- builder middleware aliases override legacy aliases
- configured route files load in deterministic order
- `app/routes.php` loads when no new route files are configured
- console bootstrap does not load route or middleware configuration

Focused verification:

```bash
php test tests/ApplicationLifecycleTest.php
php test tests/MiddlewarePipelineTest.php
php test tests/ConsoleArtisanParityTest.php
```

Final verification:

```bash
php test
```

## Acceptance Criteria

1. `public/index.php` and `command` still work with the current
   `bootstrap/app.php`.
2. A Laravel-like `bootstrap/app.php` can configure providers, middleware, and
   routes through a builder and return an `App`.
3. `bootstrap/providers.php` is supported and deduplicated with legacy
   providers.
4. `routes/web.php` and `routes/api.php` can be loaded by configuration.
5. `app/routes.php` remains a fallback when new route configuration is absent.
6. Middleware configured through the builder reaches `MiddlewareStack`.
7. `config/middleware.php` remains compatible.
8. Console bootstrap remains free of HTTP-only route and middleware loading.
9. Existing lifecycle, middleware pipeline, and console parity tests pass.

## Follow-Up Phases

After this foundation work lands, the next specs can be scoped independently:

1. Developer API alignment: resource routes, implicit model binding, route cache,
   URL generation, and facade/API polish.
2. Ecosystem alignment: queue/mail/filesystem/http/localization/scheduling depth
   based on the stabilized lifecycle.
