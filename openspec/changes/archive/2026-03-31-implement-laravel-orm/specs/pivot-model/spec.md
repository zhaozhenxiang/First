## ADDED Requirements

### Requirement: Pivot 模型基类
系统 SHALL 提供 `Pivot` 类继承 `Model`，用于表示多对多关联的中间表记录。

#### Scenario: Pivot 基本实例化
- **WHEN** 创建 Pivot 实例
- **THEN** 实例 SHALL 默认 `$incrementing = false`、`$timestamps = false`
- **AND** 主键 SHALL 默认为 `'id'`（可通过 `$primaryKey` 配置）

### Requirement: Pivot 属性水合
BelongsToMany 关系 SHALL 在查询结果中自动水合 `pivot` 属性对象，包含中间表的所有列。

#### Scenario: 自动水合 pivot 属性
- **WHEN** User 模型通过 `belongsToMany(Role::class)` 关联查询
- **AND** 中间表 `role_user` 包含 `user_id`、`role_id`、`expires_at` 列
- **THEN** 每个结果 Role 模型 SHALL 包含 `pivot` 属性
- **AND** `pivot` 对象 SHALL 可访问 `expires_at` 等中间表列

### Requirement: 自定义 Pivot 模型
BelongsToMany SHALL 支持指定自定义 Pivot 模型类，用于处理中间表数据。

#### Scenario: 使用自定义 Pivot 类
- **WHEN** 定义 `return $this->belongsToMany(Role::class)->using(UserRolePivot::class)`
- **THEN** pivot 属性 SHALL 为 `UserRolePivot` 实例而非默认 Pivot

### Requirement: Pivot 列选择
BelongsToMany SHALL 支持 `withPivot()` 方法指定额外中间表列。

#### Scenario: 指定额外 pivot 列
- **WHEN** 调用 `withPivot('expires_at', 'assigned_by')`
- **THEN** 查询 SHALL 包含这些列
- **AND** pivot 属性 SHALL 包含这些列的值

### Requirement: Pivot 时间戳
BelongsToMany SHALL 支持 `withTimestamps()` 方法自动管理中间表的 created_at/updated_at。

#### Scenario: 启用时间戳
- **WHEN** 调用 `withTimestamps()`
- **THEN** attach/sync 操作 SHALL 自动设置 `created_at` 和 `updated_at`
