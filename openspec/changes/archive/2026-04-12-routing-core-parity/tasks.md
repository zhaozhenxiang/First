# Tasks: routing-core-parity

## Phase 1: 路由行为基线

- [x] 梳理现有 `RouteCollection` 与 Laravel 13 的主要差异
- [x] 固定路由元数据模型，明确 path、name、domain、constraints、action 的归属

## Phase 2: 分组与注册

- [x] 实现组属性合并规则
- [x] 统一 prefix / name / domain / where / namespace 行为
- [x] 校正快捷路由与 resource / apiResource 注册逻辑

## Phase 3: 绑定与解析

- [x] 统一路由参数绑定入口
- [x] 让 fallback、redirect、view 路由语义一致
- [x] 补齐路由解析与元信息读取测试

## Phase 4: 验证

- [x] 更新 `RouteEnhancementTest` 与相关路由测试
- [x] 确认后续 middleware / controller dispatch 能直接消费路由元数据
