## 1. EventDispatcher 核心

- [x] 1.1 创建 `Bin\Events\EventDispatcher` 类：实现 `listen()`, `dispatch()`, `forget()`, `hasListeners()` 方法
- [x] 1.2 实现监听器存储结构：`array<string, array<int, callable>>` + 通配符 `*` 键
- [x] 1.3 实现 `dispatch()` 停止传播逻辑：监听器返回 `false` 时中断后续调用
- [x] 1.4 实现通配符事件匹配：分发时额外触发 `*` 键下的监听器
- [x] 1.5 实现事件对象支持：传入对象时用类名作为事件名，对象作为第一个参数
- [x] 1.6 编写测试：分发/停止传播/通配符/事件对象

## 2. 监听器解析

- [x] 2.1 实现闭包监听器：直接调用，payload 展开为参数
- [x] 2.2 实现 `Class@method` 格式解析：容器解析类实例，调用指定方法
- [x] 2.3 实现可调用类解析：容器解析后调用 `__invoke()`
- [x] 2.4 实现容器集成：`setContainer()` 方法，无容器时 `new` 实例化
- [x] 2.5 编写测试：三种监听器格式解析 + 容器自动注入

## 3. 事件订阅者

- [x] 3.1 创建 `Bin\Events\Subscriber` 接口：`subscribe(EventDispatcher $events): void`
- [x] 3.2 实现 `EventDispatcher::subscribe(Subscriber $subscriber)` 方法
- [x] 3.3 支持订阅者中 `[$this, 'method']` 数组格式注册监听器
- [x] 3.4 编写测试：订阅者批量注册和独立触发

## 4. 容器集成与 Facade

- [x] 4.1 创建 `Bin\Providers\EventServiceProvider`：注册 `EventDispatcher` 单例到容器（别名 `events`）
- [x] 4.2 创建 `Bin\Facade\Event` Facade：代理 `events` 绑定
- [x] 4.3 在 `App::$defaultProviders` 中注册 EventServiceProvider
- [ ] 4.4 编写测试：容器解析、Facade 静态调用

## 5. Model 事件迁移

- [x] 5.1 修改 `ModelEventDispatcher`：委托给 `EventDispatcher` 单例（延迟初始化）
- [x] 5.2 修复 `dispatchForModel()` bug：model-specific 监听器返回 `false` 时停止传播
- [x] 5.3 验证现有 Model 事件测试（ModelEventTest、ObserverTest）全部通过
- [x] 5.4 验证 `withoutEvents()` 继续正常工作
- [ ] 5.5 编写测试：模型事件通过新 EventDispatcher 分发

## 6. 集成验证

- [x] 6.1 运行完整测试套件，确保无回归（929/931 通过，2 个预存在的 ConsoleTest 失败）
- [ ] 6.2 验证 Event Facade 可在控制器中使用
- [ ] 6.3 验证模型事件与通用事件可共存
