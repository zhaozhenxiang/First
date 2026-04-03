## Context

当前框架的事件机制仅限于 `Bin\Database\ModelEventDispatcher`——一个纯静态类，硬编码于模型层，仅处理模型生命周期事件（creating/updating/deleting 等）。应用层缺少通用的发布/订阅机制。

核心限制：
- `ModelEventDispatcher` 不感知 IoC 容器，无法自动注入依赖到监听器
- 不支持通配符事件（如 `App\Events\*`）
- 不支持事件订阅者（Subscriber）模式
- 不支持监听器优先级
- `dispatchForModel()` 存在 bug：model-specific 监听器返回 `false` 不停止传播
- 没有与容器集成的独立事件服务

## Goals / Non-Goals

**Goals:**
- 实现通用 EventDispatcher，注册为容器单例
- 支持闭包监听器、`Class@method` 字符串监听器、容器自动解析监听器
- 支持通配符事件匹配（`*` 匹配任意事件）
- 支持事件订阅者（Subscriber 接口）
- 监听器返回 `false` 时停止传播（修复现有 bug）
- 保持模型事件 API 完全向后兼容
- 提供 `Event` Facade 静态访问

**Non-Goals:**
- 不实现事件队列（queued events）——留待未来 Queue 系统实现
- 不实现事件广播（broadcasting）
- 不实现事件缓存或持久化
- 不实现事件版本控制

## Decisions

### 1. EventDispatcher 作为容器单例

**选择**: `EventDispatcher` 注册为 `events` 别名的单例。

**理由**: 事件系统是全局基础设施，整个应用共享同一个分发器实例。与 Laravel 的 `events` 绑定一致。

**替代方案**: 每个模块独立分发器——增加复杂度，且 Laravel 不采用此模式。

### 2. 监听器存储结构

**选择**: 使用 `array<string, array<int, callable>>` 结构，按事件名索引，每个事件名下有序数组存储监听器。

**理由**: 简单、高效。O(1) 事件名查找，O(n) 分发（n = 该事件的监听器数）。通配符监听器存储在特殊键 `*` 下。

### 3. 监听器解析策略

**选择**: 支持 3 种格式：
- 闭包：直接调用
- `Class@method` 字符串：通过容器解析类实例，调用方法
- 类名字符串：通过容器解析，调用 `__invoke()` 或 `handle()` 方法

**理由**: 与 Laravel 保持一致。`Class@method` 格式最常用（如 `SendWelcomeEmail@handle`），容器解析实现自动依赖注入。

### 4. ModelEventDispatcher 迁移策略

**选择**: `ModelEventDispatcher` 内部委托给 `EventDispatcher` 单例，而非直接替换。

**理由**: 保持向后兼容。现有 `Model::creating()` 等 API 调用 `ModelEventDispatcher::listen()`，不修改调用方。`ModelEventDispatcher` 变为薄代理层。

### 5. 事件对象 vs 字符串事件名

**选择**: 同时支持字符串事件名和事件对象。传入事件对象时，自动用类名作为事件名，并将对象传给监听器。

**理由**: Laravel 同时支持两种方式。字符串简单直接（如 `"user.registered"`），事件对象提供类型安全和数据封装。

### 6. Subscriber 接口设计

**选择**: 定义 `Subscriber` 接口，包含 `subscribe(EventDispatcher $events): void` 方法。

**理由**: 与 Laravel 的 `ShouldQueue` 接口模式一致。订阅者在 `subscribe()` 中批量注册一组相关监听器。

## Risks / Trade-offs

- **[向后兼容]** → `ModelEventDispatcher` 委托而非替换，所有现有模型事件 API 不变。测试套件中的 `ModelEventTest` 和 `ObserverTest` 必须继续通过。
- **[性能]** 通配符监听器需要在每次分发时额外遍历 → 通配符监听器通常很少，影响可忽略。
- **[静态到实例迁移]** `ModelEventDispatcher` 的静态 `listen()`/`dispatch()` 需要在首次使用时获取容器单例 → 使用延迟初始化，首次调用时从 `App` 容器获取 `EventDispatcher` 实例。
