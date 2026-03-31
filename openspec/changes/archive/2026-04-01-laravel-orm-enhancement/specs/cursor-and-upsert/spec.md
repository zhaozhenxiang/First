## ADDED Requirements

### Requirement: cursor 游标查询
QueryBuilder SHALL 支持 `cursor()` 方法，返回 `\Generator`，每次 yield 一个 hydrate 后的 Model 实例。SHALL 使用 `PDO::FETCH_LAZY` 或逐行 fetch 避免一次性加载所有数据到内存。

#### Scenario: 遍历大量记录
- **WHEN** 调用 `User::where('active', 1)->cursor()` 且有 10000 条记录
- **THEN** 返回 Generator 对象
- **THEN** 每次迭代 yield 一个 User 模型实例
- **THEN** 内存中不同时持有所有 10000 条记录

#### Scenario: cursor 与 foreach 配合
- **WHEN** 使用 `foreach (User::cursor() as $user)`
- **THEN** 逐条处理每个用户，不超出内存限制

#### Scenario: cursor 返回原始数组
- **WHEN** QueryBuilder 没有 modelClass 时调用 `cursor()`
- **THEN** 每次 yield 一个关联数组

### Requirement: upsert 批量插入或更新
QueryBuilder SHALL 支持 `upert(array $values, array|string $uniqueBy, array $updateColumns = null)` 方法。使用 MySQL 的 `INSERT ... ON DUPLICATE KEY UPDATE` 语法。

#### Scenario: 基本批量 upsert
- **WHEN** 调用 `User::upsert([['email' => 'a@test.com', 'name' => 'A'], ['email' => 'b@test.com', 'name' => 'B']], ['email'])`
- **THEN** 若 email 已存在则更新 name，不存在则插入

#### Scenario: 指定更新列
- **WHEN** 调用 `User::upsert([...], ['email'], ['name'])`
- **THEN** 冲突时只更新 `name` 列

#### Scenario: 不指定更新列时更新所有列
- **WHEN** 调用 `User::upsert([...], ['email'])` 且不传第三个参数
- **THEN** 冲突时更新 values 中除唯一键外的所有列

### Requirement: cursor 返回 LazyCollection
`cursor()` 方法 SHALL 支持 `cursor()->filter()`、`cursor()->map()` 等链式操作。返回对象 SHALL 实现 `Iterator` 接口，支持基本的集合操作。

#### Scenario: cursor 后过滤
- **WHEN** 调用 `User::cursor()->filter(fn($u) => $u->active)`
- **THEN** 仅迭代 active 为 true 的用户
