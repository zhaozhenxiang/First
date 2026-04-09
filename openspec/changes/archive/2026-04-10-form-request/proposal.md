## Why

当前没有 FormRequest 抽象。每个 Controller 方法都需要手动提取输入、验证数据、处理错误。Laravel 的 FormRequest 将验证逻辑和授权检查从 Controller 中解耦到独立的请求类中，是实现关注点分离的核心模式。本 change 依赖 `validation-engine` 的规则引擎。

## What Changes

- 新增 `FormRequest` 基类，支持 `rules()` / `messages()` / `authorize()` 方法
- 路由调度时自动解析 FormRequest 并执行验证
- 验证失败自动重定向回上一页并闪存错误和旧输入
- API 请求验证失败返回 422 JSON 响应
- 新增 `@error` Blade 指令支持 (依赖 `blade-template`)
- 新增 `$request->validated()` 获取已验证数据
- 新增 `make:request` 命令

## Capabilities

### New Capabilities
- `form-request`: FormRequest 基类 + 自动验证 + 授权检查
- `request-validation-hook`: 路由调度时 FormRequest 自动解析和验证
- `validation-error-display`: 验证错误闪存 + @error 指令显示

### Modified Capabilities

## Impact

- `bin/Validation/FormRequest.php` — 新增，FormRequest 基类
- `bin/Route/RouteAction.php` — 集成 FormRequest 自动解析
- `bin/Request/Request.php` — 新增 validated() 方法
- `bin/Console/Commands/MakeRequestCommand.php` — 新增
- `app/Requests/` — 新增目录，存放用户 FormRequest 类
- `tests/FormRequestTest.php` — 新增测试
