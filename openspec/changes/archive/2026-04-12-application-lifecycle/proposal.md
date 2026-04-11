## Why

当前框架已经有 `App`、`Container`、`ProviderRepository` 和若干 Service Provider，但启动流程仍然偏分散：应用入口、HTTP 请求处理、CLI 处理、Provider 注册来源和 boot 时机没有形成 Laravel 13 风格的统一生命周期。这会让后续路由、中间件、异常、验证、Console 的行为边界继续分散。

## What Changes

- 引入统一的应用引导入口，明确 `bootstrap/app.php` 风格的应用构建点
- 拆分 HTTP 与 Console 运行时，增加对应 Kernel
- 定义 bootstrapping 顺序：环境、配置、异常、Provider、请求上下文
- 统一 Provider 注册来源与启动顺序，减少隐式初始化
- 为后续路由、中间件、验证、Console、异常流提供稳定宿主

## Capabilities

### New Capabilities
- `application-bootstrap`: 统一应用构建入口与运行时装配
- `http-kernel`: HTTP 请求生命周期入口
- `console-kernel`: CLI 生命周期入口
- `bootstrap-sequence`: 明确的引导阶段与顺序

### Modified Capabilities
- `service-provider-runtime`: Provider 注册与 boot 时机统一纳入应用生命周期
- `app-runtime`: `App` 从“容器门面”扩展为“生命周期协调者”

## Impact

- `bin/App/App.php` — 收敛为应用核心容器与生命周期协调点
- `bin/Providers/ProviderRepository.php` — 对齐统一注册/启动时机
- `public/` 与 CLI 入口文件 — 接入 Kernel
- `bootstrap/` — 新增应用装配入口与 bootstrappers
- 后续 HTTP / Console / Exception / Validation 相关模块将基于本 change 演进
