## Why

当前异常处理器已经有 `report` / `render` / 调试页 / JSON 分支，但项目里仍存在直接 `header()`、`echo`、`exit` 的处理路径，尤其在验证和部分请求流程里。这使错误出口和响应出口仍不够统一，也不利于后续接近 Laravel 13 的异常处理模型。

## What Changes

- 统一异常到响应的出口
- 明确 HTTP、JSON、CLI 三类运行时的异常渲染规则
- 收敛直接输出/退出逻辑，优先走异常和 Response 对象
- 规范 404 / 403 / 422 / 500 等常见错误路径
- 让调试模式与生产模式行为更清晰

## Capabilities

### New Capabilities
- `exception-mapping`: 常见框架异常到状态码/响应的映射
- `runtime-error-exit`: HTTP / CLI 的统一错误出口

### Modified Capabilities
- `exception-handler`: 从基础全局处理器提升为统一响应协调器
- `response-failure-flow`: 验证、授权、路由未命中等错误改走一致出口

## Impact

- `bin/Exception/ExceptionHandler.php` — 统一异常与响应出口
- `bin/Response/Response.php` — 可能需要补充错误响应辅助能力
- Validation / Route / Auth 相关错误路径 — 改为抛异常或返回标准响应
- `tests/ExceptionHandlerTest.php` / `ErrorPathTest.php` — 补充主错误路径测试
