## Context

当前框架的 IoC 容器位于 `Bin\Container\Container`，已实现基础功能（bind/singleton/alias/contextual/extend/反射自动解析）。`App` 类作为容器门面代理调用底层 `Container`。

现有问题：
- 缺少标签绑定，无法按组解析服务
- 没有全局解析回调机制，服务提供者无法监听所有解析事件
- 没有重绑定回调，依赖方无法感知服务被覆盖
- `call()` 方法不支持完整的方法注入
- 缺少作用域绑定（请求级别共享）
- 不兼容 PSR-11 标准
- 条件绑定使用旧式 `contextual()` 方法，不如 Laravel 的 `when()->needs()->give()` 流畅
- 使用通用 `RuntimeException` 而非专用异常

## Goals / Non-Goals

**Goals:**
- 参考 Laravel Container 补齐核心功能，使容器具备生产级可用性
- 保持向后兼容，所有现有 API 不变
- 实现完整的 PSR-11 兼容
- 建立专用异常体系，提供清晰的错误信息
- 条件绑定支持流畅接口

**Non-Goals:**
- 不实现 Laravel 的 `ContextualBindingBuilder` 独立类（简化为容器内部方法链）
- 不实现容器事件分发（已有独立的 EventDispatcher）
- 不实现 `build()` 的公开 API（保持 protected）
- 不引入 `psr/container` Composer 依赖（自建接口兼容 PSR-11 签名）

## Decisions

### 1. 标签绑定实现方式

**选择**：在 Container 内部维护 `$tags` 数组，`tag()` 方法注册标签，`tagged()` 方法返回 `array` 而非 lazy collection。

**理由**：框架当前不需要 lazy loading 标签。使用数组更简单，与现有代码风格一致。

**替代方案**：返回 `\Generator` 或自定义 `TaggedCollection` — 过度设计。

### 2. PSR-11 兼容策略

**选择**：自建 `Psr\Container\ContainerInterface` 接口文件（`bin/Psr/Container/ContainerInterface.php`），而非通过 Composer 安装 `psr/container`。

**理由**：避免引入额外依赖，保持框架轻量。PSR-11 接口仅有 `get()` 和 `has()` 两个方法。

### 3. 作用域绑定实现

**选择**：新增 `$scopedInstances` 数组，`scoped()` 方法类似 `singleton()` 但实例不持久化到 `$instances`。提供 `resetScope()` 方法清空作用域实例。

**理由**：与 singleton 行为一致但生命周期不同，测试时可用 `resetScope()` 重置。

### 4. 条件绑定流畅接口

**选择**：新增 `when()` 方法返回 `ContextualBindingBuilder`，支持 `when(ClassA::class)->needs(InterfaceB::class)->give(ConcreteB::class)` 链式调用。同时保留旧 `contextual()` 方法。

**理由**：与 Laravel API 一致，更易读。保留旧方法确保向后兼容。

### 5. 循环依赖检测

**选择**：在 `build()` 中检查 buildStack，如果发现循环则抛出 `CircularDependencyException`。

**理由**：防止无限递归，提供清晰的错误信息。Laravel 也实现了此检测。

## Risks / Trade-offs

- **[向后兼容]** 新增方法可能影响子类 → 所有新方法为增量添加，不改现有签名
- **[性能]** 标签绑定和解析回调增加解析开销 → 影响极小（数组操作），可接受
- **[PSR-11 自建]** 与 Composer 包的接口不完全兼容 → 仅两个方法，签名完全匹配
- **[条件绑定]** 新增 ContextualBindingBuilder 类 → 保持简单，仅维护状态不做复杂逻辑
