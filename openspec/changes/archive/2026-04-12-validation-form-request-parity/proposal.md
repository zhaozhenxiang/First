## Why

当前 `FormRequest` 已经具备 `rules()`、`authorize()`、`validated()` 和基础失败响应，但与 Laravel 13 相比仍然缺少验证前后钩子、依赖注入、错误袋与重定向策略等完整生命周期能力。验证逻辑要继续扩展，必须先把边界定义清楚。

## What Changes

- 完整化 FormRequest 生命周期
- 支持 `prepareForValidation`、`passedValidation`、`after`
- 明确授权失败与验证失败的异常/响应出口
- 规范错误袋、旧输入闪存、JSON 与 Web 响应分支
- 与 controller dispatch 和 exception flow 统一集成

## Capabilities

### New Capabilities
- `form-request-hooks`: 验证前预处理、验证后处理和附加校验
- `validation-failure-policies`: JSON / Web 失败响应策略
- `error-bag-runtime`: 错误袋与旧输入闪存管理

### Modified Capabilities
- `form-request`: 从基础请求子类提升为完整验证生命周期对象
- `validation-dispatch`: 验证执行时机与 controller dispatch 对齐

## Impact

- `bin/Validation/FormRequest.php` — 生命周期与失败处理扩展
- `bin/Validation/*` — 可能需要增加 hook 支持与错误包装
- `bin/Exception/*` / 响应层 — 验证失败出口统一
- `tests/FormRequestTest.php` / `Validation*` — 补充生命周期测试
