## Context

当前框架 ORM 基于 Laravel Eloquent 设计，已实现 Active Record、Query Builder、关系映射（11 种关系类型）、Migration、Seeder、Schema Builder、Debug 工具等。但在与 Laravel Eloquent 的对比中发现了若干架构缺陷：

1. **静态属性共享问题**：`Model` 上的 `$globalScopes`、`$bootedModels`、`$morphMap` 使用 `protected static`，所有子类共享同一份状态，而非 Laravel 的 per-class 隔离
2. **Collection 不可链式**：变换方法返回 `array` 而非 `Collection` 实例
3. **Debug 系统断裂**：`DatabaseDebugger` 存在但未被 QueryBuilder 调用
4. **Migration 解析不一致**：`MigrationCreator` 生成匿名类，但 `Migrator::resolve()` 按类名解析

## Goals / Non-Goals

**Goals:**
- 修复静态属性隔离，确保每个 Model 子类有独立的 scopes/boot 状态/morph map
- Collection 支持链式调用，与 Laravel 行为一致
- QueryBuilder 自动记录查询日志到 DatabaseDebugger
- Migrator 支持匿名类 migration 文件
- BelongsToMany 使用 Pivot 模型而非 stdClass
- 清理废弃代码，修复已知 bug

**Non-Goals:**
- 不引入新的数据库语法抽象层（Grammar class 拆分留给后续）
- 不实现多数据库连接/连接池
- 不重构迁移系统的文件命名或目录结构
- 不新增关系类型或 ORM 特性

## Decisions

### D1: 静态属性 per-class 隔离

**选择**：使用 `static::$_property[$staticClass]` 模式，在 Model 基类中将数据按实际调用类名存储。

**替代方案**：
- (A) 使用 ` late static binding` + 每个子类重新声明属性 → 需要每个子类重复声明，容易遗漏
- (B) 使用 `ReflectionClass` 获取实际类名 → 性能开销大

**理由**：方案与 Laravel Eloquent 一致（`static::$globalScopes[static::class]`），零额外开销，向下兼容。

### D2: Collection 返回类型

**选择**：所有变换方法返回新的 `Collection` 实例。

**理由**：与 Laravel 一致，支持 `$collection->where(...)->map(...)->sortBy(...)` 链式调用。`map`、`filter` 等已返回数组，只需 `new static($result)` 包装。

### D3: Debug 集成点

**选择**：在 QueryBuilder 的 `get()`、`getArray()`、`insert()`、`insertGetId()`、`update()`、`delete()` 方法中，包装 try/finally 记录 QueryLog。

**替代方案**：
- (A) 在 PDO 层拦截 → 粒度太粗，无法记录 QueryBuilder 构建的 SQL
- (B) 通过装饰器模式包装 PDO → 复杂度高，影响面大

**理由**：在 QueryBuilder 执行点记录可以同时捕获 SQL 和绑定参数，改动最小。

### D4: Migration 匿名类支持

**选择**：修改 `Migrator::resolve()` 改为直接 include 文件并获取返回值，而非通过类名实例化。记录迁移文件名而非类名到 migrations 表。

**理由**：现有的 `MigrationCreator` 已经生成匿名类，且 `database/migrations/` 中的文件也使用匿名类。改为文件名追踪与 Laravel 8+ 行为一致。

### D5: Pivot 模型化

**选择**：在 `BelongsToMany::hydratePivot()` 中创建 `Pivot` 实例（或自定义 pivotClass），而非 stdClass。

**理由**：Pivot 类已存在且完整，只需在关系类中使用它。

## Risks / Trade-offs

- **[破坏性] Collection 返回类型变化** → 下游代码如果对 `where()` 返回值调用 `array_*` 函数会报错。缓解：全局搜索受影响代码，在测试中覆盖
- **[性能] Debug 集成增加开销** → 默认关闭，需手动 `DatabaseDebugger::enable()`。已在 DatabaseDebugger 中实现 max cap 机制
- **[Migration] 数据库迁移表结构变化** → migration 记录字段从类名改为文件名，需提供迁移脚本。缓解：对于全新部署无影响
- **[兼容性] 静态属性隔离** → 如果现有代码依赖跨模型共享 scopes，会失效。缓解：这是 bug 修复而非新行为
