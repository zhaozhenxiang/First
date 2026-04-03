## 1. 静态属性隔离

- [x] 1.1 重构 Model::$globalScopes 为 per-class 存储 `static::$globalScopes[static::class]`
- [x] 1.2 重构 Model::$bootedModels 为 per-class 存储
- [x] 1.3 重构 Model::$morphMap 为 per-class 存储
- [x] 1.4 修复 addGlobalScope / forgetGlobalScope / getGlobalScopes / clearGlobalScopes 使用 per-class 键
- [ ] 1.5 编写测试：验证不同模型的 scope/boot/morphMap 相互隔离

## 2. Collection 链式调用

- [x] 2.1 修改 Collection 所有变换方法返回 `new static($result)` 而非 `array`（map, filter, reject, sortBy, sort, reverse, shuffle, unique, collapse, flatten, slice, take, skip, chunk, merge, diff, intersect, values, keys, nth, flip, union, pop）
- [x] 2.2 确保 where / whereIn / whereNull / whereNotNull / firstWhere 返回 Collection
- [x] 2.3 确认 pluck / groupBy / keyBy 保持返回原生 array
- [x] 2.4 确认聚合方法 count / sum / avg / max / min 保持返回标量
- [ ] 2.5 编写测试：链式调用 $c->where()->map()->values() 完整流程

## 3. Debug 系统集成

- [x] 3.1 在 QueryBuilder::get() 中集成 DatabaseDebugger 日志记录（try/finally 包裹）
- [x] 3.2 在 QueryBuilder::getArray() 中集成日志记录
- [x] 3.3 在 QueryBuilder::insert() 中集成日志记录
- [x] 3.4 在 QueryBuilder::insertGetId() 中集成日志记录
- [x] 3.5 在 QueryBuilder::update() 中集成日志记录
- [x] 3.6 在 QueryBuilder::delete() 中集成日志记录
- [x] 3.7 确认默认禁用，需手动 enable() 才记录
- [ ] 3.8 编写测试：验证 select/insert/update/delete 查询被正确记录

## 4. Migration 匿名类支持

- [x] 4.1 重写 Migrator::resolve() 支持通过 include 获取匿名类实例
- [x] 4.2 修改 Migrator migrations 表记录字段为文件名（非类名）
- [x] 4.3 确认 MigrationCreator 生成的 stub 与新解析方式兼容
- [ ] 4.4 编写测试：运行和回滚匿名类 migration

## 5. Pivot 模型化

- [x] 5.1 修改 BelongsToMany::hydratePivot() 创建 Pivot 模型实例替代 stdClass
- [x] 5.2 支持 using() 自定义 Pivot 子类
- [x] 5.3 确保 Pivot 属性通过 ArrayAccess 和魔术属性可访问
- [x] 5.4 确保 Pivot::delete() 使用复合键正确删除
- [ ] 5.5 编写测试：验证 BelongsToMany 关系返回 Pivot 实例

## 6. QueryBuilder 增强

- [x] 6.1 修复 whereDate/whereDay/whereMonth/whereYear/whereTime SQL 编译（使用 MySQL 日期函数）
- [x] 6.2 添加 QueryBuilder::dump() 方法（输出 SQL+bindings 不终止）
- [x] 6.3 添加 QueryBuilder::dd() 方法（输出后 exit）
- [x] 6.4 添加 QueryBuilder::groupByRaw() 方法
- [ ] 6.5 编写测试：验证 whereDate 编译、dump/dd 行为、groupByRaw

## 7. 模型清理与 Bug 修复

- [x] 7.1 移除空的 Bin\Model\Builder 桩类（检查无引用后删除）
- [x] 7.2 修复 DatabaseServiceProvider 使用 Connection::connection() 替代不存在的 getInstance()
- [x] 7.3 修复 Model::sole() 方法：0 结果和多结果时抛出异常
- [ ] 7.4 编写测试：sole() 的三种场景（0个/1个/多个）
