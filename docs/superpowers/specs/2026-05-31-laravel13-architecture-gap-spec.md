# Laravel 13 Architecture Gap Spec

Date: 2026-05-31
Status: Ready for user review

## Goal

Define the architectural gap between the current First framework and Laravel 13,
then turn that comparison into a practical alignment spec for future work.

This spec is intentionally not an implementation plan. It captures current
state, target architecture, non-goals, capability gaps, acceptance criteria, and
recommended sequencing.

## Evidence Baseline

### Local First Evidence

- Graph snapshot: `graphify-out/GRAPH_REPORT.md`
  - 450 files, 6857 nodes, 8359 edges, 459 communities.
  - God nodes center on `Request`, middleware tests, `Blueprint`,
    `QueryBuilder`, routing tests, queue tests, `Container`,
    `ValidationManager`, and config tests.
- Runtime entry and lifecycle:
  - `bootstrap/app.php`
  - `public/index.php`
  - `bin/Foundation/HttpKernel.php`
  - `bin/Foundation/ConsoleKernel.php`
  - `bin/Foundation/ApplicationBuilder.php`
- Existing module evidence:
  - `bin/Container/`
  - `bin/Route/`
  - `bin/Middleware/`
  - `bin/Validation/`
  - `bin/View/Blade/`
  - `bin/Queue/`
  - `bin/Mail/`
  - `bin/Filesystem/`
  - `bin/Http/`
  - `bin/Localization/`
  - `bin/Facade/`
  - `config/*.php`
- Verification snapshot:
  - `php test` passed with `1998 tests, 4 skipped, 1994 passed`.
  - The suite emits PHP 8.5 deprecation warnings for implicit nullable
    parameters and reflection `setAccessible()` usage.

### Laravel 13 Reference Baseline

Reference docs checked on 2026-05-31:

- Laravel 13 Release Notes: https://laravel.com/docs/13.x/releases
- Laravel 13 Request Lifecycle: https://laravel.com/docs/13.x/lifecycle
- Laravel 13 Service Container: https://laravel.com/docs/13.x/container
- Laravel 13 Routing: https://laravel.com/docs/13.x/routing
- Laravel 13 Controllers: https://laravel.com/docs/13.x/controllers
- Laravel 13 Validation: https://laravel.com/docs/13.x/validation
- Laravel 13 Blade Templates: https://laravel.com/docs/13.x/blade
- Laravel 13 Queues: https://laravel.com/docs/13.x/queues
- Laravel 13 AI SDK: https://laravel.com/docs/13.x/ai-sdk
- Laravel 13 MCP: https://laravel.com/docs/13.x/mcp

## Current Architecture Summary

First is a PHP 8.3+ Laravel-like framework, not a Laravel application. It has
already moved beyond the older gap document state where validation, Blade,
middleware, queue, mail, filesystem, and lifecycle were mostly missing.

The current architecture has these major pillars:

- Laravel-like application assembly:
  `App::configure(...)->withProviders()->withRouting()->withMiddleware()->create()`.
- Separate HTTP and console kernels.
- Bootstrappers for environment, exceptions, configuration, request context,
  provider registration, provider booting, middleware loading, and route loading.
- IoC container with binding, singleton, scoped binding, contextual binding,
  tags, resolving callbacks, rebinding callbacks, method injection, PSR-style
  contracts, and circular dependency detection.
- Route collection with resource routing, API resource routing, fallback routes,
  redirects, view routes, signed URL support, named routes, route cache support,
  and model binding tests.
- Middleware pipeline using an onion model, middleware stack, aliases, groups,
  priority, exclusion, and terminable middleware.
- Validation engine with rule parsing, message bag, rule objects, FormRequest,
  authorization hooks, preparation hooks, after hooks, and validated data access.
- Blade compiler with escaped/raw output, control directives, sections, yields,
  includes, stacks, pushes/prepends, and compiled cache.
