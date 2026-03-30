## 1. Bug 修复

- [x] 1.1 修复 QueryBuilder whereBetween 参数绑定：改用位置参数 `?`，正确追加到 where 绑定数组
- [x] 1.2 修复 QueryBuilder update 语句：将命名参数 `:key` 改为位置参数 `?`，统一 SET 和 WHERE 绑定
- [x] 1.3 修复 Relation 基类 eager loading 对空模型数组的保护：空数组直接返回空 Collection
- [x] 1.4 修复 BelongsToMany eager loading pivot 数据水合：正确在结果模型上设置 pivot 属性对象

## 2. MorphTo 关联关系

- [x] 2.1 新建 `bin/Database/Relations/MorphTo.php`：继承 Relation，实现 addConstraints、getResults、addEagerConstraints
- [x] 2.2 实现 MorphTo eager loading：按 morph_type 分组批量查询，支持混合类型
- [x] 2.3 在 Model 基类添加 `morphTo()` 方法和 `$morphMap` 静态属性及 `enforceMorphMap()` 支持
- [x] 2.4 编写 MorphTo 关联测试（基本查询、eager loading、自定义列名、morph map）

## 3. 关联类重构

- [x] 3.1 重构 MorphOneOrMany 抽象基类：整合 MorphMany/MorphOne 共享的多态约束逻辑
- [x] 3.2 重构 MorphMany 继承 MorphOneOrMany，消除独立实现中的重复代码
- [x] 3.3 重构 MorphOne 继承 MorphMany（仅重写 getResults 返回单条）
- [x] 3.4 修复 MorphMany/MorphOne eager loading 支持混合父模型类型
- [x] 3.5 在 HasMany 添加 `saveMany()` 方法
- [x] 3.6 在 Model 添加 `destroy()` 静态方法（支持多 ID、数组、Collection 参数）
- [x] 3.7 编写关联重构测试（验证 MorphMany/MorphOne 行为不变、saveMany、destroy）

## 4. Pivot 模型

- [x] 4.1 新建 `bin/Database/Pivot.php`：继承 Model，禁用自增和时间戳，添加 pivotParent/pivotColumns 属性
- [x] 4.2 修改 BelongsToMany：使用 Pivot 模型水合结果，支持 `using()` 指定自定义 Pivot 类
- [x] 4.3 完善 `withPivot()` 和 `withTimestamps()` 方法实现
- [x] 4.4 编写 Pivot 模型测试（属性访问、自定义 Pivot 类、withPivot、withTimestamps）

## 5. Observer 模式

- [x] 5.1 新建 `bin/Database/Observer.php`：定义 Observer 接口/基类
- [x] 5.2 在 Model 添加 `observe()` 静态方法：通过反射发现事件方法名并注册为监听器
- [x] 5.3 实现 `withoutEvents()` 方法：临时禁用所有事件的回调执行
- [x] 5.4 编写 Observer 测试（基本事件、多 Observer、与闭包共存、withoutEvents）

## 6. 模型工厂

- [x] 6.1 新建 `bin/Database/Factory.php`：实现 define()、create()、make() 方法
- [x] 6.2 实现属性覆盖：create/make 支持传入覆盖属性数组
- [x] 6.3 实现 state() 状态定义和 times() 批量创建
- [x] 6.4 编写工厂测试（定义/创建/构建/覆盖属性/状态/批量）

## 7. 延迟聚合加载

- [x] 7.1 在 Model 实现 `loadCount()` 方法
- [x] 7.2 在 Model 实现 `loadSum()` 方法
- [x] 7.3 编写 loadCount/loadSum 测试

## 8. 集成验证

- [x] 8.1 运行全部现有测试确保无回归
- [x] 8.2 验证新功能之间的交互（如 Observer + Pivot、Factory + MorphTo）
