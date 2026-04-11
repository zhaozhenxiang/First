## Why

当前项目已有 `Pipeline`、`MiddlewareStack` 和若干中间件类，但中间件系统还没有形成 Laravel 13 那种完整的“全局中间件 + 路由中间件 + group + alias + priority”的治理模型。后续如果继续增加中间件，会出现注册点分散、顺序不稳定和测试成本上升的问题。

## What Changes

- 定义全局中间件、路由中间件、组和别名
- 引入中间件优先级与统一解析入口
- 将中间件执行接入 HTTP Kernel
- 规范 middleware 参数解析、组展开和执行顺序
- 明确 terminable / post-response 能力是否支持及其边界

## Capabilities

### New Capabilities
- `global-middleware`: 请求全局中间件链
- `middleware-aliases`: 中间件别名注册与解析
- `middleware-groups`: 中间件组定义与展开
- `middleware-priority`: 中间件顺序控制

### Modified Capabilities
- `middleware-pipeline`: Pipeline 由通用工具升级为框架级中间件执行系统
- `route-middleware`: 路由级中间件挂载行为标准化

## Impact

- `bin/Middleware/Pipeline.php` — 作为底层执行器继续保留，但接入更上层调度
- `bin/Middleware/*` — 组、别名、优先级的注册与解析增强
- `bin/Providers/*` / Kernel — 中间件系统注册入口
- `tests/Middleware*` — 扩展全局/组/别名/顺序测试
