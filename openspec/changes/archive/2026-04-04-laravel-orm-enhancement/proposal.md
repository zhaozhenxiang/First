## Why

当前 ORM 实现已具备 Active Record、Query Builder、关系映射、迁移、Seeder 等核心功能，但存在多个架构缺陷和与 Laravel Eloquent 的关键差距：静态属性跨模型泄漏、Collection 不可链式调用、Debug 系统未集成、多态关系 pivot 使用 stdClass 而非 Pivot 模型、无数据库语法抽象层、Migration 解析与生成不一致等。这些问题会影响生产环境可靠性和后续扩展。

## What Changes

- **修复静态属性隔离**：将 `$globalScopes`、`$bootedModels`、`$morphMap` 改为 per-class 存储（late static binding），防止跨模型泄漏
- **Collection 链式调用**：所有变换方法（`map`、`filter`、`where`、`sortBy` 等）返回 `Collection` 实例而非原生数组
- **集成 Debug 系统**：QueryBuilder 的所有执行方法接入 `DatabaseDebugger`，记录 SQL、绑定参数、执行时间
- **修复 Migration 解析**：统一 `Migrator::resolve()` 与匿名类生成器的不一致，支持匿名类 migration 文件
- **Pivot 模型化**：`BelongsToMany` 关系使用 `Pivot` 模型实例替代 `stdClass`
- **修复 DatabaseServiceProvider**：对齐 `Connection` 类的 API（`getInstance()` → `connection()`）
- **Query Builder 增强**：新增 `whereFullText`、`groupByRaw`、`havingRaw` 参数绑定、`dump()`/`dd()` 调试方法
- **清理废弃代码**：移除空的 `Bin\Model\Builder` 桩类

## Capabilities

### New Capabilities
- `static-isolation`: 修复 Model 静态属性 per-class 隔离（globalScopes、bootedModels、morphMap）
- `collection-chaining`: Collection 方法返回 Collection 实例，支持链式调用
- `debug-integration`: QueryBuilder 接入 DatabaseDebugger，查询日志自动记录
- `migration-anonymous-class`: Migrator 支持匿名类 migration 文件解析

### Modified Capabilities
- `belongs-to-many-pivot`: BelongsToMany 关系返回 Pivot 模型实例而非 stdClass
- `query-builder`: 新增 whereFullText、调试方法等，修复 whereDate 系列方法
- `model-cleanup`: 修复 sole() 签名、withoutEvents 反射问题、移除废弃 Builder 桩

## Impact

- **核心文件**：`bin/Database/Model.php`、`bin/Database/QueryBuilder.php`、`bin/Database/Collection.php`
- **关系文件**：`bin/Database/Relations/BelongsToMany.php`、`bin/Database/Pivot.php`
- **迁移系统**：`bin/Database/Migrations/Migrator.php`
- **服务提供者**：`bin/Providers/DatabaseServiceProvider.php`
- **依赖**：无新增外部依赖
- **破坏性变更**：`Collection` 返回类型从 `array` 变为 `Collection`（需检查下游代码）
