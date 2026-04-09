# Design: form-request

## 核心设计

FormRequest 继承 Request，在实例化时自动执行验证。验证失败根据请求类型决定响应：
- Web 请求：重定向回上一页 + 闪存错误 + 闪存旧输入
- API 请求（Accept: json / AJAX）：返回 422 JSON

## 新增文件

### bin/Validation/FormRequest.php
继承 `Bin\Request\Request`，核心方法：
- `rules(): array` — 子类定义验证规则
- `messages(): array` — 自定义错误消息
- `authorize(): bool` — 授权检查（默认 true）
- `validated(): array` — 获取已验证数据
- `failedValidation(ValidationManager $validator): void` — 验证失败处理

### bin/Console/Commands/MakeRequestCommand.php
生成 FormRequest 类骨架到 `app/Requests/`。

## 修改文件

### bin/Route/RouteAction.php
- `doClassMethod()` 检测控制器方法参数是否为 FormRequest 子类
- 如果是，自动创建 FormRequest 实例并执行验证
- 验证失败时直接返回错误响应（不再继续到控制器）

## 数据流

```
Route::post('/posts', 'PostController@store')

PostController@store(StorePostRequest $request)
  → RouteAction 检测到 FormRequest 类型
  → 创建 StorePostRequest 实例
  → 调用 authorize() → false → 403
  → 调用 validate() 使用 rules() + messages()
  → 失败 → 重定向 back() / 422 JSON
  → 成功 → 注入到控制器方法
```
