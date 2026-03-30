## ADDED Requirements

### Requirement: Observer 类定义
系统 SHALL 允许通过普通类定义模型事件监听器，方法名对应事件名（如 `created`、`updating`）。

#### Scenario: 基本观察者
- **WHEN** 定义 `UserObserver` 类包含 `public function created(User $user)` 方法
- **AND** 通过 `User::observe(UserObserver::class)` 注册
- **THEN** 创建 User 后 SHALL 自动调用 `created()` 方法

### Requirement: 支持的事件方法
Observer SHALL 支持以下事件方法：`retrieved`、`creating`、`created`、`updating`、`updated`、`saving`、`saved`、`deleting`、`deleted`、`restoring`、`restored`。

#### Scenario: 所有生命周期事件
- **WHEN** Observer 类定义了 `saving` 和 `saved` 方法
- **THEN** `saving` SHALL 在保存前调用，`saved` SHALL 在保存后调用

### Requirement: 多 Observer 注册
系统 SHALL 支持为同一模型注册多个 Observer。

#### Scenario: 多个观察者
- **WHEN** 为 User 模型注册 `UserObserver` 和 `UserCacheObserver`
- **THEN** 两个 Observer 的方法 SHALL 按注册顺序依次调用

### Requirement: Observer 与闭包事件共存
Observer 注册的事件 SHALL 与现有闭包事件共存，互不干扰。

#### Scenario: 混合使用
- **WHEN** User 模型通过 `created()` 注册闭包监听器，同时注册 Observer
- **THEN** 闭包和 Observer 方法 SHALL 都被调用

### Requirement: Observer 取消注册
系统 SHALL 支持通过 `Model::withoutEvents()` 临时禁用所有事件。

#### Scenario: 禁用事件执行操作
- **WHEN** 在 `User::withoutEvents(fn() => User::create([...]))` 中创建模型
- **THEN** Observer 方法 SHALL 不被调用
