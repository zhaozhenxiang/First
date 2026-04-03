## MODIFIED Requirements

### Requirement: BelongsToMany returns Pivot model instances
BelongsToMany 关系查询 SHALL 返回包含 `Pivot` 模型实例的结果，而非 `stdClass`。pivot 数据 SHALL 通过 `pivot` 属性以 `Pivot` 实例访问。

#### Scenario: 关系查询返回 Pivot 实例
- **WHEN** 通过 `$user->roles` 获取 BelongsToMany 关系
- **THEN** 每个 Role 模型的 `pivot` 属性为 `Pivot` 类实例
- **AND** `$role->pivot->role_id` 可正常访问

#### Scenario: withPivot 附加列可访问
- **WHEN** 定义 `belongsToMany(Role::class)->withPivot('expires_at')`
- **AND** 查询关系结果
- **THEN** `$role->pivot->expires_at` 返回对应值
- **AND** `$role->pivot` 为 `Pivot` 实例

#### Scenario: 自定义 Pivot 类
- **WHEN** 定义 `belongsToMany(Role::class)->using(UserRolePivot::class)`
- **THEN** pivot 属性为 `UserRolePivot` 实例（而非默认 Pivot）

### Requirement: Pivot model supports attribute access
Pivot 模型 SHALL 支持 `ArrayAccess` 和属性访问器方式访问中间表字段。

#### Scenario: 属性访问
- **WHEN** Pivot 实例有 `user_id` 和 `role_id` 字段
- **THEN** `$pivot->user_id` 和 `$pivot['user_id']` 都返回对应值

#### Scenario: Pivot delete 使用复合键
- **WHEN** 调用 `$pivot->delete()`
- **THEN** 使用 `foreignKey + relatedKey` 复合条件删除中间表记录
