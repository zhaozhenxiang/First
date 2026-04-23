# M2-A Web 状态基础

## 关联文档

- 索引页： [M2 文档索引](/home/x/src/install/php/First/docs/superpowers/specs/2026-04-18-m2-web-data-index.md)
- 上一阶段： [M1 Closure](/home/x/src/install/php/First/docs/superpowers/specs/2026-04-18-m1-runtime-skeleton-closure.md)

## 目标

在 `M1` 已统一的 runtime skeleton 之上，补齐 `Session / Cookie / CSRF` 的最小闭环，让有状态 Web 请求成为统一 request/response lifecycle 的一部分，而不是散落在 helper、入口文件或局部兼容分支里。

这一步的价值不是先把表面 API 做多，而是先把“状态从哪里进入、在哪里校验、在哪里写回”说清楚。只有这条链路稳定，后面的 `Auth`、`FormRequest`、失败重定向和用户状态恢复才不会不断返工。

## 为什么先做这一段

`M2` 必须先从 Web 状态基础开始，而不是先做 `Auth`。

原因是：

- `CSRF` 依赖稳定的 token 存储和请求态读取
- `Auth` 依赖 Session / Cookie 的恢复语义
- 验证失败、登录后跳转、登出后状态清理都依赖统一的状态写回出口

如果这一步没做稳，后面的身份和输入链路就只能建立在临时约定上。

## 范围

### In Scope

- `Session` 的启动、读取、写回、失效和 ID 轮换语义
- `Cookie` 的读取、排队写回、删除和响应传播语义
- `CSRF` token 的生成、校验、失败处理和必要的轮换语义
- Request、Middleware、Response 之间的状态边界
- 用测试固定 Web 状态主链路

### Out of Scope

- 不在这一阶段扩 `Auth` guard、登录流程或 remember-me
- 不在这一阶段扩 `Validation / FormRequest`
- 不在这一阶段大做多 driver Session 抽象或复杂 manager 体系
- 不在这一阶段处理与 `CSRF` 无关的完整浏览器安全面，例如限流、CORS、CSP
- 不在这一阶段扩 `ORM` 或数据访问体验

## 运行时主链路

这一阶段完成后，请求主链路应能稳定描述为：

1. `HttpKernel` 在 `M1` 固定的 bootstrap 骨架上接收请求。
2. Session 中间件从请求 Cookie 恢复会话标识并装配当前 Session 状态。
3. Cookie 读取和排队写回都挂在同一条 request/response 生命周期里，而不是旁路输出。
4. `CSRF` 中间件只在需要保护的请求上校验 token，并把失败交给统一异常出口。
5. 控制器、后续中间件和 helper 消费的是已经装配好的 Session / Cookie 状态。
6. 响应返回时，Session 变更和 Cookie 变更从统一出口写回，不引入额外的 header 旁路。

## 设计原则

### 1. 先把状态边界做准，再补表层 API

先保证状态流经请求主链路，而不是先追求 helper 或 facade 看起来更像 Laravel。

### 2. Session 与 Cookie 共享一个写回出口

能在一个响应出口完成的状态写回，不通过散落的 `setcookie()` 或局部兼容分支完成。

### 3. CSRF 只建立在稳定的 Session 语义上

`CSRF` 不单独开辟隐藏状态通道，而是依赖前面已经固定的 Session 生命周期。

### 4. Console 不被强行带入 Web 状态初始化

这是 Web 阶段能力，不应让 Console kernel 无故承担 Session / Cookie / `CSRF` 初始化成本。

### 5. 每个边界都需要测试锚点

`Session` 恢复、Cookie 写回、`CSRF` 成功与失败路径，都要能被明确的回归测试证明。

## 交付物

`M2-A` 完成时，建议至少交付：

1. 一条清晰的 Session 启动、读取、写回主链路。
2. 一套统一的 Cookie 读取与响应写回机制。
3. 一套稳定的 `CSRF` token 生成与校验规则。
4. 一套将 Session / Cookie / `CSRF` 接入 Middleware 与 Response 的运行时边界。
5. 一组能证明上述边界成立的回归测试。

## 验收标准

`M2-A` 结束时，应至少满足：

1. 请求可以在统一主链路中稳定读取并更新 Session 状态。
2. Cookie 的新增、覆盖和删除都通过统一响应出口传播。
3. `CSRF` 在受保护请求上能稳定放行合法 token，并把非法 token 交给统一异常出口。
4. Session ID 轮换、会话失效和状态清理有一致语义，不依赖临时旁路。
5. Console runtime 不会因为 `M2-A` 改造而被动初始化 Web 状态能力。
