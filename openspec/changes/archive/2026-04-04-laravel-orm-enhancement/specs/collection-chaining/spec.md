## ADDED Requirements

### Requirement: Collection transform methods return Collection instances
Collection 的所有变换方法（`map`、`filter`、`reject`、`sortBy`、`sort`、`reverse`、`shuffle`、`unique`、`collapse`、`flatten`、`slice`、`take`、`skip`、`chunk`、`merge`、`diff`、`intersect`、`values`、`keys`、`nth`、`flip`、`union`、`pop`）SHALL 返回 `Collection` 实例而非原生数组。

#### Scenario: map 返回 Collection
- **WHEN** 对 Collection 实例调用 `map(fn($item) => $item * 2)`
- **THEN** 返回值为 `Collection` 类型实例

#### Scenario: filter 返回 Collection
- **WHEN** 对 Collection 实例调用 `filter(fn($item) => $item > 2)`
- **THEN** 返回值为 `Collection` 类型实例

#### Scenario: sortBy 返回 Collection
- **WHEN** 对 Collection 实例调用 `sortBy('name')`
- **THEN** 返回值为 `Collection` 类型实例

### Requirement: Collection supports method chaining
Collection SHALL 支持链式调用，多个变换方法可连续调用。

#### Scenario: 链式调用 where + map + values
- **WHEN** 执行 `$collection->where('age', '>', 18)->map(fn($u) => $u['name'])->values()`
- **THEN** 每一步都返回 Collection 实例
- **AND** 最终结果为过滤后的名称 Collection

#### Scenario: 链式调用 filter + sort + take
- **WHEN** 执行 `$collection->filter(fn($i) => $i > 0)->sort()->take(5)`
- **THEN** 返回排序后前 5 个元素的 Collection

### Requirement: Collection search methods return arrays unchanged
`pluck`、`groupBy`、`keyBy` 等搜索/聚合方法 SHALL 保持返回原生数组（与 Laravel 一致）。

#### Scenario: pluck 返回数组
- **WHEN** 对 Collection 调用 `pluck('name')`
- **THEN** 返回值为原生 PHP `array` 类型

### Requirement: Collection aggregate methods return scalar unchanged
`count`、`sum`、`avg`、`max`、`min` 等聚合方法 SHALL 保持返回标量值。

#### Scenario: sum 返回数字
- **WHEN** 对 Collection 调用 `sum('price')`
- **THEN** 返回值为 `int|float` 标量
