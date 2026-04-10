# Tasks: validation-engine

## Phase 1: MessageBag + 错误消息增强

- [x] 新增 `MessageBag` 类，支持多错误/字段、first()/all()/has()/get() 方法
- [x] ValidationManager 的 `$errors` 改为使用 MessageBag，支持单字段多条错误

## Phase 2: 自定义规则对象 + Rule 构建器

- [x] 新增 `ValidationRule` 抽象基类，支持 passes()/message() 方法
- [x] 新增 `Rule` 流式构建器 (Rule::required()->string()->max(255))
- [x] ValidationManager 支持解析 ValidationRule 和 Rule 对象

## Phase 3: 条件验证 + 边界规则

- [x] 新增 `sometimes()` 条件验证支持
- [x] 补充缺失规则：uuid/mac_address/prohibited/distinct/timezone
- [x] 新增 `config/validation.php` 配置文件

## Phase 4: 测试

- [x] 新增 `tests/ValidationEngineTest.php` 覆盖 MessageBag、自定义规则、Rule 构建器、条件验证
