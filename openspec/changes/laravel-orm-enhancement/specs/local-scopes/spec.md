## ADDED Requirements

### Requirement: 本地作用域定义和调用
模型 SHALL 支持通过定义 `scopeFoo(QueryBuilder $query, ...$args)` 方法创建本地作用域。通过 `Model::foo()` 或 `$query->foo()` 静态/动态调用时，SHALL 自动注入 QueryBuilder 作为第一个参数。

#### Scenario: 基本本地作用域
- **WHEN** 模型定义了 `protected function scopeActive(QueryBuilder $query): QueryBuilder` 返回 `$query->where('active', 1)`
- **THEN** `User::active()` 等价于 `User::where('active', 1)`
- **THEN** `User::where('role', 'admin')->active()` 正确链式调用

#### Scenario: 带参数的本地作用域
- **WHEN** 模型定义了 `protected function scopeOfType(QueryBuilder $query, string $type): QueryBuilder` 返回 `$query->where('type', $type)`
- **THEN** `User::ofType('admin')` 等价于 `User::where('type', 'admin')`

#### Scenario: 作用域与查询链式组合
- **WHEN** 调用 `User::active()->ofType('admin')->orderBy('name')->get()`
- **THEN** 生成的 SQL 包含 `WHERE active = 1 AND type = 'admin' ORDER BY name`

### Requirement: 作用域方法检测
`__callStatic` SHALL 优先检测本地作用域方法。若模型中存在 `scope` + 首字母大写的方法名，SHALL 调用该作用域方法，否则转发到 QueryBuilder。

#### Scenario: 避免与 QueryBuilder 方法冲突
- **WHEN** 模型定义了 `scopeWhere()` 但 QueryBuilder 也有 `where()` 方法
- **THEN** 调用 `User::where(...)` 使用 QueryBuilder 的 `where()` 方法，不触发本地作用域
