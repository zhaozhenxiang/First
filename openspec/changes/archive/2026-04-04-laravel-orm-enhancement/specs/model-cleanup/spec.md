## ADDED Requirements

### Requirement: Remove empty Builder stub
框架 SHALL 移除空的 `Bin\Model\Builder` 桩类，该类所有方法均为空实现。

#### Scenario: Builder 桩类不存在
- **WHEN** 检查 `bin/Model/Builder.php`
- **THEN** 文件不存在或类已移除
- **AND** 无其他代码引用 `Bin\Model\Builder`

### Requirement: DatabaseServiceProvider uses correct Connection API
`DatabaseServiceProvider` SHALL 使用 `Bin\Model\Connection` 类的正确 API（`connection()` 方法）获取 PDO 实例。

#### Scenario: 服务提供者正常注册
- **WHEN** `DatabaseServiceProvider::register()` 被调用
- **THEN** `app('db')` 返回有效的 PDO 连接实例
- **AND** 无方法不存在错误

## MODIFIED Requirements

### Requirement: sole method validates single result
Model 的 `sole()` 方法 SHALL 严格返回单个结果，找到 0 个时抛出异常，找到多个时也抛出异常。

#### Scenario: 找到唯一结果
- **WHEN** 查询匹配恰好 1 条记录
- **THEN** 返回该模型实例

#### Scenario: 找到 0 个结果
- **WHEN** 查询匹配 0 条记录
- **THEN** 抛出异常（ModelNotFoundException 或类似）

#### Scenario: 找到多个结果
- **WHEN** 查询匹配超过 1 条记录
- **THEN** 抛出异常（MultipleRecordsFoundException 或类似）
