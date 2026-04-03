## ADDED Requirements

### Requirement: EventServiceProvider
框架 SHALL 提供 `Bin\Providers\EventServiceProvider`，将 `EventDispatcher` 注册为容器单例（别名 `events`）。

#### Scenario: 从容器解析 EventDispatcher
- **WHEN** 应用启动并注册了 EventServiceProvider
- **AND** 调用 `$app->make('events')` 或 `$app->make(EventDispatcher::class)`
- **THEN** SHALL 返回同一个 EventDispatcher 单例实例

#### Scenario: EventDispatcher 可获取容器实例
- **WHEN** EventDispatcher 通过容器解析
- **THEN** EventDispatcher SHALL 持有容器引用，用于解析 `Class@method` 监听器

### Requirement: Event Facade
框架 SHALL 提供 `Bin\Facade\Event` Facade，代理 `events` 容器绑定，提供静态方法访问。

#### Scenario: 通过 Facade 注册和分发事件
- **WHEN** 调用 `\Event::listen('test.event', $callback)`
- **AND** 调用 `\Event::dispatch('test.event', $payload)`
- **THEN** SHALL 等同于 `$app->make('events')->listen(...)` 和 `$app->make('events')->dispatch(...)`

#### Scenario: Facade 支持所有 EventDispatcher 方法
- **WHEN** 通过 Facade 调用 `listen`, `dispatch`, `forget`, `hasListeners`, `subscribe` 中任一方法
- **THEN** SHALL 正确代理到 EventDispatcher 实例

### Requirement: Model 事件委托
ModelEventDispatcher SHALL 将模型事件委托给 EventDispatcher 单例，保持现有 API 完全兼容。

#### Scenario: Model::creating() 通过新系统工作
- **WHEN** 调用 `User::creating(function ($user) { ... })`
- **AND** 创建一个新 User 并调用 `$user->save()`
- **THEN** 闭包 SHALL 被调用（通过 EventDispatcher 分发）

#### Scenario: Model::observe() 通过新系统工作
- **WHEN** 调用 `User::observe(UserObserver::class)`
- **AND** 创建一个新 User 并调用 `$user->save()`
- **THEN** UserObserver 的 `creating()` 和 `created()` 方法 SHALL 被调用

#### Scenario: 模型事件返回 false 阻止操作
- **WHEN** 注册 `creating` 监听器返回 `false`
- **AND** 调用 `$model->save()`
- **THEN** 插入操作 SHALL NOT 执行，`save()` 返回 `false`
