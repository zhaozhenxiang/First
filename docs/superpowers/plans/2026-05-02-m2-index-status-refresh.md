# M2 Index Status Refresh Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Update the `M2` documentation index so it reflects the existing M2 implementation plans instead of saying implementation appendices are absent.

**Architecture:** Keep `docs/superpowers/specs/README.md` as the short top-level entry and keep detailed M2 navigation in `docs/superpowers/specs/2026-04-18-m2-web-data-index.md`. Add explicit links from the M2 index to the already-created `M2-A`, `M2-B`, and `M2-C` implementation plans, and keep phase execution order unchanged.

**Tech Stack:** Markdown docs, existing `docs/superpowers/specs` and `docs/superpowers/plans` conventions, `sed` / `rg` / `git diff` verification.

---

## Context and Constraints

- Code-review-graph MCP resources and templates were unavailable when this plan was written: `list_mcp_resources` returned `[]` and `list_mcp_resource_templates` returned `[]`.
- `graphify-out/GRAPH_REPORT.md` was checked before local document reads. The graph corpus exists and covers this repository, but the requested change is documentation-only.
- Do not create a replacement for `docs/superpowers/plans/2026-04-18-m2-doc-split.md`; that plan is complete and already covers creating the M2 index plus split specs.
- Do not create new M2 implementation plans for `M2-A`, `M2-B`, or `M2-C`; these already exist.
- This plan changes Markdown docs only. No PHP code, tests, runtime configuration, or graphify update is required.

## File Structure

- Modify: `docs/superpowers/specs/2026-04-18-m2-web-data-index.md`
  Responsibility: canonical M2 navigation, current phase status, spec reading order, and links to existing implementation plans.
- Modify: `docs/superpowers/specs/README.md`
  Responsibility: top-level specs entry, one-line status that routes readers into the M2 index without duplicating detailed plan state.

---

### Task 1: Refresh the M2 Index Current Status

**Files:**
- Modify: `docs/superpowers/specs/2026-04-18-m2-web-data-index.md`

- [ ] **Step 1: Replace the stale current-status block**

In `docs/superpowers/specs/2026-04-18-m2-web-data-index.md`, replace this block:

```md
当前状态：

- `M1` 已于 `2026-04-18` 完成验收并关账
- `M2` 已拆分为 `M2-A / M2-B / M2-C`
- 当前只建立规划入口，实施附录还没有展开为 `tasks / order / tests / closure`
```

with this block:

```md
当前状态：

- `M1` 已于 `2026-04-18` 完成验收并关账
- `M2` 已拆分为 `M2-A / M2-B / M2-C`
- `M2` 文档拆分 plan 已完成，索引和三份子 spec 已落地
- `M2-A` Web 状态基础 plan 已完成
- `M2-B` 身份与输入链路 plan 已建立，是当前身份链路执行入口
- `M2-C` ORM 生命周期 plan 已建立，是后续数据层执行入口
```

- [ ] **Step 2: Add an implementation-plan entry section**

In `docs/superpowers/specs/2026-04-18-m2-web-data-index.md`, insert this section after the `阅读顺序` section and its explanatory bullets, before the existing `---` separator that precedes `## 文档用途`:

```md
---

## 实施计划入口

如果目标是执行而不是只阅读 spec，按下面顺序进入 plan：

1. [M2 文档拆分 plan](/home/x/src/install/php/First/docs/superpowers/plans/2026-04-18-m2-doc-split.md) - 已完成，用于追溯索引和三份子 spec 的拆分来源
2. [M2-A Web 状态基础 plan](/home/x/src/install/php/First/docs/superpowers/plans/2026-04-18-m2a-web-state-foundation.md) - 已完成，用于追溯 `Session / Cookie / CSRF` 的实施闭环
3. [M2-B 身份与输入链路 plan](/home/x/src/install/php/First/docs/superpowers/plans/2026-04-28-m2b-identity-and-input.md) - 当前身份链路执行入口
4. [M2-C ORM 生命周期 plan](/home/x/src/install/php/First/docs/superpowers/plans/2026-04-28-m2c-orm-lifecycle.md) - 数据层执行入口，排在 `M2-B` 之后
```

- [ ] **Step 3: Update the planning-role guidance**

In the `如果你是做阶段规划，可以这样看：` subsection of `docs/superpowers/specs/2026-04-18-m2-web-data-index.md`, replace this bullet:

```md
- 再决定是否要为某一段补 `tasks / order / tests`
```

with these bullets:

```md
- 再看 `实施计划入口`，确认对应 plan 是否已经存在
- 如果继续推进实施，优先执行 `M2-B`，再进入 `M2-C`
```

- [ ] **Step 4: Update the current recommended entry**

In the `## 当前推荐入口` section of `docs/superpowers/specs/2026-04-18-m2-web-data-index.md`, replace this introductory sentence:

```md
如果现在要从 `M1` 进入下一阶段，建议从这里开始：
```

with this sentence:

```md
如果现在要从 `M1` 进入或继续推进 `M2`，建议从这里开始：
```

Then replace the numbered list in that section with:

