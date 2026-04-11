# Tasks: middleware-system-parity

## Phase 1: 中间件模型

- [x] 定义全局中间件、路由中间件、组、别名、优先级的数据结构
- [x] 明确这些能力在 HTTP Kernel 中的挂载点

## Phase 2: 执行链路

- [x] 调整 Pipeline 以适配统一中间件解析入口
- [x] 实现组展开、别名解析和参数处理中间件
- [x] 实现优先级排序或等价机制

## Phase 3: Kernel 集成

- [x] 将请求执行接入全局中间件链和路由中间件链
- [x] 明确请求前 / 请求后处理边界

## Phase 4: 验证

- [x] 补充全局、组、别名、顺序、短路返回测试
- [x] 验证与 route metadata、controller dispatch 的联动