- Eloquent-like ORM with model lifecycle, relationships, eager loading,
  soft deletes, factories, pagination, scopes, events, observers, pivots, and
  convenience finders.
- Ecosystem modules for queue, mail, filesystem, HTTP client, localization,
  cache, session, auth, authorization, resource responses, console commands,
  migrations, seeders, logging, cookies, and debug/profiler helpers.

## Target Architecture

The target is "Laravel 13-aligned, First-native". The goal is not to vendor or
clone Illuminate internals. First should preserve its smaller custom framework
identity while adopting the Laravel 13 architectural surfaces that improve
developer experience, module boundaries, and ecosystem interoperability.

The target shape:

- Application lifecycle remains builder-driven and kernel-based.
- Core runtime modules are container-resolved rather than singleton-only.
- Facades remain compatibility conveniences over container-managed services.
- Configuration files stay explicit and small, but support Laravel-compatible
  keys where First implements the matching behavior.
- Routing, validation, middleware, console, and response APIs should continue
  moving toward Laravel-compatible developer ergonomics.
- Ecosystem modules should grow by driver contracts and adapters, not by
  coupling to one implementation.
- Laravel 13 AI, MCP, semantic search, and agent-oriented APIs are treated as a
  new architecture track, not as incidental helpers.

## Non-Goals

- Do not replace First with Laravel or Illuminate packages wholesale.
- Do not attempt full Laravel 13 feature parity in one change.
- Do not remove existing compatibility paths such as `app/routes.php` or
  legacy static manager calls without a migration spec.
- Do not add AI/MCP/vector features directly into the current container,
  routing, ORM, or queue modules without clear boundaries.
- Do not broaden driver support without first defining driver contracts and
  test fixtures.

## Gap Matrix

| Area | Current First State | Laravel 13 Target | Gap |
| --- | --- | --- | --- |
| PHP/runtime support | Requires PHP `>=8.3`; tests run on PHP 8.5 with deprecations | PHP 8.3+ with active PHP compatibility discipline | Medium: deprecation cleanup and compatibility policy |
| Lifecycle | Builder, HTTP kernel, console kernel, bootstrappers | Official Laravel lifecycle centered on `public/index.php`, `bootstrap/app.php`, kernels, providers, middleware, router | Low: shape aligned, polish remains |
| Container | Strong core binding/contextual/tag/call support | Attribute-based binding, contextual attributes, scoped lifecycle tied to request/job, extensive contracts | Medium: missing attribute injection and lifecycle reset semantics |
| Providers | Provider repository and boot sequence | Provider-driven framework bootstrapping with mature package discovery conventions | Medium: package discovery and provider metadata missing |
| Routing | Resource, API resource, fallback, redirect, view, signed URLs, route cache, model binding | Full route API, groups, model binding, scoped bindings, resource helpers, rate limiting, middleware integration | Medium: advanced group/scoped/resource ergonomics remain |
| Middleware | Onion pipeline, aliases, groups, priority, terminate | Full Laravel middleware configurator and route/group middleware behavior | Low/Medium: shape aligned, edge parity remains |
| Validation | Validation rules, FormRequest, message bags, rule objects | Laravel's full validator, localization, Precognition, complex conditional rules | Medium/High: breadth and edge behavior remain |
| Blade/View | Compiler supports key directives and cache | Full Blade components, anonymous components, slots, layouts, fragments, service injection | Medium/High: component model is biggest gap |
| ORM/Query | Rich Eloquent-like ORM and QueryBuilder | Eloquent + Builder + casts + factories + resources + database integrations | Medium: breadth and exact edge semantics remain |
| Queue | Sync and database queue, worker, failed jobs | Redis/SQS/Beanstalk/database/sync, batching, chains, unique jobs, Horizon ecosystem | High: driver breadth and production operations |
| Mail | SMTP/sendmail/array, mailable, queued mail | Full mailer ecosystem, Markdown mail, more transports, notifications integration | High |
| Filesystem | Local/public local disks | Flysystem-backed local/S3/SFTP/etc. | High |
| HTTP Client | Pending request, retry, pool/middleware tests | Laravel HTTP client with broader middleware/fake/assertion ecosystem | Medium |
| Auth/Security | Session/token auth, Gate, Policy, RBAC, password reset, rate limit | Multi-guard auth, Sanctum/Passport, verification, password confirmation, full security packages | High |
| Observability | Debug/profiler/logging basics | Telescope, Pulse, Nightwatch, Horizon, Cloud integrations | High |
| Scheduling/Broadcasting/Notifications | Not first-class in current evidence | First-class Laravel subsystems and packages | High |
| AI/MCP/Search | No local AI/MCP/vector/embedding architecture found | Laravel 13 AI SDK, MCP, Boost, search/semantic capabilities | High/New track |

