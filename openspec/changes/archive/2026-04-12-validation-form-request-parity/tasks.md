# Tasks: validation-form-request-parity

## Phase 1: 生命周期设计

- [x] 固定 FormRequest 的构造、注入、解析与执行时机
- [x] 明确授权失败、验证失败、验证成功后的统一行为

## Phase 2: Hook 能力

- [x] 实现 `prepareForValidation`
- [x] 实现 `after`
- [x] 实现 `passedValidation`

## Phase 3: 失败策略

- [x] 标准化 JSON 422 与 Web 302/错误袋/旧输入行为
- [x] 让错误响应走统一异常/响应出口，而非分散 `exit`

## Phase 4: 验证

- [x] 扩展 `FormRequestTest` 和验证测试
- [x] 验证与 controller dispatcher、session、exception flow 的联动
