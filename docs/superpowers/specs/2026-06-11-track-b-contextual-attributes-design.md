# Track B-1 Contextual Attributes Design

## Goal

Add a Laravel 13-inspired contextual attribute foundation to First's container
so dependency-resolution hints are handled by the container rather than by
controllers, while preserving existing contextual binding and scoped binding
behavior.

## Source Spec

This design implements the first sub-project of Track B from
`docs/superpowers/specs/2026-05-31-laravel13-architecture-gap-spec.md`.

Track B requires:

- Attribute resolution is handled inside the container, not inside controllers.
- Built-in attributes are scoped to implemented First services only.
- Custom contextual attributes can implement a First equivalent of
  `ContextualAttribute`.
- Scoped bindings are reset at request/job boundaries.
- Existing contextual binding APIs keep working.

## Current Context

First already has a capable container in `bin/Container/Container.php`:

- constructor injection through `resolveDependencies()`;
- method injection through `resolveMethodParameters()`;
- contextual binding through `contextual()` and `when()->needs()->give()`;
- tagged bindings through `tag()` and `tagged()`;
- scoped bindings through `scoped()` and `resetScope()`;
- request/controller method invocation through `Container::call()`.

`bin/Routing/ControllerDispatcher.php` currently resolves route, model, and
FormRequest parameters before delegating to `Container::call()`. Track B should
not push more resolution policy into this dispatcher. Attribute handling belongs
in the shared container parameter-resolution path.

## Design Summary

Introduce a small contextual attribute subsystem:

- `Bin\Contracts\ContextualAttribute` defines the extension point for custom
  attributes.
- Built-in attributes live under `Bin\Container\Attributes`.
- `Container` inspects `ReflectionParameter` attributes during both
  constructor injection and method injection.
- Attribute resolution runs before legacy contextual binding, but after
  explicitly supplied call parameters.
- Existing contextual binding and tagged binding behavior remains available.

The implementation should add only services First can resolve today. If a
Laravel attribute maps to a service that is not stable in First yet, it should
stay out of B-1 instead of becoming an unsupported API.

## Built-In Attributes

The B-1 built-ins are intentionally conservative:

- `#[Config('app.name')]` resolves a config value by key.
- `#[Cache]` resolves the cache manager/default cache service already exposed
  by First.
- `#[Db]` resolves the default database connection service used by the existing
  database layer.
- `#[Log('daily')]` resolves a logger channel through the existing log manager.
- `#[RouteParameter('id')]` resolves an explicitly supplied route/call
  parameter from `Container::call()` context.
- `#[Tag('reports')]` resolves the container's tagged services as an array.
- `#[Give(Foo::class)]` resolves an explicit implementation through the
  container.

The original Track B list also names storage disk and auth guard. B-1 should
include them only if current First services expose stable resolution points with
clear tests. If not, they remain Track B-2 work and the B-1 plan must say so.

## Custom Attribute Contract

Custom attributes implement:

```php
namespace Bin\Contracts;

use Bin\Container\Container;
use ReflectionParameter;

interface ContextualAttribute
{
    public function resolve(Container $container, ReflectionParameter $parameter): mixed;
}
```

Container logic should not need to know custom attribute classes. It should
instantiate the attribute and call `resolve()` when the attribute implements the
contract.

## Parameter Resolution Order

For `Container::call()` method and closure injection:

1. Explicit parameter by name from the `$parameters` array.
2. First contextual attribute on the `ReflectionParameter`.
3. Type-hinted object resolution through `make()`.
4. Default value.
5. Variadic skip.
6. `BindingResolutionException`.

For constructor injection:

1. First contextual attribute on the `ReflectionParameter`.
2. Existing contextual binding by building class and type name.
3. Type-hinted object resolution through `make()`.
4. Existing contextual binding by building class and parameter name.
5. Default value.
6. `BindingResolutionException`.

This keeps controller-dispatched route values explicit while still allowing
attributes to resolve everything else inside the container.

