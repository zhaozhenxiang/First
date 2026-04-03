## ADDED Requirements

### Requirement: 闭包监听器
ListenerResolver SHALL 直接调用闭包监听器，将 payload 数组展开为参数。

#### Scenario: 闭包接收 payload 参数
- **WHEN** 注册闭包 `function ($name, $email) { ... }` 并分发 `['Alice', 'a@b.com']`
- **THEN** 闭包 SHALL 接收 `$name = 'Alice'`, `$email = 'a@b.com'`

### Requirement: Class@method 监听器
ListenerResolver SHALL 支持 `ListenerClass@method` 格式字符串，通过 IoC 容器解析类实例后调用指定方法。

#### Scenario: 解析 Class@method
- **WHEN** 注册 `'SendWelcomeEmail@handle'` 作为监听器
- **AND** 分发事件
- **THEN** SHALL 通过容器解析 `SendWelcomeEmail` 实例
- **AND** 调用其 `handle($payload)` 方法

#### Scenario: 容器自动注入依赖
- **WHEN** `SendWelcomeEmail` 构造函数依赖 `Mailer $mailer`
- **AND** 容器中已注册 `Mailer`
- **THEN** 实例化 `SendWelcomeEmail` 时 SHALL 自动注入 `Mailer`

### Requirement: 可调用类监听器
ListenerResolver SHALL 支持传入类名字符串，通过容器解析后调用 `__invoke()` 方法。

#### Scenario: 解析可调用类
- **WHEN** 注册 `SendWelcomeEmail::class` 作为监听器
- **AND** `SendWelcomeEmail` 实现 `__invoke($event)`
- **THEN** SHALL 通过容器解析实例并调用 `__invoke()`

### Requirement: 事件对象传递
当分发的事件是对象时，ListenerResolver SHALL 将事件对象作为监听器的第一个参数传递。

#### Scenario: 监听器接收事件对象
- **WHEN** 分发 `$event = new UserRegistered('Alice')`（对象）
- **AND** 监听器签名为 `function (UserRegistered $event)`
- **THEN** 监听器 SHALL 接收 `$event` 对象作为第一个参数

### Requirement: 无容器时回退
当 IoC 容器不可用时，ListenerResolver SHALL 直接 `new` 实例化监听器类。

#### Scenario: 容器未设置时直接实例化
- **WHEN** EventDispatcher 未绑定容器
- **AND** 监听器为 `SomeListener@handle`
- **THEN** SHALL 使用 `new SomeListener()` 创建实例
