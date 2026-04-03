## ADDED Requirements

### Requirement: Subscriber 接口
框架 SHALL 提供 `Bin\Events\Subscriber` 接口，包含单个方法 `subscribe(EventDispatcher $events): void`。订阅者允许将相关监听器组织在一起，作为独立类进行批量注册。

#### Scenario: 实现 Subscriber 接口
- **WHEN** 创建类实现 `Subscriber` 接口并在 `subscribe()` 中注册多个监听器
- **AND** 调用 `$dispatcher->subscribe($subscriber)`
- **THEN** 所有在 `subscribe()` 中注册的监听器 SHALL 生效

### Requirement: 注册订阅者
EventDispatcher SHALL 提供 `subscribe(Subscriber $subscriber): void` 方法，调用订阅者的 `subscribe()` 方法完成批量注册。

#### Scenario: 订阅者批量注册监听器
- **WHEN** 订阅者在 `subscribe()` 中调用 `$events->listen('user.created', [$this, 'onUserCreated'])` 和 `$events->listen('user.deleted', [$this, 'onUserDeleted'])`
- **AND** 分发 `user.created` 事件
- **THEN** 订阅者的 `onUserCreated()` 方法 SHALL 被调用

#### Scenario: 订阅者的多个监听器各自独立触发
- **WHEN** 订阅者注册了 `user.created` 和 `user.deleted` 两个监听器
- **AND** 仅分发 `user.created`
- **THEN** 仅 `onUserCreated()` SHALL 被调用，`onUserDeleted()` SHALL NOT 被调用

### Requirement: 订阅者支持方法引用
订阅者在 `subscribe()` 中注册监听器时 SHALL 支持 `[$this, 'methodName']` 数组格式。

#### Scenario: 使用自身方法作为监听器
- **WHEN** 订阅者在 `subscribe()` 中使用 `[$this, 'onUserCreated']`
- **AND** 分发对应事件
- **THEN** 订阅者的 `onUserCreated()` 方法 SHALL 被调用，接收事件 payload
