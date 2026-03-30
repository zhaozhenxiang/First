## ADDED Requirements

### Requirement: whereHas 关系存在性查询
QueryBuilder SHALL 支持 `whereHas(string $relation, \Closure $callback = null)` 方法，通过 EXISTS 子查询筛选具有指定关系的模型。

#### Scenario: 基本关系存在性筛选
- **WHEN** 调用 `User::whereHas('posts')`
- **THEN** 生成的 SQL 包含 `EXISTS (SELECT * FROM posts WHERE posts.user_id = users.id)`

#### Scenario: 带约束的关系筛选
- **WHEN** 调用 `User::whereHas('posts', fn($q) => $q->where('published', 1))`
- **THEN** SQL 中 EXISTS 子查询包含 `WHERE posts.user_id = users.id AND published = 1`

### Requirement: whereDoesntHave
QueryBuilder SHALL 支持 `whereDoesntHave(string $relation, \Closure $callback = null)` 方法，通过 NOT EXISTS 子查询筛选不具有指定关系的模型。

#### Scenario: 筛选没有文章的用户
- **WHEN** 调用 `User::whereDoesntHave('posts')`
- **THEN** SQL 包含 `NOT EXISTS (SELECT * FROM posts WHERE posts.user_id = users.id)`

### Requirement: orWhereHas / orWhereDoesntHave
QueryBuilder SHALL 支持 `orWhereHas` 和 `orWhereDoesntHave`，使用 OR 布尔连接。

#### Scenario: OR 条件关系筛选
- **WHEN** 调用 `User::where('active', 1)->orWhereHas('posts')`
- **THEN** SQL 包含 `WHERE active = 1 OR EXISTS (...)`

### Requirement: withCount 关系统计
QueryBuilder SHALL 支持 `withCount(string|array $relations)` 方法，通过子查询统计关联记录数，并将结果附加到模型的虚拟属性上。

#### Scenario: 统计单个关系
- **WHEN** 调用 `User::withCount('posts')->find(1)`
- **THEN** 模型实例包含 `posts_count` 属性，值为该用户的文章数量

#### Scenario: 统计多个关系
- **WHEN** 调用 `User::withCount(['posts', 'comments'])`
- **THEN** 模型实例同时包含 `posts_count` 和 `comments_count`

#### Scenario: 带约束的统计
- **WHEN** 调用 `User::withCount(['posts' => fn($q) => $q->where('published', 1)])`
- **THEN** `posts_count` 只统计已发布的文章

### Requirement: withSum / withAvg / withMin / withMax
QueryBuilder SHALL 支持 `withSum`、`withAvg`、`withMin`、`withMax` 方法，通过子查询计算关联字段的聚合值。

#### Scenario: 统计关联字段总和
- **WHEN** 调用 `User::withSum('posts', 'views')->find(1)`
- **THEN** 模型实例包含 `posts_sum_views` 属性，值为该用户所有文章浏览量之和

#### Scenario: 统计关联字段平均值
- **WHEN** 调用 `User::withAvg('posts', 'views')`
- **THEN** 模型实例包含 `posts_avg_views` 属性

### Requirement: has 和 orHas
QueryBuilder SHALL 支持 `has(string $relation, string $operator = '>=', int $count = 1)` 方法，筛选关联数量满足条件的记录。

#### Scenario: 筛选有至少 3 篇文章的用户
- **WHEN** 调用 `User::has('posts', '>=', 3)`
- **THEN** SQL 中 EXISTS 子查询包含 `HAVING COUNT(*) >= 3`
