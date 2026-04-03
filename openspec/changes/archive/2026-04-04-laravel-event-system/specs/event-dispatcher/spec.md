## ADDED Requirements

### Requirement: EventDispatcher 核心分发
EventDispatcher SHALL 提供 `listen(string $event, mixed $listener): void` 方法注册监听器，和 `dispatch(string|object $event, array $payload = []): array|null` 方法分发事件。

#### Scenario: 注册闭包监听器并分发事件
- **WHEN** 调用 `$dispatcher->listen('user.created', function ($payload) { return $payload; })`
- **AND** 调用 `$dispatcher->dispatch('user.created', ['name' => 'Alice'])`
- **THEN** 闭包 SHALL 被调用，参数为 `['name' => 'Alice']`

#### Scenario: 分发事件对象
- **WHEN** 创建一个事件对象 `$event = new UserRegistered('Alice')`
- **AND** 调用 `$dispatcher->dispatch($event)`
- **THEN** 事件名 SHALL 为事件对象的类名（如 `App\Events\UserRegistered`）
- **AND** 监听器 SHALL 接收事件对象作为第一个参数

### Requirement: 停止传播
当任何监听器返回 `false` 时，EventDispatcher SHALL 停止向后续监听器传播该事件。

#### Scenario: 监听器返回 false 停止传播
- **WHEN** 事件 A 有 3 个监听器
- **AND** 第 1 个监听器返回 `false`
- **THEN** 第 2 和第 3 个监听器 SHALL NOT 被调用

#### Scenario: 所有监听器正常执行
- **WHEN** 事件 A 有 3 个监听器，全部返回非 `false` 值
- **THEN** 3 个监听器 SHALL 按注册顺序全部执行

### Requirement: 通配符事件匹配
EventDispatcher SHALL 支持通配符监听器注册（使用 `*`），匹配所有事件。

#### Scenario: 通配符监听器捕获所有事件
- **WHEN** 注册 `$dispatcher->listen('*', $listener)`
- **AND** 分发任意事件 `$dispatcher->dispatch('user.created', $payload)`
- **THEN** 通配符监听器 SHALL 被调用，参数为事件名和 payload

#### Scenario: 通配符监听器在具体监听器之后执行
- **WHEN** 注册具体监听器 `$dispatcher->listen('user.created', $listener1)`
- **AND** 注册通配符监听器 `$dispatcher->listen('*', $listener2)`
- **AND** 分发 `user.created` 事件
- **THEN** `$listener1` SHALL 先于 `$listener2` 执行

### Requirement: 监听器管理
EventDispatcher SHALL 提供 `forget(string $event): void` 移除指定事件的所有监听器，和 `hasListeners(string $event): bool` 检查是否有监听器。

#### Scenario: forget 移除监听器
- **WHEN** 注册监听器后调用 `$dispatcher->forget('user.created')`
- **THEN** 后续 `dispatch('user.created')` SHALL NOT 触发任何监听器

#### Scenario: hasListeners 检查
- **WHEN** 未注册任何 `user.created` 监听器
- **THEN** `$dispatcher->hasListeners('user.created')` SHALL 返回 `false`
- **WHEN** 注册 `user.created` 监听器
- **THEN** `$dispatcher->hasListeners('user.created')` SHALL 返回 `true`

### Requirement: 返回监听器响应
`dispatch()` SHALL 返回最后一个非 null 监听器的返回值组成的数组（排除返回 `false` 之前的结果）。

#### Scenario: 收集监听器返回值
- **WHEN** 3 个监听器分别返回 `'a'`, `'b'`, `'c'`
- **THEN** `dispatch()` SHALL 返回 `['a', 'b', 'c']`
