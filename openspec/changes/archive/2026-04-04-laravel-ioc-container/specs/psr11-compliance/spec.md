## ADDED Requirements

### Requirement: PSR-11 get method
The container SHALL implement `get(string $id)` method from PSR-11 `ContainerInterface`, returning the service instance.

#### Scenario: Get existing service
- **WHEN** calling `$container->get('cache')` where `cache` is bound
- **THEN** the resolved instance SHALL be returned

#### Scenario: Get non-existent service
- **WHEN** calling `$container->get('nonexistent')` where the service is not bound
- **THEN** a `NotFoundExceptionInterface` implementation SHALL be thrown

### Requirement: PSR-11 has method
The container SHALL implement `has(string $id)` method from PSR-11 `ContainerInterface`, returning boolean.

#### Scenario: Has bound service
- **WHEN** calling `$container->has('cache')` where `cache` is bound
- **THEN** `true` SHALL be returned

#### Scenario: Has unbound service
- **WHEN** calling `$container->has('nonexistent')`
- **THEN** `false` SHALL be returned

### Requirement: ContainerInterface signature
The PSR-11 `ContainerInterface` SHALL be defined at `Bin\Psr\Container\ContainerInterface` with `get($id)` and `has($id)` methods matching PSR-11 specification.

#### Scenario: Container implements PSR-11
- **WHEN** checking `instanceof \Bin\Psr\Container\ContainerInterface` on the container
- **THEN** the result SHALL be `true`
