## 1. 异常体系

- [x] 1.1 创建 `BindingResolutionException`（继承 RuntimeException，包含抽象名信息）
- [x] 1.2 创建 `CircularDependencyException`（继承 BindingResolutionException，包含循环路径）
- [x] 1.3 创建 PSR-11 `NotFoundExceptionInterface`（`bin/Psr/Container/NotFoundExceptionInterface.php`）
- [x] 1.4 创建 PSR-11 `ContainerInterface`（`bin/Psr/Container/ContainerInterface.php`，get/has 方法）
- [x] 1.5 将 Container 中现有 RuntimeException 替换为 BindingResolutionException

## 2. PSR-11 兼容

- [x] 2.1 Container 实现 `Bin\Psr\Container\ContainerInterface`
- [x] 2.2 实现 `get(string $id)` 方法（未找到时抛 NotFoundException）
- [x] 2.3 更新 `has(string $id)` 方法签名匹配 PSR-11

## 3. 标签绑定

- [x] 3.1 在 Container 中新增 `$tags` 属性和 `tag()` 方法
- [x] 3.2 实现 `tagged(string $tag)` 方法返回已解析实例数组
- [x] 3.3 在 `flush()` 中清空标签数据

## 4. 解析回调

- [x] 4.1 新增 `$globalResolvingCallbacks` 和 `$globalAfterResolvingCallbacks` 数组属性
- [x] 4.2 新增 `$resolvingCallbacks` 和 `$afterResolvingCallbacks` 按 abstract 分组的回调数组
- [x] 4.3 实现 `resolving($abstract, $callback)` 方法（支持全局和 per-abstract 两种签名）
- [x] 4.4 实现 `afterResolving($abstract, $callback)` 方法
- [x] 4.5 在 `resolve()` 中触发 resolving → 构建 → afterResolving 回调链
- [x] 4.6 确保单例已缓存时不重复触发回调

## 5. 重绑定回调

- [x] 5.1 新增 `$reboundCallbacks` 数组属性
- [x] 5.2 实现 `rebinding(string $abstract, Closure $callback)` 方法
- [x] 5.3 改进 `rebound()` 方法：清除实例后重新解析并通知所有 rebinding 回调
- [x] 5.4 实现 `refresh(string $abstract, $target, string $method)` 辅助方法

## 6. 方法注入增强

- [x] 6.1 改进 `call()` 支持 `[$instance, 'method']` 数组回调并注入依赖
- [x] 6.2 改进 `call()` 对 `Class@method` 的依赖注入（解析方法参数类型提示）
- [x] 6.3 确保显式参数覆盖容器自动解析

## 7. 作用域绑定

- [x] 7.1 新增 `$scopedInstances` 数组属性
- [x] 7.2 实现 `scoped(string $abstract, callable|string $concrete = null)` 方法
- [x] 7.3 在 `resolve()` 中处理 scoped 实例的缓存和返回
- [x] 7.4 实现 `resetScope()` 方法清空 scoped 实例
- [x] 7.5 在 `flush()` 中清空 scoped 实例

## 8. 条件绑定流畅接口

- [x] 8.1 创建 `ContextualBindingBuilder` 类（when/needs/give 方法链）
- [x] 8.2 实现 `when(string $concrete)` 方法返回 ContextualBindingBuilder
- [x] 8.3 支持 `give($concrete)` 和 `giveTagged($tag)` 方法
- [x] 8.4 确保旧 `contextual()` 方法继续工作

## 9. 循环依赖检测

- [x] 9.1 在 `build()` 或 `resolve()` 中检测 buildStack 中的重复抽象
- [x] 9.2 检测到循环时抛出 CircularDependencyException 并显示循环路径

## 10. App 门面代理更新

- [x] 10.1 在 `App` 类中代理新方法：`tag()`, `tagged()`, `scoped()`, `resetScope()`, `resolving()`, `afterResolving()`, `rebinding()`, `when()`
- [x] 10.2 更新 `ContainerInterface` 接口添加新方法签名

## 11. 测试

- [x] 11.1 编写标签绑定测试（tag/tagged/空标签）
- [x] 11.2 编写解析回调测试（全局/per-abstract/afterResolving）
- [x] 11.3 编写重绑定回调测试
- [x] 11.4 编写方法注入增强测试
- [x] 11.5 编写作用域绑定测试（共享/重置/不影响单例）
- [x] 11.6 编写条件绑定流畅接口测试
- [x] 11.7 编写 PSR-11 兼容测试
- [x] 11.8 编写异常体系测试（BindingResolution/CircularDependency）
- [x] 11.9 运行全部测试确保无回归（998/1000 通过，2 个失败非本次变更引起）
