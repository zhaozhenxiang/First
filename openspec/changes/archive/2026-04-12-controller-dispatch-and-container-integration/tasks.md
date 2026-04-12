# Tasks: controller-dispatch-and-container-integration

## Phase 1: 调度模型

- [x] 明确 controller action、闭包 action、middleware handle、command handle 的统一调用抽象
- [x] 识别现有代码中的手动实例化路径

## Phase 2: Dispatcher

- [x] 实现统一 controller dispatcher
- [x] 让 route action 解析产物接入 dispatcher
- [x] 统一方法参数、默认值、容器解析、路由参数映射规则

## Phase 3: 容器贯穿

- [x] 让 middleware 与 command 的调用路径尽量走容器
- [x] 让 FormRequest 作为可注入参数参与调度
- [x] 明确失败时异常与响应行为

## Phase 4: 验证

- [x] 补充 controller/middleware/command/form-request 的集成测试
- [x] 验证与现有 IoC 行为测试兼容
