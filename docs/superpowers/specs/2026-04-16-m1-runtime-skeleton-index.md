# M1 文档索引

## 说明

这组文档围绕 `M1 运行时骨架统一` 组织成一套主 spec + 执行附录。

适用场景：

- 想先看整体差异、范围和验收标准
- 想直接进入 `M1` 的实施准备
- 想按任务、顺序、测试分别阅读

---

## 阅读顺序

建议按下面顺序阅读：

1. [M1 主 spec](/home/x/src/install/php/First/docs/superpowers/specs/2026-04-16-m1-runtime-skeleton-spec.md)
2. [M1 任务拆解](/home/x/src/install/php/First/docs/superpowers/specs/2026-04-16-m1-runtime-skeleton-tasks.md)
3. [M1 改造顺序](/home/x/src/install/php/First/docs/superpowers/specs/2026-04-16-m1-runtime-skeleton-order.md)
4. [M1 测试清单](/home/x/src/install/php/First/docs/superpowers/specs/2026-04-16-m1-runtime-skeleton-tests.md)

这个顺序对应的是：

- 先看主 spec
- 再看任务范围
- 再看改造顺序
- 最后看测试验证

---

## 文档用途

## 1. 主 spec

[M1 主 spec](/home/x/src/install/php/First/docs/superpowers/specs/2026-04-16-m1-runtime-skeleton-spec.md)

适合在这些时候看：

- 需要快速理解“当前项目和 Laravel 13 还差什么”
- 需要看 `M1 / M2 / M3` 的总体路线
- 需要明确 `M1` 的目标、范围、边界和验收标准

主要内容：

- 背景与对齐目标
- `P0 / P1 / P2` 差异优先级
- `M1 / M2 / M3` 三阶段路线
- `M1` 范围、原则、交付物、验收标准

## 2. M1 任务拆解

[M1 任务拆解](/home/x/src/install/php/First/docs/superpowers/specs/2026-04-16-m1-runtime-skeleton-tasks.md)

适合在这些时候看：

- 要把 `M1` 拆成具体工作项
- 要明确 `T1-T6` 分别包含什么
- 要给自己或团队分配任务

主要内容：

- `Bootstrap`
- `Container`
- `HTTP`
- `Exception`
- `Response`
- `Route`

六大块的具体任务范围。

## 3. M1 改造顺序

[M1 改造顺序](/home/x/src/install/php/First/docs/superpowers/specs/2026-04-16-m1-runtime-skeleton-order.md)

适合在这些时候看：

- 要确认先改哪里，后改哪里
- 要避免改造顺序错误导致返工
- 要在实施过程中设置停顿检查点

主要内容：

- 正确改造顺序
- 不建议的错误顺序
- 每一步完成后的检查点

## 4. M1 测试清单

[M1 测试清单](/home/x/src/install/php/First/docs/superpowers/specs/2026-04-16-m1-runtime-skeleton-tests.md)

适合在这些时候看：

- 要确定每次改动后最少跑哪些测试
- 要确认 `M1` 的验收口径
- 要区分一级回归集和二级回归集

主要内容：

- 骨架级回归测试集合
- 按 `T1-T6` 映射的测试建议
- `M1` 完成前的测试标准

---

## 按角色阅读

如果你是自己推进，可以这样看：

- 先看主 spec
- 再看任务拆解
- 然后看改造顺序
- 实施时随手对照测试清单

如果你是做 code review，可以这样看：

- 先看主 spec 里的 `M1 设计原则`
- 再看改造顺序
- 最后对照测试清单判断是否覆盖主链路

如果你是准备直接开工，可以这样看：

- 先看主 spec
- 再看任务拆解
- 最后按改造顺序推进

---

## 当前推荐入口

如果现在准备真正开始 `M1`，建议从这里开始：

1. 阅读 [M1 主 spec](/home/x/src/install/php/First/docs/superpowers/specs/2026-04-16-m1-runtime-skeleton-spec.md) 里的 `M1 范围`、`M1 设计原则` 和 `实施入口`
2. 打开 [M1 任务拆解](/home/x/src/install/php/First/docs/superpowers/specs/2026-04-16-m1-runtime-skeleton-tasks.md) 对照 `T1 Bootstrap`
3. 按 [M1 改造顺序](/home/x/src/install/php/First/docs/superpowers/specs/2026-04-16-m1-runtime-skeleton-order.md) 推进
4. 每次改动后参考 [M1 测试清单](/home/x/src/install/php/First/docs/superpowers/specs/2026-04-16-m1-runtime-skeleton-tests.md) 跑骨架级回归

---

## 一句话索引

- 看主 spec： [M1 主 spec](/home/x/src/install/php/First/docs/superpowers/specs/2026-04-16-m1-runtime-skeleton-spec.md)
- 看任务范围： [M1 任务拆解](/home/x/src/install/php/First/docs/superpowers/specs/2026-04-16-m1-runtime-skeleton-tasks.md)
- 看实施顺序： [M1 改造顺序](/home/x/src/install/php/First/docs/superpowers/specs/2026-04-16-m1-runtime-skeleton-order.md)
- 看测试验证： [M1 测试清单](/home/x/src/install/php/First/docs/superpowers/specs/2026-04-16-m1-runtime-skeleton-tests.md)
