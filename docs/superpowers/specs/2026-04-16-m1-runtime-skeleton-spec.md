# M1 运行时骨架统一 Spec

## 文档定位

这份文档是当前项目对齐 `Laravel 13` 的主 spec，聚焦：

- 先看清当前项目与 Laravel 13 的核心差异
- 明确为什么下一阶段优先做 `M1`
- 固定 `M1` 的目标、范围、边界、交付物和验收标准

执行细节已经拆到附录文档：

- 任务拆解： [M1 任务拆解](/home/x/src/install/php/First/docs/superpowers/specs/2026-04-16-m1-runtime-skeleton-tasks.md)
- 改造顺序： [M1 改造顺序](/home/x/src/install/php/First/docs/superpowers/specs/2026-04-16-m1-runtime-skeleton-order.md)
- 测试清单： [M1 测试清单](/home/x/src/install/php/First/docs/superpowers/specs/2026-04-16-m1-runtime-skeleton-tests.md)
- 文档索引： [M1 文档索引](/home/x/src/install/php/First/docs/superpowers/specs/2026-04-16-m1-runtime-skeleton-index.md)

---

## 1. 背景

当前项目已经具备明显的 Laravel 风格骨架：

- 有 `bootstrap/app.php` 入口
- 有 `config/*` 配置体系
- 有 Service Provider、Container、HTTP Kernel、Console Kernel
- 有 Route、Middleware、ControllerDispatcher、FormRequest
- 有 Eloquent 风格 ORM、Migration、Factory、Policy、RateLimiter
- 有 `make:*` 命令和基础测试体系

这意味着项目已经过了“从零开始做一个框架”的阶段，进入了 **Laravel 风格内核补齐阶段**。

当前最主要的问题，不再是“有没有功能”，而是：

- 生命周期是否一致
- 子系统是否通过统一契约协作
- 默认行为是否稳定
- 扩展边界是否清晰

---

## 2. 对齐目标

对齐 `Laravel 13`，不是追求表面 API 数量，而是优先对齐这些能力：

- 统一的应用启动生命周期
- 容器驱动的装配方式
- 统一的 HTTP / Console 运行时主链路
- 一致的异常出口和响应出口
- 能承载上层能力的路由基础设施
- 后续能够自然承接 Session、Auth、Queue、Event、Cache 等子系统

一句话说，目标是把项目从“多个 Laravel 风格模块的集合”推进到“具备统一运行时骨架的框架”。

---

## 3. 差异优先级

## P0：必须优先补齐

这些差异直接决定主链路是否稳定。

### 1. Bootstrap 生命周期

当前已有入口和基础配置，但仍需固定 bootstrap 阶段顺序，统一 HTTP / Console 的共享初始化，并制度化 Provider 的 `register` / `boot` 调用时机。

### 2. 容器装配中心

当前容器已经可用，但还需要进一步升级为框架唯一可信的装配中心，避免部分子系统走容器、部分逻辑直接实例化。

### 3. HTTP Kernel 主链路

当前路由、中间件、控制器调度、请求响应都已存在，但需要继续收口成稳定的请求生命周期。

### 4. 异常统一出口

当前已有异常类和部分异常处理能力，但还需要统一 `report` / `render` 语义，打通 HTTP / Console 双出口。

### 5. Response 归一化

当前已有基础响应支持，但还需要补齐统一响应工厂，以及字符串、数组、异常、重定向、文件、流式输出的标准响应路径。

### 6. 路由绑定与 URL 生成

当前已有路由、分组、资源路由、绑定基础，但还需要把命名路由、URL 生成、绑定失败语义固定为稳定基础设施。

## P1：主链路稳定后尽快补齐

这些能力不应抢在 `P0` 前面，但 `P0` 稳定后需要立刻推进。

- Session / Cookie / CSRF / Auth Web 安全链
- Validation 生态补齐
- ORM 从 Eloquent 风格升级到更完整的 Eloquent 体系
- Console Kernel 向完整 Artisan 运行时继续对齐
- Queue / Job / Failed Job
- Event / Listener / Observer
- Cache 统一驱动层

## P2：重要增强

这些能力重要，但不应打断主链路收口。

- Provider 生态完善
- 测试开发体验进一步对齐 Laravel
- Facade / Manager / Driver 模式
- 包开发与生态接口
- AI Native、向量检索、语义能力

---

## 4. 三阶段路线

## M1：运行时骨架统一

目标是统一启动、装配、HTTP 主链路、异常出口、响应出口、路由基础设施。

## M2：Web 与数据主链路补齐

目标是补齐 Session、Cookie、CSRF、Auth、Validation、ORM 生命周期与常用数据访问体验。

## M3：工程化运行时完成

目标是补齐 Console、Queue、Event、Cache 等 Laravel 作为工程框架的关键能力。

---

## 5. 为什么先做 M1

如果没有 `M1`，后面的能力会持续返工。

具体表现通常是：

