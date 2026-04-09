# stub-templates Spec

## Stub 文件格式

使用 `{{ placeholder }}` 占位符，渲染时通过 `str_replace` 替换。

### 通用占位符

| 占位符 | 用途 |
|--------|------|
| `{{ namespace }}` | PHP 命名空间 |
| `{{ class }}` | 类名 |
| `{{ model }}` | 关联模型名（用于 factory/policy/observer） |
| `{{ modelVariable }}` | 模型变量名（小写） |
| `{{ table }}` | 表名（用于 migration） |

### Stub 查找顺序

1. `stubs/{stubFile}` — 项目级自定义（如果存在）
2. `bin/Console/Stubs/{stubFile}` — 框架默认

### Stub 列表

| 文件 | 生成目标 |
|------|---------|
| `model.stub` | Eloquent 模型 |
| `controller.stub` | 空控制器 |
| `controller.api.stub` | API 控制器 |
| `controller.invokable.stub` | Invokable 控制器 |
| `middleware.stub` | 中间件 |
| `migration.create.stub` | 创建表迁移 |
| `migration.update.stub` | 修改表迁移 |
| `migration.blank.stub` | 空迁移 |
| `command.stub` | CLI 命令 |
| `request.stub` | 表单请求 |
| `factory.stub` | 模型工厂 |
| `policy.stub` | 授权策略 |
| `observer.stub` | 模型观察者 |
