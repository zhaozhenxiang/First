# First 框架 ORM/数据库层 vs Laravel 13 差距分析

> 生成日期：2026-09-11（2026-09-12 更新：P0 批次已实施，见文末阶段8 记录；取代 2026-04-08 的全框架版，旧版可从 git 历史找回）
> 基线：Laravel 13.x（2026-03-17 发布，PHP ≥8.3）
> 范围：ORM/数据库层——模型、关系、查询构建器、Schema/迁移、Seeder/工厂、分页、集合。
> 旧版中的非 ORM 章节（路由/验证/Blade/队列等）因严重过时且超出本次范围已移除，待后续按同口径重审。
> 代码基线：`fix/orm-p0-p1` 分支，七阶段修复（940286c）+ 阶段8 P0 对齐批次（7852138/3466560/dc85b80），`bin/Database/` 共 57 文件约 1.5 万行。

---

## 成熟度总览

```
    完成度（相对 Laravel 13 对应模块）
    100% ┤
     90% ┤  ████ 分页        (paginate/simplePaginate/cursorPaginate 三种全有)
     85% ┤  ████ 模型层      (时间戳/casts/访问器/事件/软删/严格模式/复制/静默家族*)
          ████ 关系层       (11 种关系/嵌套 eager 恒 2 条 SQL/has 家族含计数比较/morphMap)
          ████ Schema/迁移  (列类型全家/索引/流式外键链/迁移运行器)
          ████ 查询构建器   (WHERE 全系/JOIN 含子查询/upsert 家族/事务/调试器)
     70% ┤       ████ 集合   (60+ 方法，未分 Base/Eloquent 两层)
     50% ┤            ████ Seeder/工厂 (静态注册表式，无工厂类 DSL)
      0% ┤                 ████ Laravel 13 专项 (PHP 属性/向量检索/JSON:API 全无)

    * 标注 * 的为 2026-09 阶段8 批次补齐项
```

---

## Laravel 13 基线速览

**版本信息**：2026-03-17 发布，最低 PHP 8.3（与本项目一致）；官方定位"最小化破坏性变更"，Eloquent 主体为延续性改进。

**Laravel 13 在 ORM 侧的新东西**：

| 特性 | 说明 |
|------|------|
| PHP 属性配置 | Eloquent 改用 PHP 8 属性替代属性声明：`#[Table(name/key/keyType/incrementing/timestamps/dateFormat)]`、`#[WithoutIncrementing]`、`#[WithoutTimestamps]`、`#[DateFormat]`、`#[Connection]`、`#[Fillable]`、`#[Guarded]`、`#[Unguarded]`、`#[Hidden]`、`#[Visible]`、`#[Appends]`、`#[Touches]`、`#[Scope]`（本地作用域，替代 `scopeXxx` 前缀约定）、`#[ScopedBy]`、`#[ObservedBy]` |
| 向量检索 | 查询构建器 `whereVectorSimilarTo()`（PostgreSQL + pgvector）、`Str::toEmbeddings()` 语义搜索工作流 |
| JSON:API 资源 | 一等公民的 JSON:API 规范资源序列化（关系包含/稀疏字段集/链接/合规响应头） |
| 其余 | `Cache::touch()`、队列按类路由、`PreventRequestForgery` 中间件等，均不属 ORM 范畴 |

**Laravel 13 延续自 12.x 及更早的 Eloquent 基线**（下文对比中反复出现）：`upsert`/`updateOrInsert`/`insertOrIgnore`、`cursor()`/`lazy()`/`lazyById()` 流式迭代、`HasUuids`（UUIDv7）/`HasUlids`、`Prunable`/`MassPrunable` + `model:prune`、`saveQuietly`/`deleteQuietly` 等静默家族、`$dispatchesEvents` 事件类映射、`is()`/`isNot()` 模型比较、`withAttributes` 待定属性、高阶 `orWhere` 作用域、子查询 select/orderBy、JSON 子句（`whereJsonContains` 等）。

