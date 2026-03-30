## Context

当前框架已有 QueryBuilder（2103行）、Model 基类（1644行）、Collection（667行）和 8 种关联关系。ORM 功能覆盖率约 75%，但缺少 MorphTo 逆向关联、自定义 Pivot 模型、Observer 模式等关键基础设施。此外存在若干 bug：BelongsToMany 的 pivot 数据未正确水合、eager loading 对空数组无保护、MorphMany 仅支持单一父类类型。

## Goals / Non-Goals

**Goals:**
- 补全 MorphTo 关联，使多态关联完整闭环
- 实现 Pivot 模型，支持中间表的自定义属性和类型转换
- 实现 Observer 模式，提供类级别的模型事件监听
- 实现模型工厂，支持测试数据生成
- 修复现有 eager loading 和参数绑定的 bug
- 重构 MorphMany/MorphOne 继承体系，消除 MorphOneOrMany 死代码

**Non-Goals:**
- 多数据库连接支持（当前使用静态 PDO）
- 复合主键支持
- 模型序列化/反序列化（serialize/unserialize）
- 数据库通知系统
- Lazy eager loading 的所有变体（仅实现 loadCount/loadSum）

## Decisions

### 1. MorphTo 实现策略
**选择**: 在 `bin/Database/Relations/MorphTo.php` 中实现，继承 `Relation` 基类。
**理由**: MorphTo 与 BelongsTo 类似但不完全相同——它需要根据 `morph_type` 列动态解析目标模型类。独立实现比继承 BelongsTo 更清晰。
**替代方案**: 继承 BelongsTo 并重写约束方法——耦合太高，BelongsTo 的 eager loading 逻辑与多态不兼容。

### 2. Pivot 模型设计
**选择**: 新建 `Pivot` 类继承 `Model`，禁用自增主键和时间戳，添加 `pivotParent` 和 `pivotColumns` 属性。
**理由**: Laravel 的 Pivot 就是轻量 Model，共享属性访问、类型转换等能力，但有自己的特殊行为（不自增、默认无时间戳）。
**替代方案**: 用 stdClass 表示 pivot 行——无法支持访问器/类型转换。

### 3. Observer 模式实现
**选择**: 新建 `Observer` 接口 + `Model::observe()` 方法，通过反射发现方法名（如 `created`、`updating`）自动注册为事件监听器。
**理由**: 遵循 Laravel 的 Observer 模式，用户定义一个类包含与事件同名的方法即可。
**替代方案**: 用属性标注事件方法——增加复杂度，无额外收益。

### 4. 模型工厂设计
**选择**: 新建 `Factory` 类，支持 `define()` 注册定义、`states()` 定义状态变体、`create()`/`make()` 生成模型实例。
**理由**: 基础工厂模式即可满足测试数据生成需求，不需要 Laravel 9+ 的 Factory 类重构。
**替代方案**: 使用 Faker 库直接在测试中生成——每个测试都要重复定义逻辑。

### 5. MorphMany/MorphOne 继承重构
**选择**: 让 MorphMany 继承 MorphOneOrMany，MorphOne 继承 MorphMany，消除当前 MorphOneOrMany 死代码。
**理由**: 与 HasOne/HasMany 继承 HasOneOrMany 的模式一致。
**风险**: 现有 MorphMany 和 MorphOne 的测试需验证不回归。

### 6. 参数绑定修复策略
**选择**: whereBetween 统一使用 `?` 占位符，update 使用 `SET col = ?` 替代命名参数 `:col`。
**理由**: 全部使用位置参数 `?` 避免与现有 WHERE 绑定冲突。

## Risks / Trade-offs

- **MorphTo eager loading 性能**: 多态类型需要分组查询（N+1 类型数），但这是 Laravel 的标准做法，可接受 → 通过 groupBy morph_type 批量查询缓解
- **Pivot 模型与现有 BelongsToMany 兼容性**: 现有 BelongsToMany 直接操作数组，引入 Pivot 模型可能破坏向后兼容 → 在 Model 基类中按需水合 pivot 属性，保持原有 API
- **Observer 与现有事件系统冲突**: 现有闭包事件与 Observer 共存 → Observer 注册为普通事件监听器，无冲突
- **Factory 依赖 Faker**: 模型工厂需要 Faker 数据 → Factory::define() 回调中由用户自行选择是否使用 Faker，框架不强制依赖
