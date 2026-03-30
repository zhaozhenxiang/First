## ADDED Requirements

### Requirement: MorphTo 关联定义
系统 SHALL 允许模型通过 `morphTo()` 方法定义逆向多态关联。方法 SHALL 接受关联名称、类型列名、ID 列名参数。

#### Scenario: 基本多态逆向关联
- **WHEN** Comment 模型定义 `commentable()` 返回 `$this->morphTo()`
- **AND** comment 记录的 `commentable_type` 为 `'Post'`，`commentable_id` 为 `1`
- **THEN** `$comment->commentable` SHALL 返回 Post 模型实例（id=1）

#### Scenario: 自定义列名
- **WHEN** 模型定义 `imageable()` 返回 `$this->morphTo('imageable', 'image_type', 'image_id')`
- **THEN** 系统 SHALL 使用 `image_type` 作为类型列，`image_id` 作为外键列

### Requirement: MorphTo 查询约束
MorphTo 关系 SHALL 正确设置查询约束，根据类型列值动态解析目标模型类并添加 WHERE 条件。

#### Scenario: 获取关联查询
- **WHEN** 调用 MorphTo 关系的 `getResults()` 方法
- **THEN** SHALL 根据 `morph_type` 列值解析模型类名并执行 `WHERE id = morph_id`

### Requirement: MorphTo Eager Loading
MorphTo 关系 SHALL 支持预加载，按 `morph_type` 分组批量查询，避免 N+1 问题。

#### Scenario: 批量预加载混合类型
- **WHEN** 预加载一组 Comment 的 `commentable` 关联
- **AND** 该组包含 Post 类型（id=1,2）和 Video 类型（id=3）
- **THEN** 系统 SHALL 按 Post 和 Video 分组执行 2 次查询
- **AND** 正确将结果匹配到各 Comment 实例

#### Scenario: 空模型数组预加载
- **WHEN** 传入空数组进行 eager loading
- **THEN** 系统 SHALL 返回空 Collection，不抛出异常

### Requirement: MorphTo 类型解析
系统 SHALL 支持自定义 morph 类型映射，通过 `$morphMap` 将类型字符串映射到实际模型类名。

#### Scenario: 使用 morph map
- **WHEN** 注册 `$morphMap = ['post' => Post::class]`
- **AND** `commentable_type` 列值为 `'post'`
- **THEN** 系统 SHALL 将 `'post'` 解析为 `Post` 类