---

## 一、模型层

**文件：** `bin/Database/Model.php`（895 行）+ `bin/Database/Model/`（HasAttributes / HasEvents / HasRelationships / HasSerialization / HasStrictMode / HasTimestamps 共 6 个 trait）

| 功能 | 状态 | 证据 / 说明 |
|------|------|------|
| 约定（表名蛇形复数、主键 id、keyType int/string） | ✅ | UUID 等字符串主键不被强转 `QueryBuilder.php:708-715` |
| timestamps（`CREATED_AT`/`UPDATED_AT` 常量、touch） | ✅ | `Model/HasTimestamps.php` |
| casts 基础类型（int/float/string/bool/array/json/object/date/datetime/timestamp） | ✅ | `Model/HasAttributes.php:385-404` |
| casts 参数化语法（`datetime:Y-m-d`、`decimal:2`）、`encrypted:*` | ❌ | |
| Carbon 日期对象 | ❌ | 零依赖取舍：时间戳为 `date()` 字符串，无 Carbon |
| 访问器/修改器（传统 `getFooAttribute` + 新式 `Attribute` 类，反射按类缓存） | ✅ | `HasAttributes.php:156-275`；注意 `Attribute.php` 是访问器类，与 PHP 8 属性无关 |
| 批量赋值保护（$fillable/$guarded/forceFill/unguard/unguarded/totallyGuarded） | ✅ | `HasAttributes.php:110-140,279-364` |
| 本地作用域（`scopeXxx` + `__call`/`__callStatic` 转发，支持动态参数） | ✅ | `Model.php:317-342` |
| 全局作用域 | ⚠️ | 仅"字符串标识 + 闭包"形态 `Model.php:275`；无 `Scope` 接口类作用域，无 `withoutGlobalScopesExcept`，无 Laravel 13 的 `#[ScopedBy]` |
| 模型事件 | ⚠️ | 11 种（`Model/HasEvents.php`）+ `$dispatchesEvents` 自定义事件类映射（阶段8）；仍缺 `trashed`/`forceDeleting`/`forceDeleted`/`replicating` 事件与 queueable 监听 |
| 观察者 `observe()` | ✅ | `Model/HasEvents.php:120-131`、`Observer.php` |
| 软删除全套（delete→UPDATE/forceDelete/restore/trashed/withTrashed/onlyTrashed/查询级 delete 转软删） | ✅ | `bin/Database/SoftDeletes.php` |
| hidden/visible/appends 序列化过滤 | ✅ | `Model/HasSerialization.php` |
| isDirty/isClean/getOriginal/getDirty/getChanges/wasRecentlyCreated | ✅ | `HasAttributes.php:417-488` |
| `wasChanged()` / `getPrevious()` / `getChanges()` | ✅ | 阶段8；save() 时落快照，`setRawAttributes` 清空 changes |
| find/findMany/findOrFail/findOrNew/firstWhere/all/destroy/hydrate | ✅ | `Model.php:355-477` |
| findOr / firstOr / sole | ✅ | `QueryBuilder.php:439,430`、`Model.php:450` |
| firstOrCreate / firstOrNew / updateOrCreate | ✅ | `Model.php:393-421` |
| replicate | ✅ | 阶段8：支持 `$except` 排除参数（主键始终排除） |
| fresh / refresh / refreshOrFail | ✅ | `Model.php:812-878` |
| saveQuietly / deleteQuietly / restoreQuietly / forceDeleteQuietly 静默家族 | ✅ | 阶段8 |
| saveOrFail / updateOrFail / deleteOrFail | ❌ | |
| forceCreate | ✅ | 阶段8：unguard 包装 create |
| is() / isNot() 模型比较 | ✅ | 阶段8：表名 + 主键比较 |
| HasUuids（UUIDv7）/ HasUlids 主键 trait | ❌ | `Model/` 下仅 6 个 trait，无对应实现 |
| Prunable / MassPrunable + `model:prune` 命令 | ❌ | |
| 严格模式（preventLazyLoading / preventSilentlyDiscardingAttributes 等） | ✅ | `Model/HasStrictMode.php` |
| per-model 连接 | ❌ | `$connectionName` 是死属性 `Model.php:38`，全局单静态 PDO（修复计划列为 P2 待办） |
| `withoutTimestamps()` | ❌ | |
| `model:show` 命令 | ❌ | `bin/Console/Commands/` 无此命令 |
| trait 引导递归（父类 use 子类生效） | ✅ | `Model.php:104-161` 自写 classUsesRecursive |

