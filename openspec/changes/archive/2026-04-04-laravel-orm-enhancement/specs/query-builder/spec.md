## MODIFIED Requirements

### Requirement: whereDate 系列方法正确编译 SQL
`whereDate`、`whereDay`、`whereMonth`、`whereYear`、`whereTime` 方法 SHALL 使用 MySQL `DATE()`/`DAY()`/`MONTH()`/`YEAR()`/`TIME()` 函数编译 WHERE 子句。

#### Scenario: whereDate 编译
- **WHEN** 调用 `$query->whereDate('created_at', '2026-04-03')`
- **THEN** 生成的 SQL 包含 `DATE(created_at) = ?` 且绑定为 `'2026-04-03'`

#### Scenario: whereMonth 编译
- **WHEN** 调用 `$query->whereMonth('created_at', 4)`
- **THEN** 生成的 SQL 包含 `MONTH(created_at) = ?` 且绑定为 `4`

## ADDED Requirements

### Requirement: dump and dd debugging methods
QueryBuilder SHALL 提供 `dump()` 和 `dd()` 方法用于调试。`dump()` 输出 SQL 和绑定参数后继续执行，`dd()` 输出后终止程序。

#### Scenario: dump 输出 SQL
- **WHEN** 调用 `$query->where('id', 1)->dump()`
- **THEN** 输出包含 toSql() 结果和 bindings
- **AND** 查询继续可执行

#### Scenario: dd 终止程序
- **WHEN** 调用 `$query->where('id', 1)->dd()`
- **THEN** 输出 SQL 和绑定参数
- **AND** 程序终止（exit）

### Requirement: groupByRaw method
QueryBuilder SHALL 提供 `groupByRaw(string $sql)` 方法，支持原生 SQL GROUP BY 表达式。

#### Scenario: groupByRaw 使用
- **WHEN** 调用 `$query->select('status, COUNT(*) as count')->groupByRaw('status')`
- **THEN** 生成的 SQL 包含 `GROUP BY status`
