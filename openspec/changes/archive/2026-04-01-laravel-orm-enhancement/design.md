## Context

框架当前 ORM 层已实现 Model 基类、QueryBuilder、Collection、四种基础关系（HasOne/HasMany/BelongsTo/BelongsToMany）、软删除、模型事件、分页、全局作用域等。这些功能覆盖了基本的 CRUD 和关系查询场景。

但在实际开发中，以下 Laravel Eloquent 的核心特性仍然缺失：
- 无法在模型中定义属性访问/修改的钩子逻辑（访问器/修改器）
- 无法定义可复用的本地作用域
- 缺少 firstOrCreate 等便捷方法
- 无法基于关系存在性筛选或统计关联数据
- 缺少远层关系和多态关系
- 事件处理仅支持闭包回调，缺少 Observer 类模式

## Goals / Non-Goals

**Goals:**
- 补齐 Laravel Eloquent 中最常用的高频特性
- 保持 API 风格与现有代码一致（fluent interface、静态代理）
- 所有新功能通过测试覆盖
- 不引入新的外部依赖

**Non-Goals:**
- 不实现 Eloquent 的 Factory 系统（已有独立的 Seeder 体系）
- 不实现队列异步处理
- 不实现 Elasticsearch/Scout 全文搜索集成
- 不重构现有 QueryBuilder 的 SQL 编译架构

## Decisions

### 1. 访问器/修改器采用方法命名约定

采用 Laravel 的 `getFooAttribute()` / `setFooAttribute()` 命名约定，同时支持新的 `Attribute` 类返回方式。

**理由**：与 Laravel 生态保持一致，降低学习成本。`Attribute` 类方式是 Laravel 9+ 推荐的新写法，更简洁。

**实现方式**：在 `Model::getAttribute()` 中检查 `get{Key}Attribute` 方法存在性；在 `Model::setAttribute()` 中检查 `set{Key}Attribute` 方法。新增 `$appends` 属性和 `append()` 方法。

### 2. 本地作用域通过 `scope` 前缀方法实现

模型中定义 `scopeFoo(QueryBuilder $query, ...$args)` 方法，通过 `__callStatic` 代理调用。

**理由**：Laravel 标准模式，开发者可直接在模型类中组织查询逻辑。

**实现方式**：在 `Model::__callStatic` 中检测 `scope` 前缀方法，自动注入 QueryBuilder 作为第一个参数。

### 3. 关系查询通过子查询实现

`whereHas`、`withCount` 等通过生成 EXISTS / LEFT JOIN 子查询实现。

**理由**：避免 N+1 查询，同时保持 SQL 层面的过滤能力。

**实现方式**：QueryBuilder 新增 `whereHas` 方法，接受关系名和闭包。闭包内获取关系的子查询 SQL，嵌套为 EXISTS 子句。`withCount` 等统计方法通过 addSelect + 子查询实现。

### 4. 远层关系通过中间表 JOIN 实现

`HasOneThrough` / `HasManyThrough` 通过在 QueryBuilder 中添加 JOIN 穿透中间表实现。

**理由**：与 Laravel 实现方式一致，SQL 层面高效。

### 5. 多态关系通过 `*_type` + `*_id` 列实现

`MorphOne`/`MorphMany` 存储 `commentable_type` + `commentable_id`，`MorphToMany` 使用中间表存储多态关系。

**理由**：Laravel 标准多态关系实现，灵活支持一个表被多种模型关联。

### 6. Observer 通过类方法映射到事件

Observer 类的方法名对应模型事件名（creating、created 等），通过 `Model::observe(ObserverClass::class)` 注册。

**理由**：比闭包回调更易组织和维护，一个 Observer 类集中管理一个模型的所有事件。

### 7. 游标查询使用 PHP Generator

`cursor()` 返回 `\Generator`，每次 yield 一条 hydrate 后的 Model 实例。

**理由**：避免 `fetchAll` 一次性加载所有数据到内存，适合大数据集处理。

### 8. upsert 使用 MySQL ON DUPLICATE KEY UPDATE

直接利用 MySQL 的 `INSERT ... ON DUPLICATE KEY UPDATE` 语法。

**理由**：原子操作，无需额外事务，性能最优。

## Risks / Trade-offs

- **[多态关系复杂度]** → 多态关系引入 `*_type` 列存储类名，需注意类名变更时的数据迁移。文档中注明风险。
- **[whereHas 性能]** → EXISTS 子查询在大数据集上可能较慢。提供 `whereHas` 的同时也保留 `with` + 内存过滤的方案。
- **[全局作用域与本地作用域冲突]** → `__callStatic` 需区分本地作用域调用和 QueryBuilder 方法代理。优先检查 scope 方法。
- **[访问器递归]** → `getAttribute` 中调用 `getFooAttribute` 时需避免通过 `__get` 再次触发访问器。访问器方法应直接读取 `$this->attributes`。