---

## 二、关系层

**文件：** `bin/Database/Relations/`（13 个类）+ `QueryBuilder/BuildsRelationships.php`

| 功能 | 状态 | 证据 / 说明 |
|------|------|------|
| 11 种关系（hasOne/hasMany/belongsTo/belongsToMany/hasOneThrough/hasManyThrough/morphOne/morphMany/morphTo/morphToMany/morphedByMany） | ✅ | 定义入口 `Model/HasRelationships.php:136-385` |
| eager load（字符串/数组/闭包约束/嵌套点号；嵌套恒定 2 条关系查询） | ✅ | `BuildsRelationships.php:308-406` |
| 延迟加载 load()/loadCount/loadSum、模型级默认 `$with`/`$withCount` | ✅ | `HasRelationships.php:425-511`、`Model.php:264-267` |
| whereHas / whereDoesntHave / orWhereHas / orWhereDoesntHave（EXISTS 子查询） | ✅ | `BuildsRelationships.php:45-72` |
| has()/doesntHave()/orHas()/orDoesntHave()（含计数比较 `has('posts','>=',3)` 与嵌套 `has('posts.comments')`） | ✅ | 阶段8：`>= 1` 走 EXISTS 快路径，计数比较编译 `(SELECT COUNT(*) ...) op n`；whereHas 家族同样支持点号嵌套 |
| `whereMorphedTo` | ❌ | |
| 关系聚合 withCount/withSum/withAvg/withMin/withMax | ✅ | `BuildsRelationships.php:155-303`，各关系类自带 `getAggregateSubQuery()` |
| morphMap（enforceMorphMap/getMorphMap/flushMorphMap） | ✅ | `Relation.php:162-198` |
| 关系写方法 save/saveMany/create/createMany/associate/dissociate | ✅ | 含 MorphTo 的 associate/dissociate |
| attach/detach/sync/updateExistingPivot/withPivot/withTimestamps/using(自定义 Pivot) | ✅ | `BelongsToMany.php`；sync 阶段8 起支持 `id => 附加列` 映射形式与 `detach=false`；attach 仅接受 id 数组 |
| toggle / syncWithoutDetaching / syncWithPivotValues | ✅ | 阶段8（MorphToMany 经继承获得） |
| 关系级 `make()`（实例化不落库） | ✅ | 阶段8：HasOneOrMany 接线外键、MorphOneOrMany 接线 morphId+morphType、BelongsToMany 纯实例化 |
| attach 接受模型实例 / Collection | ❌ | |
| 高阶 `orWhere` 作用域代理（`User::popular()->orWhere->active()`） | ❌ | |
| `latestOfMany`/`oldestOfMany`/`ofMany`（一对一最新记录） | ❌ | |
| 关系上 is()/isNot() 比较 | ❌ | |

---

## 三、查询构建器

**文件：** `bin/Database/QueryBuilder.php`（1126 行）+ 5 个 trait（BuildsWhereClauses / BuildsRelationships / ChunksResults / PaginatesResults / CompilesQueries）

