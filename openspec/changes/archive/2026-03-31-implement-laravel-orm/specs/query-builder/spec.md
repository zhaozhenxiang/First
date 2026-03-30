## MODIFIED Requirements

### Requirement: whereBetween 参数绑定
whereBetween SHALL 使用位置参数 `?` 占位符，绑定值 SHALL 正确追加到 WHERE 绑定数组。

#### Scenario: whereBetween 绑定
- **WHEN** 调用 `whereBetween('age', [18, 65])`
- **THEN** 生成 SQL SHALL 为 `age BETWEEN ? AND ?`
- **AND** 绑定数组 SHALL 包含 `[18, 65]` 两个值

#### Scenario: whereBetween 与其他 WHERE 条件组合
- **WHEN** 先调用 `where('active', 1)` 再调用 `whereBetween('age', [18, 65])`
- **THEN** WHERE 绑定 SHALL 按顺序包含 `[1, 18, 65]`

### Requirement: update 语句参数绑定
update SHALL 使用位置参数 `?` 而非命名参数 `:key`，SET 子句和 WHERE 子句的绑定值 SHALL 统一处理。

#### Scenario: update 带条件
- **WHEN** 调用 `where('id', 1)->update(['name' => 'test'])`
- **THEN** SQL SHALL 为 `UPDATE table SET name = ? WHERE id = ?`
- **AND** 绑定数组 SHALL 为 `['test', 1]`

#### Scenario: update 无条件
- **WHEN** 调用 `update(['status' => 'active'])` 无 WHERE 条件
- **THEN** SQL SHALL 为 `UPDATE table SET status = ?`
- **AND** 绑定数组 SHALL 为 `['active']`
