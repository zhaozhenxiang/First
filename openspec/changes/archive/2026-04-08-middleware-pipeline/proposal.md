## Why

当前 `bin/Middleware/Middleware.php` 仅 38 行，是一个裸抽象类，只有 `handle()` 和 `run()` 方法。框架没有中间件管道（Pipeline）、没有全局中间件概念、没有中间件组、没有 `terminate()` 生命周期、没有中间件参数传递。请求处理链是硬编码的顺序调用，无法实现洋葱模型的前置/后置处理。

## What Changes

- 新增 `Pipeline` 类实现洋葱模型中间件调度
- `Middleware` 基类增加 `terminate()` 生命周期钩子
- 新增全局中间件栈，在每个请求中自动执行
- 新增中间件组 (web/api 等) 概念
- 支持中间件参数 (`throttle:60,1`)
- 支持中间件排除 (`withoutMiddleware()`)
- 新增 `config/middleware.php` 配置全局/组中间件

## Capabilities

### New Capabilities
- `middleware-pipeline`: Pipeline 洋葱模型，支持 before/after/terminate 三阶段
- `middleware-groups`: 中间件分组 (web/api/global)，可配置优先级
- `middleware-parameters`: 中间件参数解析和传递 (`throttle:60,1`)
- `middleware-exclusion`: 路由级排除中间件

### Modified Capabilities

## Impact

- `bin/Middleware/Middleware.php` — 扩展基类，增加 terminate()
- `bin/Middleware/Pipeline.php` — 新增，洋葱模型调度器
- `bin/Middleware/MiddlewareStack.php` — 新增，全局/组中间件管理
- `bin/Route/RouteAction.php` — 集成 Pipeline 替代硬编码中间件调用
- `bin/Route/RouteCollection.php` — 支持 middleware 组和排除
- `config/middleware.php` — 新增配置文件
- `bin/Middleware/AuthMiddleware.php` — 适配新基类
- `bin/Middleware/CsrfMiddleware.php` — 适配新基类
- `bin/Middleware/RateLimitMiddleware.php` — 支持参数解析
- `tests/MiddlewarePipelineTest.php` — 新增测试