| 功能 | 状态 | 证据 / 说明 |
|------|------|------|
| WHERE 全系（嵌套闭包/数组/whereIn 子查询/whereNot/whereExists/日期系列/whereColumn/whereRaw/when/unless/tap） | ✅ | `QueryBuilder/BuildsWhereClauses.php` |
| 操作符白名单 + 空 whereIn → `0=1` 防注入 | ✅ | 本轮修复项 |
| JOIN inner/left/right | ✅ | `QueryBuilder.php:242-262` |
| crossJoin / joinSub / leftJoinSub | ✅ | 阶段8：子查询绑定走新增 `join` 桶（顺序 join→where→having→order→union）；闭包 on 条件仍缺；注意 update()/delete() 不编译 JOIN，子查询 JOIN 仅用于 SELECT |
| groupBy/groupByRaw/having/havingRaw/orderBy 全系/latest/oldest/resetOrders | ✅ | `QueryBuilder.php:267-341` |
| inRandomOrder | ⚠️ | 用 `RAND()`，仅 MySQL |
| 聚合 count/sum/avg/min/max、exists/doesntExist | ✅ | `QueryBuilder.php:591-661` |
| insert / insertGetId / update / delete / increment / decrement | ✅ | update/delete 均应用全局作用域 |
| upsert / updateOrInsert / insertOrIgnore | ✅ | 阶段8：按驱动分方言（MySQL `ON DUPLICATE KEY UPDATE` / SQLite|PG `ON CONFLICT`），模型时间戳自动补齐；`insertUsing` 仍缺 |
| chunk / chunkById / each / eachById | ✅ | `ChunksResults.php` |
| cursor() / lazy() / lazyById() 流式迭代 | ✅ | 阶段8：返回 `\Generator`（无 LazyCollection，链式集合操作需先 get） |
| pluck / value | ⚠️ | `QueryBuilder.php:545,563` 返回原生数组（Laravel 返回 Collection） |
| distinct / selectRaw / union / unionAll | ✅ | |
| 悲观锁 lockForUpdate/sharedLock、explain、dump/dd | ✅ | |
| 事务三件套 + `Schema::transaction(callable)` | ✅ | `Schema/Schema.php:158-174` |
| 子查询 select / addSelect / orderBy / fromSub | ❌ | |
| whereKey / whereKeyNot | ✅ | 阶段8：按模型主键过滤 |
| whereJsonContains 等 JSON 子句 | ❌ | |
| 向量子句 whereVectorSimilarTo（Laravel 13 新增） | ❌ | |
| 多命名连接 / 读写分离 | ❌ | `ConnectionManager` 单静态 PDO，config 仅 mysql 一个连接 |
| 多驱动 grammar（Postgres/SQLServer 方言） | ❌ | 单 grammar；SQLite 内存库可跑查询，但 `SHOW INDEX` 等 introspection 与 EXPLAIN 为 MySQL 专用 |
| 查询日志/慢查询阈值/失败统计/报告 | ✅ | `Debug/DatabaseDebugger.php`（超出 Laravel 基础配备，亮点） |

---

## 四、Schema 与迁移

**文件：** `bin/Database/Schema/`（Schema 门面、Blueprint、ColumnDefinition、ForeignIdDefinition、SchemaBuilder）+ `Migrations/`