- HTTP 和 Console 各自有一套启动逻辑
- 容器不是唯一装配中心
- 控制器、验证、异常、响应之间存在旁路
- 路由只能分发，不能稳定承载 URL 生成和绑定语义
- 新加 Session、Auth、Queue 时不断遇到初始化时序和出口不一致问题

因此 `M1` 不是附属优化，而是后续所有模块的前置条件。

---

## 6. M1 范围

`M1` 只做运行时骨架统一，不扩张新功能面。

### In Scope

- Bootstrap 生命周期统一
- 容器装配中心统一
- HTTP Kernel 主链路收口
- 异常统一出口
- Response 归一化
- 路由绑定与 URL 生成规则固化

### Out of Scope

- 不先大做 Session / Auth / Queue / Cache
- 不先大规模扩 ORM 新特性
- 不先引入复杂 Facade / Manager / Driver 抽象
- 不先做大范围目录重排或命名重构
- 不先做包生态、AI 能力等外围扩展

---

## 7. M1 设计原则

### 1. 先统一生命周期，再增加外层 API

优先判断一项改动是否让启动、装配、执行路径和出口更统一，而不是先看它是否更像 Laravel。

### 2. 优先收口主链路

`M1` 的重点始终是：

- Bootstrap
- Container
- HTTP Kernel
- Exception
- Response
- Route Infrastructure

### 3. 接口少动，运行时边界改准

尽量不做表层 API 大改，优先修正底层调用路径、初始化顺序和出口语义。

### 4. 优先删除重复逻辑

能通过收口和删除重复初始化来解决的问题，不通过叠兼容分支解决。

### 5. 每次收口都要有测试锚点

`M1` 的改动必须能被骨架级回归测试证明没有漂移。

---

## 8. M1 交付物

`M1` 完成时，建议至少交付：

1. 一套统一的 bootstrap 生命周期实现。
2. 一套容器化的核心装配路径。
3. 一条可清晰描述的 HTTP 请求主链路。
4. 一套统一异常处理出口。
5. 一套统一响应归一化机制。
6. 一套稳定的路由绑定与 URL 生成规则。
7. 一组能证明上述边界成立的回归测试。

---

## 9. M1 验收标准

满足下面这 6 条，才算 `M1` 真正完成：

1. HTTP 与 Console 共用统一启动骨架。
2. 核心对象已经由容器稳定装配。
3. 请求生命周期可以清晰描述并由测试覆盖。
4. 异常从统一出口转换。
5. 控制器与异常处理器共享同一响应体系。
6. 路由已经能承担 URL 生成和绑定基础设施职责。

---

## 10. M1 风险

### 风险 1

边做骨架收口，边继续扩大量新特性。

### 风险 2

只补表层 API，不改运行时边界。

### 风险 3

把容器改造直接做成大规模翻新，导致主链路和回归面同时失控。

---

## 11. 实施入口

如果现在要正式开工，建议按这个顺序进入：

1. 看索引： [M1 文档索引](/home/x/src/install/php/First/docs/superpowers/specs/2026-04-16-m1-runtime-skeleton-index.md)
2. 看任务： [M1 任务拆解](/home/x/src/install/php/First/docs/superpowers/specs/2026-04-16-m1-runtime-skeleton-tasks.md)
3. 看顺序： [M1 改造顺序](/home/x/src/install/php/First/docs/superpowers/specs/2026-04-16-m1-runtime-skeleton-order.md)
4. 看测试： [M1 测试清单](/home/x/src/install/php/First/docs/superpowers/specs/2026-04-16-m1-runtime-skeleton-tests.md)

如果直接进入代码，优先阅读这些入口文件：

- [bootstrap/app.php](/home/x/src/install/php/First/bootstrap/app.php)
- [bin/App/App.php](/home/x/src/install/php/First/bin/App/App.php)
- [bin/Foundation/HttpKernel.php](/home/x/src/install/php/First/bin/Foundation/HttpKernel.php)
- [bin/Foundation/ConsoleKernel.php](/home/x/src/install/php/First/bin/Foundation/ConsoleKernel.php)
- [bin/Container/Container.php](/home/x/src/install/php/First/bin/Container/Container.php)
- [bin/Providers/ProviderRepository.php](/home/x/src/install/php/First/bin/Providers/ProviderRepository.php)
- [bin/Route/RouteCollection.php](/home/x/src/install/php/First/bin/Route/RouteCollection.php)
- [bin/Routing/ControllerDispatcher.php](/home/x/src/install/php/First/bin/Routing/ControllerDispatcher.php)
- [bin/Exception/ExceptionHandler.php](/home/x/src/install/php/First/bin/Exception/ExceptionHandler.php)
- [bin/Response/Response.php](/home/x/src/install/php/First/bin/Response/Response.php)

---

## 12. 结论

当前项目与 `Laravel 13` 的核心差距，已经不再是“有没有路由 / ORM / 命令”，而是：

**是否已经形成统一、稳定、可扩展的运行时生命周期。**

`M1` 的价值就在于先把这件事做对。骨架一旦收稳，`M2` 和 `M3` 才会进入低返工状态。
