## Why

当前框架的 ORM 已具备基本的 CRUD、关系定义、查询构建等核心功能，但与 Laravel Eloquent 相比，缺少多项实用特性：访问器/修改器、本地作用域、便捷的 firstOrCreate 系列方法、关系存在性查询（whereHas）、远层关系（HasOneThrough/HasManyThrough）、多态关系、模型观察者、以及游标迭代等。这些特性是日常开发中高频使用的功能，补全后可大幅提升开发效率。

## What Changes

- 新增 **访问器和修改器（Accessors & Mutators）**：支持 `getFooAttribute()` / `setFooAttribute()` 风格和 `Attribute` 类风格，支持 `appends` 将计算属性序列化到数组/JSON
- 新增 **本地作用域（Local Scopes）**：支持在模型中定义 `scopeFoo()` 方法，通过 `User::foo()` 链式调用
- 新增 **便捷查找方法**：`firstOrCreate`、`firstOrNew`、`updateOrCreate`、`firstWhere`、`sole`
- 新增 **关系查询**：`whereHas`、`whereDoesntHave`、`withCount`、`withMax`、`withMin`、`withAvg`、`withSum`
- 新增 **远层关系**：`HasOneThrough`、`HasManyThrough`
- 新增 **多态关系**：`MorphOne`、`MorphMany`、`MorphToMany`、`MorphByMany`
- 新增 **模型观察者（Observers）**：支持创建 Observer 类集中管理模型事件
- 新增 **游标查询（Cursor）**：`cursor()` 方法返回懒加载 Generator，降低大数据集内存占用
- 新增 **upsert** 方法：批量 insert or update

## Capabilities

### New Capabilities
- `accessors-mutators`: 访问器、修改器和计算属性序列化（appends）
- `local-scopes`: 本地作用域定义和调用
- `convenience-finders`: firstOrCreate/firstOrNew/updateOrCreate/firstWhere/sole
- `relationship-queries`: whereHas/whereDoesntHave/withCount 等关系存在性统计查询
- `through-relationships`: HasOneThrough/HasManyThrough 远层关系
- `polymorphic-relationships`: 多态关系（MorphOne/MorphMany/MorphToMany）
- `model-observers`: 模型观察者类
- `cursor-and-upsert`: 游标查询和 upsert 方法

### Modified Capabilities

## Impact

- **bin/Database/Model.php** - 新增访问器/修改器调度逻辑、本地作用域、便捷方法、关系查询入口
- **bin/Database/QueryBuilder.php** - 新增 whereHas/withCount 等子查询支持、cursor、upsert
- **bin/Database/Relations/** - 新增 4 个关系类（HasOneThrough、HasManyThrough、MorphOne/MorphMany、MorphToMany）
- **bin/Database/ModelObserver.php** - 新文件
- **bin/Database/ModelEventDispatcher.php** - 扩展支持 Observer 注册
- **tests/** - 每个新能力需要对应测试
