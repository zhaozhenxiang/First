## MODIFIED Requirements

### Requirement: 空数组预加载保护
eager loading 方法 SHALL 在传入空模型数组时安全返回空 Collection，不触发关联查询。

#### Scenario: 空数组输入
- **WHEN** 对空模型数组执行 eager loading
- **THEN** SHALL 返回空 Collection 而不抛出异常或执行查询

### Requirement: BelongsToMany pivot 数据填充
BelongsToMany 的 eager loading SHALL 正确水合 pivot 属性对象到查询结果中。

#### Scenario: pivot 属性正确填充
- **WHEN** 通过 BelongsToMany 关系预加载
- **THEN** 每个结果模型 SHALL 包含 `pivot` 属性
- **AND** `pivot` 属性 SHALL 包含外键列和 `withPivot` 指定的额外列

### Requirement: 多态混合类型预加载
MorphOne/MorphMany 的 eager loading SHALL 支持混合父模型类型，按 `morph_type` 分组查询。

#### Scenario: 混合类型集合
- **WHEN** 预加载一组包含不同 `commentable_type` 的 Comment 的 morph 关系
- **THEN** 系统 SHALL 按类型分组并分别查询
- **AND** 正确将结果匹配到对应父模型

## ADDED Requirements

### Requirement: loadCount 延迟聚合加载
Model SHALL 支持 `loadCount()` 方法在已加载模型上延迟加载关联计数。

#### Scenario: 延迟加载计数
- **WHEN** 对已查询的模型调用 `$model->loadCount('comments')`
- **THEN** 模型 SHALL 获得 `comments_count` 属性

### Requirement: loadSum 延迟聚合加载
Model SHALL 支持 `loadSum()` 方法在已加载模型上延迟加载关联聚合值。

#### Scenario: 延迟加载求和
- **WHEN** 对已查询的模型调用 `$model->loadSum('orders', 'total')`
- **THEN** 模型 SHALL 获得 `orders_sum_total` 属性