## Required Capability Tracks

### Track A: Runtime Compatibility and PHP 8.5 Hygiene

First MUST keep the test suite green without PHP deprecation noise on supported
runtime versions.

Acceptance criteria:

- `php test` passes.
- No implicit nullable parameter deprecation is emitted from framework code.
- Reflection `setAccessible()` deprecation is removed from tests or isolated in
  a compatibility helper.
- `composer.json` continues to require `php >=8.3`.
- A short runtime policy is documented in `docs/Testing.md` or a dedicated
  runtime compatibility doc.

### Track B: Container and Provider Laravel 13 Alignment

First SHOULD support Laravel 13-style attribute-driven dependency resolution
where it materially improves developer APIs.

Acceptance criteria:

- Attribute resolution is handled inside the container, not inside controllers.
- Built-in attributes are scoped to implemented First services only:
  config, cache, DB connection, storage disk, log channel, auth guard,
  route parameter, tagged iterable, and explicit implementation.
- Custom contextual attributes can implement a First contract equivalent to
  `ContextualAttribute`.
- Scoped bindings are reset at request/job boundaries.
- Existing contextual binding APIs keep working.

### Track C: Developer API Routing Parity

First SHOULD close remaining high-value routing ergonomics before adding new
ecosystem modules.

> **Status note (2026-09-09):** Delivered per
> `docs/superpowers/plans/2026-09-09-track-c-routing-parity.md` — nested
> resource URIs with parent parameters, `shallow` nesting, implicit-binding
> `missing()` callbacks, `scoped()` nested bindings (default and explicit
> foreign keys), route-group `controller` attribute composition, and route
> cache closure-rejection coverage with scoped round-trips.

Acceptance criteria:

- Resource routes support Laravel-like names, parameters, only/except,
  shallow nesting where feasible, and API-only behavior.
- Implicit model binding supports missing callbacks and scoped nested binding.
- Route groups compose prefix, name, middleware, domain, controller, and where
  constraints predictably.
- Signed URL validation remains compatible with route middleware aliases.
- Route cache rejects unsupported closures with clear messages.
- `route:list`, `route:cache`, and `route:clear` remain covered by tests.

### Track D: Validation, FormRequest, and Blade Breadth

First SHOULD prioritize developer-facing parity in validation and views because
these are used in nearly every web application.

> **Status note (2026-09-09):** Delivered per
> `docs/superpowers/plans/2026-09-09-track-d-validation-blade.md` — unknown
> validation rules now fail loudly instead of silently passing; `exclude`,
> `accepted`, `required_if`, `required_with`, `unique`, and `exists` rules
> implemented with `Rule` builder support; validation messages resolve through
> an opt-in `Translator` hook with full `en`/`zh` message files; Blade gains
> `@component`/`@slot`, anonymous `<x-*>` components with static/bound/bare
> attributes, `ComponentAttributeBag` with class merging, and component-aware
> cache invalidation. Class-based components and `@props` remain future work.

Acceptance criteria:

- Validation supports the common Laravel rule set used by forms and APIs:
  required, nullable, sometimes, string, integer, numeric, boolean, array,
  email, url, min, max, size, between, confirmed, accepted, date, in,
  not_in, unique, exists, regex, required_if, required_with, prohibited,
  exclude, and custom rule objects.
