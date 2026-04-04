## ADDED Requirements

### Requirement: BindingResolutionException
The container SHALL throw `BindingResolutionException` when unable to resolve a service, replacing the use of generic `RuntimeException`.

#### Scenario: Unresolvable abstract
- **WHEN** calling `$container->make('NonExistentClass')` where the class does not exist
- **THEN** a `BindingResolutionException` SHALL be thrown with a descriptive message including the abstract name

#### Scenario: Unresolvable dependency
- **WHEN** resolving a class whose constructor has an unresolvable non-class-typed parameter without default value
- **THEN** a `BindingResolutionException` SHALL be thrown indicating which dependency failed

### Requirement: CircularDependencyException
The container SHALL throw `CircularDependencyException` when a circular dependency is detected during resolution.

#### Scenario: Direct circular dependency
- **WHEN** class A depends on class B, and class B depends on class A
- **THEN** a `CircularDependencyException` SHALL be thrown with the circular path shown

#### Scenario: Indirect circular dependency
- **WHEN** class A → B → C → A forms a cycle
- **THEN** a `CircularDependencyException` SHALL be thrown with the full cycle path

### Requirement: Exception hierarchy
`BindingResolutionException` SHALL extend the framework's base exception. `CircularDependencyException` SHALL extend `BindingResolutionException`. Both SHALL implement PSR-11 `NotFoundExceptionInterface` where applicable.

#### Scenario: Exception type checking
- **WHEN** a `CircularDependencyException` is thrown
- **THEN** it SHALL be catchable as both `CircularDependencyException` and `BindingResolutionException`
