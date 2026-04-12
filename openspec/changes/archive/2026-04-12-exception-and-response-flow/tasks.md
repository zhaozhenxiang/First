# Tasks: exception-and-response-flow

## Phase 1: 错误出口梳理

- [x] 识别当前框架中直接 `header/echo/exit` 的主要路径
- [x] 归类 HTTP、JSON、CLI 三类错误处理场景

## Phase 2: 映射与渲染

- [x] 完善异常类型到状态码/响应的映射
- [x] 统一调试模式与生产模式渲染
- [x] 明确 JSON 与 HTML 错误响应策略

## Phase 3: 收敛分散逻辑

- [x] 将验证、授权、路由未命中等路径改为统一异常/响应出口
- [x] 减少运行时直接输出与直接终止

## Phase 4: 验证

- [x] 补充异常处理主路径测试
- [x] 验证与 FormRequest、Route、Auth、Kernel 的联动