| 功能 | 状态 | 证据 / 说明 |
|------|------|------|
| 列类型全家（各档 int/string/text 系/decimal/float/bool/enum/set/日期时间系/json/uuid/ip/mac/geometry 系/softDeletes） | ✅ | `Schema/Blueprint.php` |
| morphs / nullableMorphs / uuidMorphs / rememberToken | ❌ | |
| 修饰符（nullable/default/unsigned/autoIncrement/primary/unique/useCurrent(OnUpdate)/comment/first/after/charset/collation…） | ✅ | `Schema/ColumnDefinition.php` |
| 索引 primary/unique/index/fullText/spatialIndex + dropPrimary/dropUnique/dropIndex/dropForeign | ✅ | |
| dropFullText / dropSpatialIndex | ❌ | |
| foreignId()->constrained() 流式外键链（references/on/cascadeOn*/restrictOn*/nullOn*） | ✅ | `ForeignIdDefinition.php:40-133`（本轮修复项） |
| 流式列修改 `->change()` | ⚠️ | 仅命令式 `modifyColumn(name, newType, attrs)` `Blueprint.php:539` |
| 表操作 rename/dropColumn/renameColumn/drop/dropIfExists | ✅ | |
| introspection（hasTable/hasColumn/getColumns/getTables/getIndexes/hasIndex/getForeignKeys） | ⚠️ | 依赖 MySQL information_schema/`SHOW INDEX`，SQLite 下部分失效 |
| 迁移运行器（run/rollback/reset/refresh/fresh/status、batch 台账、匿名类支持、事务包裹） | ✅ | `Migrations/Migrator.php` + `MigrateCommand.php`；`fresh` 连台账表一起删（本轮修复项） |
| make:migration（秒级时间戳，同秒冲突抛异常） | ✅ | `Migrations/MigrationCreator.php` |

---

## 五、Seeder 与工厂

**文件：** `bin/Database/Seeders/` + `bin/Database/Factory.php`

| 功能 | 状态 | 证据 / 说明 |
|------|------|------|
| Seeder 基类（run/call/create/createMany/factory）+ SeederRepository | ✅ | 命名空间固定 `Database\Seeders` |
| SeederFactory（state/afterMaking/afterCreating/make/create/createMany/withStates） | ✅ | |
| 模型工厂（静态注册表式：define/state/create/make/times/flush） | ✅ | `Factory.php` |
| 工厂类 DSL（`User::factory()->count()->has()->for()->sequence()`、`#[UseModel]`） | ❌ | |
| make:factory / make:seeder 命令 | ✅ | `bin/Console/Commands/` |

---

## 六、分页

**文件：** `QueryBuilder/PaginatesResults.php` + 三个 Paginator 类

| 功能 | 状态 | 证据 / 说明 |
|------|------|------|
| paginate（LengthAware，含 total）/ simplePaginate（多取 1 条判 hasMore） | ✅ | `PaginatesResults.php:30-93` |
| cursorPaginate | ⚠️ | `PaginatesResults.php:104-149`：硬编码 `where('id','>')` `:116`，仅按 id 升序、游标只含 id、仅 next 游标无 previous；Laravel 支持任意排序列/方向/双向游标 |
| Paginator 元信息（url/appends/fragment/firstItem/lastItem/render(window) HTML） | ✅ | `LengthAwarePaginator.php`、`CursorPaginator.php` |
| 页码解析（setPageResolver 闭包注入，默认回落 `$_GET`） | ✅ | |

---

## 七、集合

**文件：** `bin/Database/Collection.php`（736 行）

| 功能 | 状态 | 证据 / 说明 |
|------|------|------|
| 60+ 方法（make/put/only/except/modelKeys/pluck/groupBy/keyBy/sortBy/chunk/where 系/unique/flatten/merge/diff/…+ ArrayAccess/Countable/IteratorAggregate/JsonSerializable） | ✅ | `modelKeys` 在 `Collection.php:102` |
| Base/Eloquent 双层拆分 + 模型集合专用 `find()`/`load()` | ❌ | 单一 Collection 类，map 后类型不变 |
| LazyCollection | ❌ | |
| 高阶代理（`$collection->each->save()`） | ❌ | |

---

## 八、Laravel 13 新特性专项

| Laravel 13 特性 | 状态 | 说明 |
|------|------|------|
| PHP 属性配置（`#[Table]/#[Fillable]/#[Hidden]/#[Connection]/#[Scope]/#[ScopedBy]/#[ObservedBy]` 等） | ❌ | `bin/Database/` 内无任何 PHP 属性用法（已验证）；`Attribute.php` 是访问器/修改器类，不要混淆 |
| 向量检索（`whereVectorSimilarTo` + pgvector + embeddings 工作流） | ❌ | 无任何 Vector 相关代码 |
| JSON:API 资源 | ❌ | 无 JSON:API 序列化层 |

