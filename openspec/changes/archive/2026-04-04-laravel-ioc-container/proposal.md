## Why

当前 IoC 容器（`Bin\Container\Container`）已具备基本功能：绑定、单例、别名、上下文绑定、扩展、反射自动解析。但与 Laravel 容器相比，缺少多项关键能力：**Tagged Bindings（标签绑定）**、**Rebinding Callbacks（重绑定回调）**、**Global Resolving/AfterResolving Callbacks（全局解析回调）**、**Method Injection（方法注入）**、**Scoped Bindings（作用域绑定）**、**PSR-11 兼容**等。这些是构建复杂应用和可扩展服务提供者的基础设施。

## What Changes

- 新增 **标签绑定（Tagged Bindings）**：通过标签对一组服务进行分组，一次解析多个服务
- 新增 **全局解析回调（Resolving/AfterResolving Callbacks）**：在每次服务解析前后执行全局钩子
- 新增 **重绑定回调（Rebinding Callbacks）**：当服务被重新绑定时通知依赖方
- 新增 **方法注入（Method Injection）**：`call()` 方法支持类方法注入，与 Laravel 一致
- 新增 **作用域绑定（Scoped Bindings）**：`scoped()` 绑定在同一生命周期内共享实例
- 新增 **PSR-11 兼容**：实现 `Psr\Container\ContainerInterface`
- 新增 **条件绑定**：`when()->needs()->give()` 流畅接口
- 改进 **ContainerInterface** 接口，与 Laravel 对齐
- 改进 **异常处理**：引入 `BindingResolutionException` 替代通用 RuntimeException

## Capabilities

### New Capabilities
- `tagged-bindings`: 标签绑定 - 通过 tag 对一组服务分组，支持 `tagged()` 批量解析
- `resolving-callbacks`: 全局解析回调 - resolving/afterResolving 全局和 per-abstract 钩子
- `rebinding-callbacks`: 重绑定回调 - 服务重新绑定时触发回调通知
- `method-injection`: 方法注入增强 - call() 支持完整的方法依赖注入
- `scoped-bindings`: 作用域绑定 - scoped() 在同一请求生命周期共享实例
- `conditional-binding`: 条件绑定 - when()->needs()->give() 流畅接口
- `psr11-compliance`: PSR-11 兼容 - 实现 PSR-11 ContainerInterface
- `container-exceptions`: 容器异常体系 - BindingResolutionException 和 CircularDependencyException

### Modified Capabilities
<!-- 无现有 spec 需要修改 -->

## Impact

- **核心文件**：`bin/Container/Container.php`（主要改动）、`bin/App/App.php`（代理新方法）
- **接口文件**：`bin/Contracts/ContainerInterface.php`（扩展接口）
- **新增文件**：`bin/Container/Exceptions/BindingResolutionException.php`、`bin/Container/Exceptions/CircularDependencyException.php`
- **依赖**：可能需要 `psr/container` 包（PSR-11 接口）
- **向后兼容**：所有现有 API 保持兼容，新增方法均为增量扩展
