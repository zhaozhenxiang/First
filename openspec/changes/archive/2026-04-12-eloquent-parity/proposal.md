## Why

当前 ORM 已经具备明显的 Eloquent 风格，包括模型、关系、作用域、事件、序列化、SoftDeletes 等能力，但整体仍然更像“功能拼装完成”，而不是“行为矩阵稳定”。如果目标是系统性向 Laravel 13 靠拢，就需要把模型约定、安全边界和高级行为整理成独立 spec。

## What Changes

- 补齐并规范模型约定与主键策略
- 明确 mass assignment、strictness、默认属性、casts/serialization 等边界
- 规范事件、observer、scope、relationship 的一致行为
- 补充更接近 Laravel 的模型生命周期与错误约束
- 为后续 auth、policy、resource 等上层能力提供更稳定 ORM 底座

## Capabilities

### New Capabilities
- `model-conventions`: 主键、表名、时间戳、连接等约定规范
- `eloquent-safety`: mass assignment 与 strictness 相关安全能力
- `model-lifecycle-parity`: 更完整的模型引导、事件与约束行为

### Modified Capabilities
- `orm-model`: 从 Eloquent 风格模型升级为更稳定的约定集合
- `relationship-runtime`: 关系行为进一步标准化
- `serialization-runtime`: 模型序列化行为更接近 Laravel 预期

## Impact

- `bin/Database/Model.php` 与 `bin/Database/Model/*`
- `bin/Database/Relations/*`
- `bin/Database/SoftDeletes.php` / observer / event 相关模块
- `tests/*Model*`、`Relation*`、`SoftDeletesTest.php`、`ResourceTest.php`
