## Why

当前框架已实现大部分 Eloquent ORM 核心功能（QueryBuilder、Model 基类、8 种关联关系），但缺少关键的 Laravel Eloquent 特性：MorphTo 逆向多态关联、自定义 Pivot 模型、Observer 模式、模型工厂等。这些是构建复杂应用的基础设施，完善后可达到 Laravel Eloquent 90%+ 功能覆盖率。

## What Changes

- 新增 `MorphTo` 关联关系，补全多态关联的逆向端（如 Comment→commentable）
- 新增 `Pivot` 模型类，支持自定义中间表模型（含 `$pivotColumns` 水合、pivot 属性访问）
- 新增 `Observer` 模式，支持类级别的模型事件监听（区别于现有闭包事件）
- 修复 `BelongsToMany` eager loading 中 pivot 数据未正确填充的 bug
- 修复 eager loading 对空模型数组缺少保护的问题
- 修复 `MorphMany` eager loading 仅支持单一父类类型的问题（应支持混合类型）
- 新增 `HasMany::saveMany()` 批量保存方法
- 新增 `Model::destroy()` 静态删除方法
- 重构 `MorphMany`/`MorphOne` 继承自 `MorphOneOrMany` 抽象基类（消除死代码）
- 新增 `loadCount()`/`loadSum()` 等延迟聚合加载方法
- 改进 QueryBuilder 参数绑定机制，修复 whereBetween 和 update 语句的绑定冲突

## Capabilities

### New Capabilities
- `morph-to-relation`: MorphTo 逆向多态关联关系，支持多态关联的逆向查询和 eager loading
- `pivot-model`: 自定义 Pivot 模型，支持中间表属性访问、pivot 列水合和自定义中间表模型
- `model-observer`: Observer 模式，支持通过类定义模型生命周期事件监听器
- `model-factory`: 模型工厂系统，支持测试数据生成和状态定义

### Modified Capabilities
- `eager-loading`: 修复 eager loading 对空数组的保护、pivot 数据填充、混合多态类型支持
- `query-builder`: 修复参数绑定机制，改进 whereBetween 和 update 语句的绑定处理
- `relation-classes`: 重构 MorphMany/MorphOne 继承体系，新增 saveMany 等便捷方法

## Impact

- `bin/Database/Relations/` — 新增 MorphTo.php，重构 MorphMany/MorphOne 继承关系
- `bin/Database/Pivot.php` — 新增文件
- `bin/Database/Observer.php` — 新增文件
- `bin/Database/Factory.php` — 新增文件
- `bin/Database/Model.php` — 新增 morphTo()、destroy()、observe()、loadCount() 等方法
- `bin/Database/QueryBuilder.php` — 修复绑定机制
- `bin/Database/Relations/BelongsToMany.php` — 修复 pivot 数据填充
- `bin/Database/Relations/Relation.php` — 修复空数组 eager loading
- `tests/` — 新增对应测试文件
