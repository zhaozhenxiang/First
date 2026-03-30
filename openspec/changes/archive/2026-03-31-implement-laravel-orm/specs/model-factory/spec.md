## ADDED Requirements

### Requirement: 工厂定义注册
系统 SHALL 提供 `Factory::define()` 方法注册模型的工厂定义回调。

#### Scenario: 基本工厂定义
- **WHEN** 调用 `Factory::define(User::class, fn() => ['name' => 'test', 'email' => 'test@example.com'])`
- **THEN** 后续可通过 `Factory::create(User::class)` 创建 User 实例

### Requirement: 工厂创建模型
`Factory::create()` SHALL 根据定义的属性创建并保存模型到数据库。

#### Scenario: 创建并保存
- **WHEN** 调用 `Factory::create(User::class)`
- **THEN** SHALL 使用工厂定义的属性创建 User
- **AND** 模型 SHALL 已保存到数据库（有 ID）

### Requirement: 工厂构建不保存
`Factory::make()` SHALL 根据属性创建模型实例但不保存到数据库。

#### Scenario: 仅构建不保存
- **WHEN** 调用 `Factory::make(User::class)`
- **THEN** SHALL 返回 User 实例
- **AND** 实例 SHALL 未保存（`exists` 属性为 `false`）

### Requirement: 覆盖工厂属性
`create()` 和 `make()` SHALL 支持传入属性数组覆盖默认值。

#### Scenario: 属性覆盖
- **WHEN** 调用 `Factory::create(User::class, ['name' => 'custom'])`
- **THEN** 创建的 User 实例 `name` 属性 SHALL 为 `'custom'`
- **AND** 其他属性 SHALL 使用工厂默认值

### Requirement: 工厂状态
系统 SHALL 支持 `Factory::state()` 定义状态变体，用于生成特定场景数据。

#### Scenario: 使用状态
- **WHEN** 定义 `Factory::state(User::class, 'admin', fn() => ['role' => 'admin'])`
- **AND** 调用 `Factory::create(User::class, states: ['admin'])`
- **THEN** 创建的 User `role` SHALL 为 `'admin'`

### Requirement: 批量创建
`Factory::times()` SHALL 支持一次创建多个模型实例。

#### Scenario: 创建多个实例
- **WHEN** 调用 `Factory::times(5, User::class)`
- **THEN** SHALL 创建并保存 5 个 User 实例
- **AND** 返回 Collection 包含所有实例
