## ADDED Requirements

### Requirement: QueryBuilder logs all queries to DatabaseDebugger
QueryBuilder 的所有查询执行方法（`get()`、`getArray()`、`insert()`、`insertGetId()`、`update()`、`delete()`）SHALL 自动将查询信息记录到 `DatabaseDebugger`。

#### Scenario: select 查询被记录
- **WHEN** `DatabaseDebugger::enable()` 后执行 `$query->get()`
- **THEN** `DatabaseDebugger::getCount()` 增加 1
- **AND** `DatabaseDebugger::getQueries()[0]->sql` 包含执行的 SQL
- **AND** `DatabaseDebugger::getQueries()[0]->time` 大于 0

#### Scenario: insert 查询被记录
- **WHEN** `DatabaseDebugger::enable()` 后执行 `$query->insert(['name' => 'test'])`
- **THEN** `DatabaseDebugger::getCount()` 增加 1
- **AND** 最新 query log 的 `isInsert()` 返回 `true`

#### Scenario: update 查询被记录
- **WHEN** `DatabaseDebugger::enable()` 后执行 `$query->update(['name' => 'updated'])`
- **THEN** 最新 query log 的 `isUpdate()` 返回 `true`

#### Scenario: delete 查询被记录
- **WHEN** `DatabaseDebugger::enable()` 后执行 `$query->delete()`
- **THEN** 最新 query log 的 `isDelete()` 返回 `true`

### Requirement: Debug disabled by default
DatabaseDebugger SHALL 默认禁用，不影响性能。需手动调用 `DatabaseDebugger::enable()` 开启。

#### Scenario: 默认不记录
- **WHEN** 未调用 `enable()` 直接执行查询
- **THEN** `DatabaseDebugger::getCount()` 返回 0

#### Scenario: enable 后开始记录
- **WHEN** 调用 `DatabaseDebugger::enable()` 后执行查询
- **THEN** `DatabaseDebugger::getCount()` 大于 0

#### Scenario: disable 后停止记录
- **WHEN** 先 `enable()` 再 `disable()` 后执行查询
- **THEN** 查询不被记录

### Requirement: Query log captures bindings and time
每条查询日志 SHALL 包含 SQL 语句、绑定参数、执行时间（毫秒）。

#### Scenario: 完整日志信息
- **WHEN** 执行 `$query->where('id', 1)->where('name', 'test')->get()` 并开启 debug
- **THEN** 日志包含完整 SQL（含 WHERE 子句）
- **AND** 日志 bindings 包含 `[1, 'test']`
- **AND** 日志 time 为正浮点数（毫秒）
