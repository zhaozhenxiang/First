# M2 文档索引

## 说明

这组文档围绕 `M2 Web 与数据主链路补齐` 组织，但不再把 `Session / Cookie / CSRF / Auth / Validation / ORM` 混成一个阶段。

当前状态：

- `M1` 已于 `2026-04-18` 完成验收并关账
- `M2` 已拆分为 `M2-A / M2-B / M2-C`
- `M2` 文档拆分 plan 已完成，索引和三份子 spec 已落地
- `M2-A` Web 状态基础 plan 已完成
- `M2-B` 身份与输入链路 plan 已完成，最终实现进入 `master` 历史
- `M2-C` ORM 生命周期 plan 已完成，最终实现进入 `master` 历史
- `M2` 当前拆分范围已完成；后续新工作应先建立新的 spec / plan，而不是重复执行这些 plan

适用场景：

- 想知道 `M2` 为什么要按依赖顺序推进
- 想复盘 `M2` 各阶段 spec、plan 和完成状态
- 想把 `M2` 拆成更小的规划入口，而不是继续维护一个过大的总目标

---

## 阅读顺序

建议按下面顺序阅读：

1. [M1 Closure](/home/x/src/install/php/First/docs/superpowers/specs/2026-04-18-m1-runtime-skeleton-closure.md)
2. [M2-A Web 状态基础](/home/x/src/install/php/First/docs/superpowers/specs/2026-04-18-m2a-web-state-foundation-spec.md)
3. [M2-B 身份与输入链路](/home/x/src/install/php/First/docs/superpowers/specs/2026-04-18-m2b-identity-and-input-spec.md)
4. [M2-C ORM 生命周期](/home/x/src/install/php/First/docs/superpowers/specs/2026-04-18-m2c-orm-lifecycle-spec.md)

这个顺序对应的是：

- 先确认 `M1` 的骨架已经稳定
- 再补 Web 状态基础
- 再补身份与输入链路
- 最后补数据访问生命周期

---

## 实施计划入口

如果目标是执行而不是只阅读 spec，按下面顺序进入 plan：

1. [M2 文档拆分 plan](/home/x/src/install/php/First/docs/superpowers/plans/2026-04-18-m2-doc-split.md) - 已完成，用于追溯索引和三份子 spec 的拆分来源
2. [M2-A Web 状态基础 plan](/home/x/src/install/php/First/docs/superpowers/plans/2026-04-18-m2a-web-state-foundation.md) - 已完成，用于追溯 `Session / Cookie / CSRF` 的实施闭环
3. [M2-B 身份与输入链路 plan](/home/x/src/install/php/First/docs/superpowers/plans/2026-04-28-m2b-identity-and-input.md) - 已完成，用于追溯 `Validation / FormRequest / Auth` 的实施闭环
4. [M2-C ORM 生命周期 plan](/home/x/src/install/php/First/docs/superpowers/plans/2026-04-28-m2c-orm-lifecycle.md) - 已完成，用于追溯 ORM 生命周期的实施闭环

---

## 文档用途

## 1. M2-A Web 状态基础

[M2-A Web 状态基础](/home/x/src/install/php/First/docs/superpowers/specs/2026-04-18-m2a-web-state-foundation-spec.md)

适合在这些时候看：

- 要先补 `Session / Cookie / CSRF`
- 要判断为什么 `Auth` 之前必须先把 Web 状态闭环做稳
- 要看有状态请求应该落在哪些 runtime 边界

主要内容：

- `Session / Cookie / CSRF` 的阶段目标
- Web 状态主链路
- 设计原则、交付物和验收标准

## 2. M2-B 身份与输入链路

[M2-B 身份与输入链路](/home/x/src/install/php/First/docs/superpowers/specs/2026-04-18-m2b-identity-and-input-spec.md)

适合在这些时候看：

- 要补 `Validation / FormRequest / Auth`
- 要把认证、中间件、控制器和异常输出放到同一条链路里看
- 要明确这一阶段依赖 `M2-A`，但还不扩张 `ORM` 目标

