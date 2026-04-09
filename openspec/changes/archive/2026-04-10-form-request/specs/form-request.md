# Spec: form-request

## FormRequest 基类

```php
abstract class FormRequest extends Request
{
    // 子类必须实现
    abstract public function rules(): array;

    // 可选覆盖
    public function authorize(): bool { return true; }
    public function messages(): array { return []; }

    // 获取已验证数据
    public function validated(): array;

    // 验证入口（构造时自动调用）
    public function validateResolved(): void;
}
```

## 验证流程

1. `authorize()` 返回 false → 抛 403
2. `validate(rules(), messages())` 执行
3. 成功 → `validated()` 返回已验证数据
4. 失败 → `failedValidation()`:
   - `expectsJson()` → 422 JSON `{"message": "...", "errors": {...}}`
   - 否则 → 重定向 back()，错误闪存到 session，旧输入闪存

## 自动解析

RouteAction 通过 Reflection 检测控制器参数类型：
- 参数是 FormRequest 子类 → 自动创建 + 验证 + 注入
- 验证失败 → 直接返回响应（不进入控制器）
