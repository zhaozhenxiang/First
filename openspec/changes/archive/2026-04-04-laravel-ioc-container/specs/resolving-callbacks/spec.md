## ADDED Requirements

### Requirement: Global resolving callbacks
The container SHALL support registering global callbacks that execute before any service is resolved via `resolving()` method.

#### Scenario: Register global resolving callback
- **WHEN** calling `$container->resolving(function ($object, $container) { ... })` and then resolving any service
- **THEN** the callback SHALL be invoked with the resolved object and container instance

#### Scenario: Multiple global resolving callbacks
- **WHEN** registering multiple global resolving callbacks
- **THEN** all callbacks SHALL be invoked in registration order

### Requirement: Per-abstract resolving callbacks
The container SHALL support registering callbacks for a specific abstract type via `resolving($abstract, $callback)`.

#### Scenario: Per-abstract resolving callback
- **WHEN** calling `$container->resolving('cache', function ($cache, $container) { ... })` and then resolving `cache`
- **THEN** the callback SHALL be invoked with the resolved cache instance

#### Scenario: Per-abstract callback not invoked for other types
- **WHEN** registering a callback for `cache` and resolving `request`
- **THEN** the `cache` callback SHALL NOT be invoked

### Requirement: AfterResolving callbacks
The container SHALL support `afterResolving()` callbacks that execute after resolving callbacks, with identical registration behavior.

#### Scenario: AfterResolving executes after resolving
- **WHEN** registering both `resolving()` and `afterResolving()` callbacks
- **THEN** resolving callback SHALL execute first, then afterResolving callback

### Requirement: Callbacks fire for already-resolved singletons on rebind
The container SHALL NOT fire resolving callbacks for instances that were already cached (singleton).

#### Scenario: Singleton resolved twice
- **WHEN** a singleton is resolved, then resolved again
- **THEN** resolving callbacks SHALL fire only on the first resolution
