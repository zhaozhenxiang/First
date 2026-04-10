## Why

当前 `bin/Validation/Validator.php` (147 行) 本质上是输入清理工具，仅有 8 个 XSS 转义/类型转换方法。框架完全没有表单验证能力——没有规则引擎、没有错误消息、没有自定义规则、没有 FormRequest。这是日常开发中影响最大的缺失：每个表单和 API 端点都无法进行数据验证。

## What Changes

- 重写 `Validator` 为 Laravel 风格的规则引擎，支持 40+ 内置验证规则
- 新增 `MessageBag` 类管理验证错误消息
- 新增 `ValidationRule` 基类支持自定义规则对象
- 新增 `Rule` 流式构建器 (Rule::required()->string()->max(255))
- 支持条件验证 (`sometimes`) 和数据准备 (`prepareForValidation`)
- `ValidationManager` 重构为工厂 + 验证器注册中心
- 新增 `config/validation.php` 配置文件

## Capabilities

### New Capabilities
- `validation-rules`: 内置验证规则引擎 (required/nullable/string/int/numeric/email/url/array/boolean/date/confirmed/min/max/size/between/in/not_in/unique/exists/regex/alpha/alpha_num/ip/json/file/image/mime_types/prohibited/same/different/starts_with/ends_with/uuid/mac_address 等 40+ 规则)
- `validation-messages`: 多语言错误消息 + MessageBag，支持 :attribute/:min/:max 等占位符替换
- `custom-rules`: ValidationRule 基类 + Rule 流式构建器 + 闭包规则
- `conditional-validation`: sometimes/when/unless 条件验证

### Modified Capabilities
- `validation-manager`: 重构为工厂模式，支持规则注册和自定义消息覆盖

## Impact

- `bin/Validation/Validator.php` — 完全重写，从清理工具变为规则引擎
- `bin/Validation/ValidationManager.php` — 重构为工厂 + 注册中心
- `bin/Validation/MessageBag.php` — 新增
- `bin/Validation/ValidationRule.php` — 新增，自定义规则基类
- `bin/Validation/Rule.php` — 新增，流式规则构建器
- `bin/Validation/rules/` — 新增目录，内置规则实现文件
- `config/validation.php` — 新增配置文件
- `bin/Func/helpers/validation.php` — `validate()` 函数重写
- `tests/ValidationTest.php` — 新增完整测试
