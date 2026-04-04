## ADDED Requirements

### Requirement: Rebinding callback registration
The container SHALL support registering callbacks via `rebinding($abstract, $callback)` that execute when a service is rebound.

#### Scenario: Callback fires on rebind
- **WHEN** registering a rebinding callback for `cache` and then calling `$container->bind('cache', NewCache::class)`
- **THEN** the rebinding callback SHALL be invoked with the new instance

### Requirement: Rebinding with instance replacement
The container SHALL support `refresh($abstract, $target, $method)` pattern for rebinding.

#### Scenario: Refresh on rebind
- **WHEN** a service depends on another service and the dependency is rebound
- **THEN** the dependent service SHALL receive the updated instance via the refresh callback

### Requirement: Rebinding does not fire on first bind
The container SHALL NOT fire rebinding callbacks on the initial binding.

#### Scenario: First bind does not trigger rebinding
- **WHEN** registering a rebinding callback and binding a service for the first time
- **THEN** the rebinding callback SHALL NOT be invoked
