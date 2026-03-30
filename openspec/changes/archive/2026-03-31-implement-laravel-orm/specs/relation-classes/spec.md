## MODIFIED Requirements

### Requirement: MorphMany 继承体系
MorphMany SHALL 继承 MorphOneOrMany 抽象基类，共享多态关联的通用逻辑。

#### Scenario: MorphMany 查询约束
- **WHEN** 定义 `morphMany` 关系
- **THEN** SHALL 在 MorphOneOrMany 中设置 `WHERE morph_type = parent_class`
- **AND** MorphMany::getResults() SHALL 返回 Collection（多个结果）

### Requirement: MorphOne 继承体系
MorphOne SHALL 继承 MorphMany 并重写 `getResults()` 返回单个模型或 null。

#### Scenario: MorphOne 返回单个结果
- **WHEN** 定义 `morphOne` 关系并查询
- **THEN** `getResults()` SHALL 返回单个模型实例或 null（而非 Collection）

## ADDED Requirements

### Requirement: HasMany saveMany
HasMany 关系 SHALL 支持 `saveMany()` 方法批量保存多个关联模型。

#### Scenario: 批量保存
- **WHEN** 调用 `$post->comments()->saveMany([$comment1, $comment2])`
- **THEN** 两个 Comment SHALL 被保存且正确设置 `post_id` 外键

### Requirement: Model destroy 静态方法
Model SHALL 支持 `destroy()` 静态方法，按 ID 删除模型记录。

#### Scenario: 按 ID 删除
- **WHEN** 调用 `User::destroy(1, 2, 3)`
- **THEN** SHALL 删除 id 为 1、2、3 的 User 记录
- **AND** 为每条记录触发 `deleting` 和 `deleted` 事件

#### Scenario: 按数组删除
- **WHEN** 调用 `User::destroy([1, 2])`
- **THEN** SHALL 删除数组中所有 ID 对应的记录
