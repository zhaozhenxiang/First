# M2 文档拆分设计

## 背景

当前 `docs/superpowers/specs/README.md` 只显式索引了 `M1` 文档。

`M2` 仍然只以一段总述存在于 [2026-04-16 M1 Runtime Skeleton Spec](/home/x/src/install/php/First/docs/superpowers/specs/2026-04-16-m1-runtime-skeleton-spec.md) 中：

- `Session`
- `Cookie`
- `CSRF`
- `Auth`
- `Validation`
- `ORM`

这会带来两个问题：

1. `M2` 目标面过大，后续实施时缺少清晰入口。
2. `README` 无法把 `M2` 的依赖顺序和阅读顺序表达清楚。

## 目标

这次只解决文档组织问题，不直接扩写实现计划。

目标是：

1. 把 `M2` 从单段总述拆成按依赖顺序推进的 3 份独立 spec。
2. 新增一个 `M2` 索引页，承担入口与导航职责。
3. 让 `README.md` 只指向 `M2` 索引，而不是直接堆叠子 spec 链接。
4. 保持与 `M1` 文档体系一致的阅读体验，方便后续继续补 `tasks / order / tests / closure`。

## 方案选择

讨论过 3 种组织方式：

### 方案 A

只修改 `README.md`，把 `M2` 拆成 3 条入口，不新增独立文档。

问题：

- 信息仍然散落在旧 spec 中
- 后续无法自然扩展 `M2` 附录体系

### 方案 B

新增一个 `M2` 索引页，再拆成 3 份独立 spec，由 `README` 只指向索引页。

这是本次选定方案，因为它同时满足：

- 入口稳定
- 结构清楚
- 易于后续扩展
- 与 `M1` 的组织习惯一致

### 方案 C

保留一个 `M2 master spec`，再挂 3 份子 spec。

问题：

- 总 spec 与子 spec 容易重复
- 后续维护成本更高

## 拆分原则

`M2` 按依赖顺序拆分，而不是按能力重要性拆分。

顺序固定为：

1. `M2-A Web 状态基础`
2. `M2-B 身份与输入链路`
3. `M2-C ORM 生命周期`

原因是：

- `Auth` 依赖前面的 Session / Cookie / CSRF 运行时基础
- `Validation` 和 `FormRequest` 需要与认证、中间件、异常输出一起描述
- `ORM` 放在最后，可以减少它与前面 Web 主链路整理交叉返工

## 文档结构

本次新增 4 个文件：

- `docs/superpowers/specs/2026-04-18-m2-web-data-index.md`
- `docs/superpowers/specs/2026-04-18-m2a-web-state-foundation-spec.md`
- `docs/superpowers/specs/2026-04-18-m2b-identity-and-input-spec.md`
- `docs/superpowers/specs/2026-04-18-m2c-orm-lifecycle-spec.md`

同时更新：

- `docs/superpowers/specs/README.md`

## 文档职责

## 1. M2 文档索引

索引页只负责导航，不重复子 spec 的细节。

需要包含：

- `说明`
- `阅读顺序`
- `文档用途`
- `按角色阅读`
- `当前推荐入口`
- `一句话索引`

需要明确当前状态：

- `M1` 已于 `2026-04-18` 关账
- `M2` 已拆分为 `M2-A / M2-B / M2-C`
- 当前只建立规划入口，尚未补 `tasks / order / tests / closure`

## 2. M2-A Web 状态基础

只讨论：

- `Session`
- `Cookie`
- `CSRF`

文档章节：

- `目标`
- `为什么先做这一段`
- `范围`
- `Out of Scope`
- `运行时主链路`
- `设计原则`
- `交付物`
- `验收标准`

主链路必须清楚描述：

- 请求进入
- Session 启动 / 读取
- Cookie 参与状态恢复
- CSRF 校验介入
- 响应写回 Session / Cookie

## 3. M2-B 身份与输入链路

只讨论：

- `Validation`
- `FormRequest`
- `Auth`

文档章节：

- `目标`
- `为什么排在 M2-A 之后`
- `范围`
- `Out of Scope`
- `输入与身份主链路`
- `设计原则`
- `交付物`
- `验收标准`

主链路必须清楚描述：

- `Request`
- `Validation / FormRequest`
- `Auth guard / user resolve`
- `Controller`
- `Exception / Response` 统一出口

## 4. M2-C ORM 生命周期

只讨论：

- `ORM`
- 常用数据访问体验

文档章节：

- `目标`
- `为什么最后做`
- `范围`
- `Out of Scope`
- `数据访问主链路`
- `设计原则`
- `交付物`
- `验收标准`

主链路必须清楚描述：

- Model / query builder 创建
- 查询执行
- hydration / persistence
- 与上层异常和返回值链路衔接

## README 入口策略

`README.md` 保持短入口，不直接罗列 3 个子 spec。

最终入口为：

- `2026-04-16 M1 Runtime Skeleton Index`
- `2026-04-18 M1 Runtime Skeleton Closure`
- `2026-04-18 M2 Web/Data Planning Index`

状态文案更新为：

`M1` 已于 `2026-04-18` 关账；下一阶段入口为 `M2` 索引，建议按 `M2-A -> M2-B -> M2-C` 阅读。

## 不在本次范围内

本次不新增：

- `M2 tasks`
- `M2 order`
- `M2 tests`
- `M2 closure`
- 代码实现计划

这些内容应在后续 `M2` 真正进入实施准备时再补。

## 验收标准

本次文档拆分完成后，应满足：

1. `README.md` 能把用户稳定引导到 `M2` 索引页。
2. `M2` 索引页能清楚表达三阶段顺序和各自用途。
3. 3 份子 spec 的边界不重叠，且依赖关系明确。
4. 后续如果继续推进 `M2`，可以在当前结构上自然补齐附录文档，而不需要重做入口。
