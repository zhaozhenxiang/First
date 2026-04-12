## Why

当前容器能力已经很强，但“容器会不会被用到”仍然取决于各模块各自实现。要接近 Laravel 13，仅仅有 `bind` / `singleton` / `contextual` 还不够，必须把容器深度接入 controller、middleware、command、FormRequest 等调度链路。

## What Changes

- 统一 controller action 调度器
- 将方法注入、参数解析、容器调用规则标准化
- 让 FormRequest、middleware、command 在调度时可自动解析依赖
- 收敛“哪里自己 new、哪里走容器”的边界
- 降低各子系统对手写实例化逻辑的依赖

## Capabilities

### New Capabilities
- `controller-dispatcher`: 控制器动作统一调度与参数解析
- `container-call-runtime`: 容器驱动的方法调用运行时

### Modified Capabilities
- `ioc-integration`: 容器从“可选工具”升级为默认调度底座
- `method-injection`: HTTP / CLI / 中间件调用路径中的注入行为统一

## Impact

- `bin/Container/Container.php` — 继续作为注入底层，但更多场景将直接依赖它
- 控制器分发相关代码与路由 action 解析逻辑 — 接入统一 dispatcher
- `bin/Console/*` / `bin/Middleware/*` / `bin/Validation/*` — 调度时改为优先走容器
- `tests/Ioc*`、路由与控制器测试 — 增加贯通用例
