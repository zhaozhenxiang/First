## ADDED Requirements

### Requirement: firstOrCreate
模型 SHALL 支持 `firstOrCreate(array $attributes, array $values = [])` 方法。使用 `$attributes` 中的条件查找第一条记录，若不存在则创建一条新记录（合并 `$attributes` 和 `$values`）。

#### Scenario: 找到已有记录
- **WHEN** 调用 `User::firstOrCreate(['email' => 'test@test.com'])` 且数据库中存在该 email
- **THEN** 返回已存在的模型实例
- **THEN** `wasRecentlyCreated` 为 false

#### Scenario: 创建新记录
- **WHEN** 调用 `User::firstOrCreate(['email' => 'new@test.com'], ['name' => 'New User'])` 且数据库中不存在该 email
- **THEN** 创建一条新记录，属性合并为 `{email: 'new@test.com', name: 'New User'}`
- **THEN** 返回新创建的模型实例，`wasRecentlyCreated` 为 true

### Requirement: firstOrNew
模型 SHALL 支持 `firstOrNew(array $attributes, array $values = [])` 方法。查找逻辑与 `firstOrCreate` 相同，但不存在时不保存到数据库，仅返回未持久化的模型实例。

#### Scenario: 找到已有记录
- **WHEN** 调用 `User::firstOrNew(['email' => 'test@test.com'])` 且存在该记录
- **THEN** 返回已存在的模型实例，`exists` 为 true

#### Scenario: 返回未持久化实例
- **WHEN** 调用 `User::firstOrNew(['email' => 'new@test.com'], ['name' => 'New'])` 且不存在
- **THEN** 返回新模型实例，`exists` 为 false
- **THEN** 数据库中未插入新记录

### Requirement: updateOrCreate
模型 SHALL 支持 `updateOrCreate(array $attributes, array $values = [])` 方法。使用 `$attributes` 查找记录，若存在则用 `$values` 更新，若不存在则创建（合并 `$attributes` 和 `$values`）。

#### Scenario: 更新已有记录
- **WHEN** 调用 `User::updateOrCreate(['email' => 'test@test.com'], ['name' => 'Updated'])` 且存在该 email
- **THEN** 更新 name 为 'Updated'
- **THEN** `wasRecentlyCreated` 为 false

#### Scenario: 创建新记录
- **WHEN** 调用 `User::updateOrCreate(['email' => 'new@test.com'], ['name' => 'New'])` 且不存在
- **THEN** 创建新记录，属性为 `{email: 'new@test.com', name: 'New'}`

### Requirement: firstWhere
模型 SHALL 支持 `firstWhere(string|callable $column, mixed $operator = null, mixed $value = null)` 方法，等价于 `where(...)->first()`。

#### Scenario: 使用 firstWhere
- **WHEN** 调用 `User::firstWhere('email', 'test@test.com')`
- **THEN** 等价于 `User::where('email', 'test@test.com')->first()`

### Requirement: sole
模型 SHALL 支持 `sole(array|string $column, mixed $value = null)` 方法，返回唯一匹配的记录。若找到 0 条或多于 1 条，SHALL 抛出异常。

#### Scenario: 找到唯一记录
- **WHEN** 调用 `User::sole('email', 'unique@test.com')` 且恰好有 1 条匹配
- **THEN** 返回该模型实例

#### Scenario: 未找到记录
- **WHEN** 调用 `User::sole('email', 'none@test.com')` 且无匹配记录
- **THEN** 抛出 `InvalidArgumentException`

#### Scenario: 找到多条记录
- **WHEN** 调用 `User::sole('active', 1)` 且有多条匹配
- **THEN** 抛出 `InvalidArgumentException`