主要内容：

- 输入与身份主链路
- `Validation / FormRequest / Auth` 的阶段边界
- 交付物和验收标准

## 3. M2-C ORM 生命周期

[M2-C ORM 生命周期](/home/x/src/install/php/First/docs/superpowers/specs/2026-04-18-m2c-orm-lifecycle-spec.md)

适合在这些时候看：

- 要补 `ORM` 与常用数据访问体验
- 要判断为什么数据层应该在前两个阶段稳定后再推进
- 要明确模型、查询和持久化与上层 runtime 的连接点

主要内容：

- 数据访问主链路
- `ORM` 的阶段范围和边界
- 交付物和验收标准

---

## 按角色阅读

如果你是准备复盘 `M2` 或规划下一阶段，可以这样看：

- 先读 [M1 Closure](/home/x/src/install/php/First/docs/superpowers/specs/2026-04-18-m1-runtime-skeleton-closure.md)
- 再读 [M2-A Web 状态基础](/home/x/src/install/php/First/docs/superpowers/specs/2026-04-18-m2a-web-state-foundation-spec.md)
- 然后按 `M2-B -> M2-C` 顺序复核已完成的实现边界

如果你是做阶段规划，可以这样看：

- 先读三份 `M2` 子 spec 的 `范围`、`Out of Scope` 和 `验收标准`
- 再看 `实施计划入口`，确认对应 plan 的完成状态
- 如果继续推进新实施，先建立新的 spec / plan，不要重复执行已完成的 `M2-B` 或 `M2-C`

如果你是做 code review，可以这样看：

- 先看每份 spec 的 `设计原则`
- 再看 `运行时主链路 / 输入与身份主链路 / 数据访问主链路`
- 最后对照验收标准判断实现是否越界

---

## 当前推荐入口

如果现在要从 `M1` 复盘 `M2` 或规划下一阶段，建议从这里开始：

1. 先读 [M1 Closure](/home/x/src/install/php/First/docs/superpowers/specs/2026-04-18-m1-runtime-skeleton-closure.md)，确认 `M1` 不再继续扩写
2. 再读 [M2-A Web 状态基础](/home/x/src/install/php/First/docs/superpowers/specs/2026-04-18-m2a-web-state-foundation-spec.md)，把已完成的 Web 状态基础作为后续依赖背景
3. 如果复核身份链路，进入 [M2-B 身份与输入链路 plan](/home/x/src/install/php/First/docs/superpowers/plans/2026-04-28-m2b-identity-and-input.md)
4. 如果复核数据层，进入 [M2-C ORM 生命周期 plan](/home/x/src/install/php/First/docs/superpowers/plans/2026-04-28-m2c-orm-lifecycle.md)

---

## 一句话索引

- 看 `M2` 入口： [M2 文档索引](/home/x/src/install/php/First/docs/superpowers/specs/2026-04-18-m2-web-data-index.md)
- 看 Web 状态基础： [M2-A Web 状态基础](/home/x/src/install/php/First/docs/superpowers/specs/2026-04-18-m2a-web-state-foundation-spec.md)
- 看身份与输入链路： [M2-B 身份与输入链路](/home/x/src/install/php/First/docs/superpowers/specs/2026-04-18-m2b-identity-and-input-spec.md)
- 看 ORM 生命周期： [M2-C ORM 生命周期](/home/x/src/install/php/First/docs/superpowers/specs/2026-04-18-m2c-orm-lifecycle-spec.md)
- 复核身份链路： [M2-B 身份与输入链路 plan](/home/x/src/install/php/First/docs/superpowers/plans/2026-04-28-m2b-identity-and-input.md)
- 复核 ORM 生命周期： [M2-C ORM 生命周期 plan](/home/x/src/install/php/First/docs/superpowers/plans/2026-04-28-m2c-orm-lifecycle.md)