## Route Parameter Attributes

`#[RouteParameter('id')]` must read from the parameters passed to
`Container::call()`. The dispatcher can keep building the same `$parameters`
array it already sends today. It must not inspect contextual attributes itself.

If the named value is absent:

- use the PHP default value when the parameter has one;
- return `null` only when the parameter type allows null;
- otherwise throw `BindingResolutionException` with the parameter and attribute
  names.

## Scoped Binding Boundaries

B-1 should make scoped reset behavior explicit and tested. The existing
`resetScope()` method is the canonical clearing primitive. Request and queue
execution boundaries should call that primitive where they already establish a
new request/job lifecycle.

This design does not require a new lifecycle abstraction. It requires tests
proving:

- scoped bindings return the same instance inside one scope;
- `resetScope()` clears scoped instances;
- request dispatch boundaries do not leak scoped instances across requests;
- worker job boundaries do not leak scoped instances across processed jobs when
  the worker has access to the application container.

## Error Handling

Attribute resolution failures should raise `BindingResolutionException` with
enough detail to act on:

- target class or callback;
- parameter name;
- attribute class;
- missing service/key/route parameter where applicable.

The container should not silently suppress attribute failures or fall through to
unrelated type resolution when an attribute explicitly requested a value.

## Testing Strategy

The implementation plan should start with failing tests. Coverage must include:

- constructor injection with a custom `ContextualAttribute`;
- method injection with a custom `ContextualAttribute`;
- explicit call parameters overriding contextual attributes in `Container::call()`;
- `#[Tag]` returning tagged services through `Container::tagged()`;
- `#[Give]` resolving an explicit implementation;
- `#[RouteParameter]` resolving values from call/route parameters;
- missing `#[RouteParameter]` errors and nullable/default behavior;
- existing `contextual()` and `when()->needs()->give()` tests still passing;
- scoped binding reset behavior at direct container, request, and job boundaries;
- full runtime compatibility test remains green.

## Non-Goals

B-1 does not implement all of Track B-F. Specifically:

- no routing resource or route cache changes from Track C;
- no validation rule, FormRequest, or Blade expansion from Track D;
- no queue/mail/filesystem/cache/HTTP driver expansion from Track E;
- no AI/MCP/search namespace or provider contracts from Track F;
- no storage/auth attributes unless current First services can
  support them with real tests.

## Acceptance Mapping

- Container-owned attribute resolution: covered by shared constructor and method
  parameter resolution helpers in `Container`.
- Built-ins scoped to implemented services: covered by the conservative built-in
  list and explicit non-goal for unsupported services.
- Custom contextual attributes: covered by `Bin\Contracts\ContextualAttribute`.
- Scoped reset at request/job boundaries: covered by `resetScope()` boundary
  tests.
- Existing contextual binding APIs: covered by regression tests for
  `contextual()` and `when()->needs()->give()`.

## Implementation Handoff

After this design is approved, create a detailed implementation plan in
`docs/superpowers/plans/2026-06-11-track-b-contextual-attributes.md`.
The plan should use TDD, keep commits small, and implement B-1 before any other
Track B-F work.

## Implementation Notes

Implemented by plan `docs/superpowers/plans/2026-06-11-track-b-contextual-attributes.md`.

- Container parameter resolution owns contextual attributes for constructors and
  `Container::call()`.
- Built-ins cover implemented First services: config, cache, database, storage,
  log, default auth manager, route parameter, tagged services, and explicit
  implementation.
- Route dispatch passes raw route parameters to the container through
  `Container::ROUTE_PARAMETER_CONTEXT`; dispatchers do not inspect contextual
  attributes.
- Scoped bindings reset at direct `resetScope()`, request dispatch, worker job,
  sync queue, and `dispatchSync()` boundaries.
- Non-default auth guards and named database connections produce explicit
  `BindingResolutionException` messages because First does not yet expose those
  runtime abstractions.
