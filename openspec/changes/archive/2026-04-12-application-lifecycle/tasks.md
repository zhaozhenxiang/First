# Tasks: application-lifecycle

## Phase 1: 启动骨架

- [x] 设计并创建 `bootstrap/app.php` 风格的应用装配入口
- [x] 明确 HTTP 和 CLI 的入口文件职责边界
- [x] 约束 `App`、`Container`、`ProviderRepository` 的职责分层

## Phase 2: Kernel

- [x] 新增 HTTP Kernel，统一处理请求 bootstrapping 与请求分发
- [x] 新增 Console Kernel，统一处理命令 bootstrapping 与命令分发
- [x] 将 Provider 注册与 boot 顺序纳入 Kernel 生命周期

## Phase 3: Bootstrap Sequence

- [x] 定义环境、配置、异常、Provider、请求上下文的引导顺序
- [x] 为各引导阶段补充测试或可观察断言
- [x] 让现有核心服务通过统一生命周期初始化

## Phase 4: 验证

- [x] 补充生命周期测试，覆盖 HTTP / CLI 两条主路径
- [x] 确认后续 routing、middleware、exception、validation 可以接入新骨架
