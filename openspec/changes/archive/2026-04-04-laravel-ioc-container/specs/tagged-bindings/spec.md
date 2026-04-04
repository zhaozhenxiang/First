## ADDED Requirements

### Requirement: Tag registration
The container SHALL support registering tags for service bindings via `tag()` method. Multiple services can share the same tag, and a single service can have multiple tags.

#### Scenario: Tag a single service
- **WHEN** calling `$container->tag('cache.driver', ['cache', 'drivers'])`
- **THEN** the service `cache.driver` is associated with both `cache` and `drivers` tags

#### Scenario: Tag multiple services at once
- **WHEN** calling `$container->tag(['redis.cache', 'file.cache'], 'cache')`
- **THEN** both `redis.cache` and `file.cache` are associated with the `cache` tag

#### Scenario: Retrieve tagged services
- **WHEN** calling `$container->tagged('cache')` after tagging `redis.cache` and `file.cache`
- **THEN** an array of resolved instances for `redis.cache` and `file.cache` is returned

### Requirement: Tagged iteration
The container SHALL resolve all services under a tag when `tagged()` is called.

#### Scenario: Tagged with unresolvable service
- **WHEN** calling `$container->tagged('cache')` and one tagged service cannot be resolved
- **THEN** a `BindingResolutionException` SHALL be thrown

#### Scenario: Empty tag
- **WHEN** calling `$container->tagged('nonexistent')`
- **THEN** an empty array SHALL be returned