```md
1. 先读 [M1 Closure](/home/x/src/install/php/First/docs/superpowers/specs/2026-04-18-m1-runtime-skeleton-closure.md)，确认 `M1` 不再继续扩写
2. 再读 [M2-A Web 状态基础](/home/x/src/install/php/First/docs/superpowers/specs/2026-04-18-m2a-web-state-foundation-spec.md)，把已完成的 Web 状态基础作为后续依赖背景
3. 如果执行身份链路，进入 [M2-B 身份与输入链路 plan](/home/x/src/install/php/First/docs/superpowers/plans/2026-04-28-m2b-identity-and-input.md)
4. 如果执行数据层，确认 `M2-B` 已完成后再进入 [M2-C ORM 生命周期 plan](/home/x/src/install/php/First/docs/superpowers/plans/2026-04-28-m2c-orm-lifecycle.md)
```

- [ ] **Step 5: Update the one-line index links**

In the `## 一句话索引` section of `docs/superpowers/specs/2026-04-18-m2-web-data-index.md`, append these bullets after the existing spec bullets:

```md
- 执行身份链路： [M2-B 身份与输入链路 plan](/home/x/src/install/php/First/docs/superpowers/plans/2026-04-28-m2b-identity-and-input.md)
- 执行 ORM 生命周期： [M2-C ORM 生命周期 plan](/home/x/src/install/php/First/docs/superpowers/plans/2026-04-28-m2c-orm-lifecycle.md)
```

- [ ] **Step 6: Verify the stale status wording is gone**

Run:

```bash
rg -n "当前只建立规划入口|实施附录还没有展开|再决定是否要为某一段补" docs/superpowers/specs/2026-04-18-m2-web-data-index.md
```

Expected: no matches.

- [ ] **Step 7: Verify the new plan links are present**

Run:

```bash
rg -n "2026-04-18-m2-doc-split|2026-04-18-m2a-web-state-foundation|2026-04-28-m2b-identity-and-input|2026-04-28-m2c-orm-lifecycle" docs/superpowers/specs/2026-04-18-m2-web-data-index.md
```

Expected: matches for all four plan filenames.

### Task 2: Keep the Specs README Aligned

**Files:**
- Modify: `docs/superpowers/specs/README.md`

- [ ] **Step 1: Replace the top-level status sentence**

In `docs/superpowers/specs/README.md`, replace this sentence:

```md
当前状态：`M1` 已于 `2026-04-18` 关账；下一阶段入口为 [M2 文档索引](/home/x/src/install/php/First/docs/superpowers/specs/2026-04-18-m2-web-data-index.md)，建议按 `M2-A -> M2-B -> M2-C` 阅读。
```

with this sentence:

```md
当前状态：`M1` 已于 `2026-04-18` 关账；`M2` 已拆分为 `M2-A -> M2-B -> M2-C`，阶段 spec 与实施 plan 入口统一从 [M2 文档索引](/home/x/src/install/php/First/docs/superpowers/specs/2026-04-18-m2-web-data-index.md) 进入。
```

- [ ] **Step 2: Verify README still stays concise**

Run:

```bash
sed -n '1,80p' docs/superpowers/specs/README.md
```

Expected: the file still contains only the `# Specs` heading, the three spec links, and one current-status sentence.

### Task 3: Verify and Commit the Documentation Refresh

**Files:**
- Modify: `docs/superpowers/specs/2026-04-18-m2-web-data-index.md`
- Modify: `docs/superpowers/specs/README.md`

- [ ] **Step 1: Inspect the updated M2 index**

Run:

```bash
sed -n '1,260p' docs/superpowers/specs/2026-04-18-m2-web-data-index.md
```

Expected: output includes `当前状态`, `阅读顺序`, `实施计划入口`, `文档用途`, `按角色阅读`, `当前推荐入口`, and `一句话索引`.

- [ ] **Step 2: Check Markdown headings for the touched docs**

Run:

```bash
rg -n "^#|^## " docs/superpowers/specs/README.md docs/superpowers/specs/2026-04-18-m2-web-data-index.md
```

Expected: `README.md` has only `# Specs`; the M2 index has one `# M2 文档索引` heading and section headings for `说明`, `阅读顺序`, `实施计划入口`, `文档用途`, `按角色阅读`, `当前推荐入口`, and `一句话索引`.

- [ ] **Step 3: Check for placeholder language**

Run:

```bash
rg -n "TB[D]|TO[D]O|待[定]|占[位]|implement late[r]|fill in detail[s]" docs/superpowers/specs/README.md docs/superpowers/specs/2026-04-18-m2-web-data-index.md
```

Expected: no matches.

- [ ] **Step 4: Inspect the final diff**

Run:

```bash
git diff -- docs/superpowers/specs/README.md docs/superpowers/specs/2026-04-18-m2-web-data-index.md
```

Expected: diff only updates current M2 documentation status, adds links to existing plans, and keeps spec reading order intact.

- [ ] **Step 5: Commit the documentation refresh**

Run:

```bash
git add docs/superpowers/specs/README.md docs/superpowers/specs/2026-04-18-m2-web-data-index.md
git commit -m "docs: refresh M2 index plan status"
```

Expected: commit succeeds with only the two touched spec docs staged.

## Self-Review

- Spec coverage: covers the stale current-status line in the M2 index, routes readers to existing M2 plans, preserves `M2-A -> M2-B -> M2-C` order, and keeps the specs README as a short entry point.
- Placeholder scan: the plan avoids the red-flag placeholder phrases from the writing-plans skill.
- Type and path consistency: all referenced plan/spec filenames match the repository paths checked while writing this plan.
