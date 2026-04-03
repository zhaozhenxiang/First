## Why

当前框架只有模型级别的 `ModelEventDispatcher`（纯静态、不感知容器、不支持通用的应用级事件）。应用层缺少一个统一的发布/订阅机制来解耦业务逻辑，例如：用户注册后发送邮件、订单支付后触发库存扣减、日志记录等跨模块协作。参考 Laravel Events 实现，需要一个通用的 EventDispatcher 服务，支持事件分发、监听器注册、事件订阅者、通配符匹配，并能与 IoC 容器集成。

## What Changes

- 新增 `Bin\Events\EventDispatcher`：通用事件分发器，替代 `ModelEventDispatcher` 的功能并扩展为应用级服务
- 新增 `Bin\Events\Event` 基类：可选的事件对象封装
- 新增 `Bin\Events\Listener`：监听器解析（支持闭包、类方法、容器自动解析）
- 新增 `Bin\Events\Subscriber`：事件订阅者接口
- 新增 `Bin\Facade\Event` Facade：静态代理访问事件系统
- 新增 `Bin\Providers\EventServiceProvider`：注册事件服务到容器
- 重构 `Bin\Database\ModelEventDispatcher`：委托给新的 `EventDispatcher`，保持模型事件向后兼容
- 修复 `ModelEventDispatcher::dispatchForModel()` 中 model-specific 监听器不停止传播的 bug

## Capabilities

### New Capabilities
- `event-dispatcher`: 通用事件分发器核心（注册、分发、通配符、优先级、停止传播）
- `event-listener-resolver`: 监听器解析（闭包、类@方法、容器自动注入）
- `event-subscriber`: 事件订阅者模式（批量注册一组相关监听器）
- `event-facade-provider`: Event Facade 和 EventServiceProvider（容器集成）

### Modified Capabilities
（无现有 spec 需要修改）

## Impact

- **新增文件**: `bin/Events/EventDispatcher.php`, `bin/Events/Event.php`, `bin/Events/Listener.php`, `bin/Events/Subscriber.php`, `bin/Facade/Event.php`, `bin/Providers/EventServiceProvider.php`
- **修改文件**: `bin/Database/ModelEventDispatcher.php`（委托给 EventDispatcher）, `bin/App/App.php`（注册 EventServiceProvider）, `bin/Database/Model.php`（适配新的分发方式）
- **向后兼容**: `Model::creating()`、`Model::observe()` 等现有 API 不变
- **依赖**: 依赖现有的 IoC 容器 (`Bin\Container\Container`)
