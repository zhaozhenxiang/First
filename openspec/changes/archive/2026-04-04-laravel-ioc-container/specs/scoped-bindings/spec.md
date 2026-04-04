## ADDED Requirements

### Requirement: Scoped binding registration
The container SHALL support `scoped($abstract, $concrete)` method that binds a service shared within the current scope/lifecycle.

#### Scenario: Scoped instance shared within scope
- **WHEN** calling `$container->scoped('cache', FileCache::class)` and resolving `cache` twice
- **THEN** the same instance SHALL be returned within the current scope

#### Scenario: Scoped resets after resetScope
- **WHEN** resolving a scoped service, calling `$container->resetScope()`, then resolving again
- **THEN** a new instance SHALL be created

### Requirement: Scoped vs singleton distinction
Scoped bindings SHALL be stored separately from singleton instances and cleared by `resetScope()`.

#### Scenario: ResetScope does not affect singletons
- **WHEN** having both a singleton and a scoped binding, calling `resetScope()`
- **THEN** the singleton instance SHALL remain cached, but the scoped instance SHALL be cleared

### Requirement: Scoped binding defaults
If no concrete is provided, the abstract SHALL be used as concrete (same behavior as `bind` and `singleton`).

#### Scenario: Scoped without concrete
- **WHEN** calling `$container->scoped('MyService')` without a concrete
- **THEN** the container SHALL use `MyService` as the concrete implementation
