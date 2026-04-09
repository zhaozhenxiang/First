# Tasks: form-request

## Phase 1: FormRequest 基类

- [x] 新增 FormRequest — rules/messages/authorize/validated/failedValidation
- [x] 验证失败响应：Web 重定向 back + API 422 JSON

## Phase 2: RouteAction 集成

- [x] doClassMethod 检测 FormRequest 参数并自动解析验证

## Phase 3: Make 命令

- [x] 新增 MakeRequestCommand — 生成 FormRequest 骨架

## Phase 4: 测试

- [x] FormRequestTest — rules 验证、authorize 拒绝、错误响应、validated 数据
