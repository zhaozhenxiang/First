## ADDED Requirements

### Requirement: When-needs-give fluent interface
The container SHALL support `when($concrete)->needs($abstract)->give($implementation)` fluent interface for contextual binding.

#### Scenario: Basic conditional binding
- **WHEN** calling `$container->when(UserController::class)->needs(LoggerInterface::class)->give(FileLogger::class)`
- **THEN** when `UserController` is resolved, its `LoggerInterface` dependency SHALL receive `FileLogger`

#### Scenario: Conditional binding with closure
- **WHEN** calling `$container->when(Service::class)->needs('config')->give(function ($container) { return $container->make('app.config'); })`
- **THEN** the closure SHALL be invoked to resolve the dependency

### Requirement: Give value
The container SHALL support `give($value)` for primitive value injection.

#### Scenario: Give primitive value
- **WHEN** calling `$container->when(Mailer::class)->needs('timeout')->give(30)`
- **THEN** the value `30` SHALL be injected as the `timeout` parameter

### Requirement: Give tag
The container SHALL support `giveTagged($tag)` to inject all services with a given tag.

#### Scenario: Give tagged services
- **WHEN** calling `$container->when(Reporter::class)->needs('handlers')->giveTagged('report.handlers')`
- **THEN** all services tagged with `report.handlers` SHALL be injected

### Requirement: Backward compatibility
The existing `contextual()` method SHALL continue to work alongside the new fluent interface.

#### Scenario: Old contextual method still works
- **WHEN** calling `$container->contextual(Service::class, Interface::class, Concrete::class)`
- **THEN** the behavior SHALL be identical to `when(Service::class)->needs(Interface::class)->give(Concrete::class)`