---

## 已对齐清单（纠正 2026-04-08 旧版过时条目）

旧版标 ❌、现已经 ✅ 的条目（多数为本轮七阶段修复及此前的演进补齐）：

| 旧版条目 | 现状证据 |
|------|------|
| firstOrCreate / firstOrNew / updateOrCreate | `Model.php:393-421` |
| findOr / firstOr | `QueryBuilder.php:439,430`；另有 sole `Model.php:450` |
| Lazy Eager Loading（load()） | `HasRelationships.php:469` |
| replicate() | `Model.php:812`（但缺 $except 参数） |
| make: 命令族 | `bin/Console/Commands/` 已有 make:model/controller/migration/factory/middleware/observer/policy/request/command/seeder 共 10 个生成器 |

旧版标 ❌ 且至今仍 ❌ 的条目：读写分离（依旧准确；upsert 家族已于阶段8补齐）。

本轮七阶段修复额外带来的 Laravel 行为对齐（详见 `docs/ORM.md:680-696`）：标识符反引号包裹、belongsTo 默认外键按关系名推导且 NULL 外键返回 null、嵌套 with 恒 2 条 SQL、全局作用域应用于 update/delete/increment、软删模型查询级 delete 转 UPDATE、操作符白名单、空 whereIn → `0=1`、morphMap 全局生效、`$model->update()` 为 fill+save 实例语义（原始终 SQL 走 `updateSql`）。

### 阶段8（2026-09-12，P0 批次实施记录）

P0 全部 9 项已实施完毕（提交 7852138 / 3466560 / dc85b80），另连带修复 3 个预存缺陷：

| 条目 | 说明 |
|------|------|
| upsert/updateOrInsert/insertOrIgnore、cursor()/lazy()/lazyById()、joinSub/leftJoinSub/crossJoin、whereKey/whereKeyNot | 提交 7852138（阶段8A） |
| saveQuietly/deleteQuietly/restoreQuietly/forceDeleteQuietly、forceCreate、$dispatchesEvents、wasChanged()/getChanges()/getPrevious()、is()/isNot()、replicate($except) | 提交 3466560（阶段8B） |
| has()/orHas()/doesntHave()/orDoesntHave()（计数比较 + 嵌套，whereHas 家族同步支持嵌套）、关系 make()、toggle/syncWithoutDetaching/syncWithPivotValues、sync 映射形式 | 提交 dc85b80（阶段8C） |
| 连带缺陷① HasOneOrMany::$related 未赋值 → hasMany/hasOne 关系 create() 必抛未初始化错误 | dc85b80 修复 |
| 连带缺陷② morphOne/morphMany 的 whereHas 丢多态类型条件（同 morph_id 异类型行跨类型泄漏） | dc85b80 修复 |
| 连带缺陷③ morphOne/morphMany 的 withCount 误调基类占位实现直接抛 BadMethodCallException | dc85b80 修复 |

回归测试：`tests/OrmP0BatchTest.php` 27 例；全量 2221 例通过。

**文档漂移提醒**：`docs/ORM.md:452` 提到的 `sortByDesc` 在 `Collection.php` 中并不存在（实际是 `sortBy($key, $descending)`），补齐或修文档二选一。

---

## 差距清单（按 ROI 排序，可作下一轮补齐计划输入）

### P0 — ✅ 已全部完成（2026-09-12 阶段8，见上表实施记录）

### P1 — 结构性补强

