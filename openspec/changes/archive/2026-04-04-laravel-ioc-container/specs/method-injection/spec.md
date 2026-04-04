## ADDED Requirements

### Requirement: Call with class@method syntax
The container `call()` method SHALL support `Class@method` string syntax with full dependency injection.

#### Scenario: Call class method with DI
- **WHEN** calling `$container->call('UserController@show')` where `show(Request $request)` has type-hinted parameters
- **THEN** the container SHALL resolve `UserController` and inject `Request` into the `show` method

### Requirement: Call with array callable
The container `call()` SHALL support `[$instance, 'method']` array callable with dependency injection.

#### Scenario: Call array callable
- **WHEN** calling `$container->call([$controller, 'show'])` where `show` has type-hinted dependencies
- **THEN** the container SHALL inject dependencies from the container

### Requirement: Call with default parameters
The container `call()` SHALL support passing explicit parameters that override container resolution.

#### Scenario: Explicit parameter overrides DI
- **WHEN** calling `$container->call('Class@method', ['id' => 42])` where `method($id)` has a non-type-hinted parameter
- **THEN** the value `42` SHALL be used instead of attempting container resolution

### Requirement: Call with closure
The container `call()` SHALL support closures with dependency injection (already implemented, preserve behavior).

#### Scenario: Closure with DI
- **WHEN** calling `$container->call(function (Request $request) { ... })`
- **THEN** the `Request` instance SHALL be resolved from the container and injected
