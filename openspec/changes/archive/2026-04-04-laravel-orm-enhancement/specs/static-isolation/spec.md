## ADDED Requirements

### Requirement: Per-class global scopes isolation
Model 的 `$globalScopes` 属性 SHALL 按 per-class 存储，每个 Model 子类拥有独立的 global scopes，互不影响。

#### Scenario: 不同模型的 scope 隔离
- **WHEN** `User::addGlobalScope('active', fn(...))` 被调用
- **THEN** `User::getGlobalScopes()` 返回包含 `active` 的数组
- **AND** `Post::getGlobalScopes()` 返回空数组

#### Scenario: forgetGlobalScope 只影响当前模型
- **WHEN** `User::forgetGlobalScope('active')` 被调用
- **THEN** `User::getGlobalScopes()` 不再包含 `active`
- **AND** 其他模型的 scopes 不受影响

### Requirement: Per-class booted models tracking
Model 的 `$bootedModels` 属性 SHALL 按 per-class 存储，每个子类独立追踪 boot 状态。

#### Scenario: 不同模型独立 boot
- **WHEN** `User` 模型首次被使用触发 `boot()`
- **THEN** `User` 的 boot 状态为已完成
- **AND** `Post` 的 boot 状态为未完成（直到 Post 首次使用）

### Requirement: Per-class morph map isolation
Model 的 `$morphMap` 属性 SHALL 按 per-class 存储，每个子类维护独立的多态映射表。

#### Scenario: morph map 独立
- **WHEN** `User` 设置了 `enforceMorphMap(['user' => User::class])`
- **THEN** `User` 的 morph map 包含该映射
- **AND** `Post` 的 morph map 为空

### Requirement: clearGlobalScopes 只清除当前模型
`clearGlobalScopes()` 方法 SHALL 只清除调用类的 global scopes，不影响其他模型。

#### Scenario: 清除不影响其他模型
- **WHEN** `User::clearGlobalScopes()` 被调用
- **THEN** `User::getGlobalScopes()` 返回空数组
- **AND** `Post::getGlobalScopes()` 保持不变
