## ADDED Requirements

### Requirement: Observer 类定义
框架 SHALL 支持创建 Observer 类，方法名对应模型事件（creating、created、updating、updated、saving、saved、deleting、deleted、restoring、restored、retrieved）。

#### Scenario: 创建 Observer 类
- **WHEN** 定义一个类包含 `public function creating(Model $model)` 方法
- **THEN** 该方法在模型创建前被调用
- **THEN** 接收模型实例作为参数

#### Scenario: Observer 中阻止操作
- **WHEN** Observer 的 `creating` 方法返回 `false`
- **THEN** 模型的 `save()` 操作被阻止，返回 false

### Requirement: Observer 注册
模型 SHALL 支持 `Model::observe(string|object $observer)` 方法注册 Observer。

#### Scenario: 通过类名注册
- **WHEN** 调用 `User::observe(UserObserver::class)`
- **THEN** User 模型的所有事件触发时调用 UserObserver 对应的方法

#### Scenario: 通过实例注册
- **WHEN** 调用 `User::observe(new UserObserver())`
- **THEN** Observer 使用提供的实例

### Requirement: Observer 自动方法映射
Observer 类中定义的方法 SHALL 与模型事件自动映射，方法名即为事件名。

#### Scenario: 完整生命周期 Observer
- **WHEN** UserObserver 定义了 `creating`、`created`、`updating`、`updated`、`deleting`、`deleted` 方法
- **THEN** 创建用户时依次触发 `creating` → `created`
- **THEN** 更新用户时依次触发 `updating` → `updated`
- **THEN** 删除用户时依次触发 `deleting` → `deleted`

### Requirement: 清除 Observer
模型 SHALL 支持 `Model::clearObservers()` 方法移除所有已注册的 Observer。

#### Scenario: 清除 Observer
- **WHEN** 调用 `User::clearObservers()`
- **THEN** 后续的 User 模型事件不再触发 Observer 方法