- FormRequest resolves automatically in controller dispatch and fails with
  predictable web/API behavior.
- Message localization uses the localization subsystem.
- Blade supports components, anonymous components, slots, attributes, class
  merging, `@csrf`, `@method`, `@error`, `@auth`, `@guest`, and stacks.
- Blade cache invalidation accounts for inherited and included templates.

### Track E: Driver-Based Ecosystem Expansion

First SHOULD extend queue, mail, filesystem, cache, and HTTP client through
driver contracts and manager factories, not one-off service code.

Acceptance criteria:

- Queue drivers are contract-based and cover sync, database, and at least one
  external backend before production claims.
- Queue worker supports retry, timeout, failed jobs, backoff, max tries, and
  graceful stop semantics.
- Filesystem supports local and at least one remote-style adapter contract.
- Mail supports queued mailable delivery through the queue contract.
- Notifications are introduced only after mail and queue contracts are stable.
- Driver configuration is documented in `config/*.php` with test fixtures.

### Track F: Laravel 13 AI/MCP/Search Architecture

First SHOULD treat AI/MCP/search as a separate subsystem family. These APIs are
new enough and cross-cutting enough that they should not be embedded into
existing modules without clear contracts.

Acceptance criteria:

- Add a `Bin\Ai` namespace only after defining provider-neutral contracts.
- AI client capabilities are split by text generation, tool calling, embeddings,
  image/audio, and structured output where implemented.
- MCP server/client support is isolated from HTTP routing and console commands
  behind explicit adapters.
- Semantic/vector search is modeled as a search capability, not as an ORM-only
  feature.
- No AI provider API key is read directly from business code; config and
  container resolution own provider setup.
- Tests use fake providers and deterministic fixtures.

## Recommended Sequencing

1. PHP 8.5 hygiene and runtime policy.
2. Container attributes and scoped lifecycle reset.
3. Routing edge parity and route cache hardening.
4. Validation/FormRequest and Blade component breadth.
5. Queue/filesystem/mail driver expansion.
6. Scheduling, notifications, broadcasting, and observability.
7. AI/MCP/search subsystem design and implementation.

This order keeps the foundation stable before adding high-blast-radius
ecosystem features.

## OpenSpec Decomposition

If this spec is promoted into OpenSpec capabilities, split it into separate
changes instead of one large change:

- `runtime-php85-hygiene`
- `container-attributes`
- `routing-advanced-parity`
- `validation-blade-breadth`
- `queue-driver-expansion`
- `filesystem-driver-expansion`
- `mail-notification-foundation`
- `ai-mcp-search-foundation`

Each change should include its own `proposal.md`, `tasks.md`, and capability
specs under `openspec/specs/`.

## Acceptance Criteria for This Spec

- It reflects the current codebase, not the older stale gap analysis.
- It names concrete local files and graph evidence used for the comparison.
- It distinguishes already-aligned Laravel-like architecture from remaining
  Laravel 13 gaps.
- It includes AI/MCP/Search as a Laravel 13-specific architecture track.
- It provides a sequencing model that can be converted into implementation
  plans or OpenSpec changes.

## Risks

- Chasing full Laravel parity can produce a large framework rewrite. Mitigate by
  keeping each capability track independently shippable.
- Attribute-based container features can hide dependencies. Mitigate with a
  small supported attribute set and explicit tests.
- AI/MCP features can become provider-specific quickly. Mitigate with
  provider-neutral contracts and fake providers in tests.
- Driver expansion can multiply maintenance cost. Mitigate by standardizing
  driver contracts before adding adapters.

## Outbound Work Products

This spec authorizes writing follow-up implementation plans, but it does not
authorize runtime code changes by itself. The next concrete work product should
be one of:

- an implementation plan for Track A,
- an OpenSpec change for `runtime-php85-hygiene`, or
- an OpenSpec change for `container-attributes`.
