# Tasks: eloquent-parity

## Phase 1: 约定梳理

- [x] 对照当前模型能力与 Laravel 13 Eloquent 约定
- [x] 固定主键、时间戳、表名、连接、默认属性等基线

## Phase 2: 安全与生命周期

- [x] 设计 mass assignment 相关边界
- [x] 设计 strictness 行为与失败策略
- [x] 校正模型 boot、events、observer 的执行时机

## Phase 3: 关系与序列化

- [x] 校正 relationship 行为细节
- [x] 校正模型数组/JSON 序列化规则
- [x] 明确与 resource、policy、auth 的协作边界

## Phase 4: 验证

- [x] 扩展 ORM、关系、模型事件相关测试
- [x] 确认不破坏现有 QueryBuilder / Model 能力