| # | 条目 | 工作量 | 说明 |
|---|------|--------|------|
| 1 | PHP 属性配置（`#[Table]/#[Fillable]/#[Scope]/#[ScopedBy]/#[ObservedBy]` 等子集） | 中 | Laravel 13 标志性特性；反射已按类缓存，与属性声明共存、非破坏性 |
| 2 | 多命名连接 + 读写分离 | 中 | `$connectionName`（Model.php:38）接线，ConnectionManager 多实例化 |
| 3 | 流式 `->change()` 列修改 | 中 | 现有 modifyColumn 命令式改链式 |
| 4 | morphs/nullableMorphs/uuidMorphs/rememberToken/dropFullText/dropSpatialIndex | 极小 | Blueprint 别名/命令 |
| 5 | HasUuids（UUIDv7）/ HasUlids | 小 | 新 trait + boot 钩子 |
| 6 | Prunable / MassPrunable + model:prune 命令 | 小-中 | trait + Console 命令 |
| 7 | Eloquent/Base Collection 分层 + 模型集合 find()/load() | 中 | 736 行拆两层 |
| 8 | 子查询 select / addSelect / orderBy / fromSub | 中 | grammar 子查询编译 |
| 9 | whereJsonContains 等 JSON 子句 | 小-中 | MySQL `JSON_EXTRACT` 方言先行 |
| 10 | 软删事件 trashed/forceDeleting/forceDeleted + replicating | 小 | HasEvents 事件表扩列 |
| 11 | 工厂类 DSL（`User::factory()->has()/for()/sequence()`） | 中-大 | Factory.php 重构为 per-model 工厂类 |
| 12 | cursorPaginate 泛化（任意排序列/方向/双向游标） | 中 | PaginatesResults.php:104-149 |
| 13 | morphOne/morphMany 的 save()/create() 写方法（阶段8调研时新发现：两类关系完全没有写入口） | 小 | MorphOneOrMany 增加 save/saveMany/create/createMany（阶段8C 已提供 make() 基础） |

### P2 — 生态/长线

| # | 条目 | 工作量 | 说明 |
|---|------|--------|------|
| 1 | 多驱动 grammar（PostgreSQL 优先） | 大 | 单一 MySQL 方言是读写分离、向量检索、跨库测试的共同前置 |
| 2 | 向量检索（whereVectorSimilarTo） | 大 | 依赖 pgvector + P2-1 前置 |
| 3 | LazyCollection | 中 | 配合 lazy() 使用 |
| 4 | queueable 模型事件监听 / 模型事件广播 | 中 | 依赖队列生态成熟度 |
| 5 | JSON:API 资源 | 中 | 属资源序列化层，可独立于 DB 层立项 |
| 6 | Scope 接口类全局作用域 / withoutGlobalScopesExcept / model:show | 小 | 零散收尾 |
| 7 | Carbon 引入与否 | — | 设计取舍：维持零依赖则维持现状，文档明确即可 |

---

## 附：ORM 测试覆盖现状

| 类别 | 测试文件 | 说明 |
|------|----------|------|
| Eloquent 行为对齐 | `tests/EloquentParityTest.php` | 758 行，45+ 用例（strict 模式/序列化/生命周期/replicate 等） |
| 阶段8 P0 批次回归 | `tests/OrmP0BatchTest.php` | 27 例：upsert 家族/流式迭代/joinSub/whereKey/静默家族/dispatchesEvents/wasChanged/is/replicate/has 家族/关系 make/sync 系列/3 个连带缺陷 |
| 本轮新增回归 | QueryCompilerRegressionTest / GlobalScopeIntegrityTest / RelationDefaultsTest / TraitInheritanceTest / EagerLoadingConsistencyTest / OrmRegressionTest / MigrationSmokeTest | 七阶段修复的回归防线 |
| 历史存量 | QueryBuilderTest / ModelTest / RelationTest / SoftDeletesTest / PaginatorTest 等 | 旧版记录约 ~200/~100/~45/22 个用例，覆盖面以本轮文档核对为准 |

> 全量测试基线：2221 例通过（2026-09-12，阶段8 完成后）。下一轮补齐建议从 P1 清单选取（P0 已清零），每项同步扩充 EloquentParityTest 或 OrmP0BatchTest。
