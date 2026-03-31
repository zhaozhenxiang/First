# Implementation Tasks

## 1. 访问器和修改器（accessors-mutators）

- [x] 1.1 创建 `bin/Database/Attribute.php` - Attribute 值对象类（支持 get/set 闭包）
- [x] 1.2 修改 `bin/Database/Model.php` - 在 `getAttribute()` 中检查 `get{Key}Attribute()` 方法和 `Attribute` 返回方法
- [x] 1.3 修改 `bin/Database/Model.php` - 在 `setAttribute()` 中检查 `set{Key}Attribute()` 方法和 `Attribute` 返回方法
- [x] 1.4 修改 `bin/Database/Model.php` - 新增 `$appends` 属性、`append()` 方法和 `toArray()` 中追加逻辑
- [x] 1.5 编写测试 - 访问器、修改器、Attribute 类、appends 功能

## 2. 本地作用域（local-scopes）

- [x] 2.1 修改 `bin/Database/Model.php` - 在 `__callStatic` 中优先检测 `scope{Method}` 方法，自动注入 QueryBuilder
- [x] 2.2 编写测试 - 基本作用域、带参数作用域、链式调用

## 3. 便捷查找方法（convenience-finders）

- [x] 3.1 修改 `bin/Database/Model.php` - 新增 `firstOrCreate()`、`firstOrNew()`、`updateOrCreate()` 方法
- [x] 3.2 修改 `bin/Database/Model.php` - 新增 `firstWhere()` 方法
- [x] 3.3 修改 `bin/Database/Model.php` - 新增 `sole()` 方法
- [x] 3.4 编写测试 - 各方法正常/异常场景

## 4. 关系查询（relationship-queries）

- [x] 4.1 修改 `bin/Database/QueryBuilder.php` - 新增 `whereHas()`、`whereDoesntHave()`、`orWhereHas()`、`orWhereDoesntHave()` 方法
- [x] 4.2 修改 `bin/Database/QueryBuilder.php` - 新增 `withCount()`、`withSum()`、`withAvg()`、`withMin()`、`withMax()` 方法
- [x] 4.3 修改 `bin/Database/QueryBuilder.php` - 在 `get()` 中处理 withCount 等聚合子查询
- [x] 4.4 修改 `bin/Database/Relations/Relation.php` - 修复 `noConstraints()` 静态方法、`addEagerConstraints()` 可见性
- [x] 4.5 编写测试 - 关系存在性查询、统计查询（8 tests passing）

## 5. 远层关系(through-relationships)

- [x] 5.1 创建 `bin/Database/Relations/HasOneThrough.php` - 通过中间表 JOIN 审现远层一对一
- [x] 5.2 创建 `bin/Database/Relations/HasManyThrough.php` - 通过中间表 JOIN 实现远层一对多
- [x] 5.3 修改 `bin/Database/Model.php` - 新增 `hasOneThrough()`、`hasManyThrough()` 方法定义
- [x] 5.4 编写测试 - 远层关系查询和 eager loading（5 tests passing）

## 6. 多态关系（polymorphic-relationships）

- [ ] 6.1 创建 `bin/Database/Relations/MorphOne.php` - 多态一对一关系
- [ ] 6.2 创建 `bin/Database/Relations/MorphMany.php` - 多态一对多关系
- [ ] 6.3 创建 `bin/Database/Relations/MorphToMany.php` - 多态多对多关系
- [ ] 6.4 创建 `bin/Database/Relations/MorphByMany.php` - 反向多态多对多
- [ ] 6.5 修改 `bin/Database/Model.php` - 新增 `morphOne()`、`morphMany()`、`morphToMany()`、`morphedByMany()` 方法
- [ ] 6.6 编写测试 - 各多态关系的 CRUD 和 eager loading

## 7. 模型观察者（model-observers）

- [ ] 7.1 创建 `bin/Database/ModelObserver.php` - Observer 注册和管理类
- [ ] 7.2 修改 `bin/Database/ModelEventDispatcher.php` - 支持 Observer 类方法映射
- [ ] 7.3 修改 `bin/Database/Model.php` - 新增 `observe()`、`clearObservers()` 静态方法
- [ ] 7.4 编写测试 - Observer 注册、事件触发、阻止操作、清除

## 8. 游标查询和 Upsert（cursor-and-upsert）

- [ ] 8.1 修改 `bin/Database/QueryBuilder.php` - 新增 `cursor()` 方法，返回 Generator
- [ ] 8.2 修改 `bin/Database/QueryBuilder.php` - 新增 `upsert()` 方法，使用 ON DUPLICATE KEY UPDATE
- [ ] 8.3 编写测试 - cursor 遍历、内存验证、upsert 插入/更新/冲突
