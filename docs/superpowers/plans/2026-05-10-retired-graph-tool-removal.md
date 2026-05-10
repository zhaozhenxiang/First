# Retired Graph Tool Removal Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remove the retired graph-intelligence tool from active project and user workflow without touching graphify or code-review-graph.

**Architecture:** The cleanup is configuration and documentation hygiene only. Project files should keep the active `code-review-graph` MCP and `graphify` instructions, while removing retired-tool command permissions and deleting user-level retired-tool hook/skill directories after explicit approval. This plan uses bracket expressions such as `git[n]exus` in commands so the plan can match real retired-tool paths without reintroducing the retired tool's exact search token into project docs.

**Tech Stack:** Markdown docs, Claude/Codex local configuration, shell verification commands, git.

---

## File Structure

- Create: `docs/superpowers/plans/2026-05-10-retired-graph-tool-removal.md`
  - Documents the exact cleanup procedure for the approved design.
- Modify outside git: `/home/x/src/install/php/First/.claude/settings.local.json`
  - Remove only the retired-tool `npx` allow-list entry. This file is ignored by the user's global gitignore and is not committed.
- Check only: `AGENTS.md`
  - Keep `code-review-graph` MCP and `graphify` instructions intact.
- Check only: `CLAUDE.md`
  - Keep `graphify` instructions intact.
- Delete with explicit approval: `/home/x/.claude/hooks/git[n]exus`
  - User-level retired-tool hook directory.
- Delete with explicit approval:
  - `/home/x/.config/opencode/skill/git[n]exus-cli`
  - `/home/x/.config/opencode/skill/git[n]exus-debugging`
  - `/home/x/.config/opencode/skill/git[n]exus-exploring`
  - `/home/x/.config/opencode/skill/git[n]exus-guide`
  - `/home/x/.config/opencode/skill/git[n]exus-impact-analysis`
  - `/home/x/.config/opencode/skill/git[n]exus-pr-review`
  - `/home/x/.config/opencode/skill/git[n]exus-refactoring`

### Task 1: Remove Project Retired-Tool Permission

**Files:**
- Modify outside git: `/home/x/src/install/php/First/.claude/settings.local.json`

- [ ] **Step 1: Confirm tracked project files are already clear**

Run:

```bash
rg -n "Git[N]exus|git[n]exus|git[n]exus_|git[n]exus://|npx git[n]exus|\\.git[n]exus" . -g '!graphify-out/**' -g '!vendor/**' -g '!.git/**' -g '!.worktrees/**'
```

Expected: no matches.

- [ ] **Step 2: Confirm the ignored local project config still has the retired permission**

Run:

```bash
rg -n "Git[N]exus|git[n]exus|git[n]exus_|git[n]exus://|npx git[n]exus|\\.git[n]exus" /home/x/src/install/php/First/.claude/settings.local.json
```

Expected: one match for the retired-tool `npx` permission entry. If the file is absent or the command returns no matches, this local config is already clean.

- [ ] **Step 3: Remove the permission entry**

Edit `/home/x/src/install/php/First/.claude/settings.local.json` by deleting this JSON string from the `permissions.allow` array:

```json
"Bash(npx git[n]exus:*)",
```

The JSON file contains the literal form of that permission entry; delete the corresponding real string from the array. Do not remove any `code-review-graph`, `graphify`, or non-retired-tool permissions.

- [ ] **Step 4: Validate JSON syntax**

Run:

```bash
php -r 'json_decode(file_get_contents("/home/x/src/install/php/First/.claude/settings.local.json"), true, flags: JSON_THROW_ON_ERROR); echo "json ok\n";'
```

Expected:

```text
json ok
```

- [ ] **Step 5: Confirm the retired-tool token is absent from tracked project files and ignored local config**

Run:

```bash
rg -n "Git[N]exus|git[n]exus|git[n]exus_|git[n]exus://|npx git[n]exus|\\.git[n]exus" . -g '!graphify-out/**' -g '!vendor/**' -g '!.git/**' -g '!.worktrees/**'
rg -n "Git[N]exus|git[n]exus|git[n]exus_|git[n]exus://|npx git[n]exus|\\.git[n]exus" /home/x/src/install/php/First/.claude/settings.local.json
```

Expected: both commands return no matches.

- [ ] **Step 6: Commit the plan document**

Run:

```bash
git add docs/superpowers/plans/2026-05-10-retired-graph-tool-removal.md
git commit -m "docs: remove retired graph tool references"
```

Expected: commit succeeds with only the plan document changed. The ignored local config remains outside git.

### Task 2: Remove User-Level Retired-Tool Directories

**Files:**
- Delete with explicit approval: `/home/x/.claude/hooks/git[n]exus`
- Delete with explicit approval: `/home/x/.config/opencode/skill/git[n]exus-*`

- [ ] **Step 1: Confirm user-level retired-tool directories exist**

Run:

```bash
find /home/x/.claude /home/x/.config/opencode -maxdepth 4 \( -iname '*git[n]exus*' -o -path '*/git[n]exus*' \) -print
```

Expected before cleanup:

```text
/home/x/.claude/hooks/git[n]exus
/home/x/.config/opencode/skill/git[n]exus-refactoring
/home/x/.config/opencode/skill/git[n]exus-cli
/home/x/.config/opencode/skill/git[n]exus-exploring
/home/x/.config/opencode/skill/git[n]exus-pr-review
/home/x/.config/opencode/skill/git[n]exus-guide
/home/x/.config/opencode/skill/git[n]exus-impact-analysis
/home/x/.config/opencode/skill/git[n]exus-debugging
```

- [ ] **Step 2: Request destructive-action approval**

Ask the user for approval to delete the listed user-level directories. Do not delete them without approval.

- [ ] **Step 3: Delete the approved user-level directories**

Run after approval:

```bash
rm -rf /home/x/.claude/hooks/git[n]exus \
  /home/x/.config/opencode/skill/git[n]exus-refactoring \
  /home/x/.config/opencode/skill/git[n]exus-cli \
  /home/x/.config/opencode/skill/git[n]exus-exploring \
  /home/x/.config/opencode/skill/git[n]exus-pr-review \
  /home/x/.config/opencode/skill/git[n]exus-guide \
  /home/x/.config/opencode/skill/git[n]exus-impact-analysis \
  /home/x/.config/opencode/skill/git[n]exus-debugging
```

Expected: command exits 0.

- [ ] **Step 4: Confirm user-level retired-tool directories are absent**

Run:

```bash
find /home/x/.claude /home/x/.config/opencode -maxdepth 4 \( -iname '*git[n]exus*' -o -path '*/git[n]exus*' \) -print
```

Expected: no matches.

### Task 3: Final Validation

**Files:**
- Check: project tree
- Check: user-level retired-tool paths

- [ ] **Step 1: Confirm active graph tools remain documented**

Run:

```bash
rg -n "code-review-graph|graphify" AGENTS.md CLAUDE.md docs/superpowers/specs/2026-05-10-retired-graph-tool-removal-design.md
```

Expected: matches remain for `code-review-graph` and `graphify`.

- [ ] **Step 2: Confirm no project-local retired-tool directory exists**

Run:

```bash
find . -maxdepth 5 \( -iname '*git[n]exus*' -o -path './.git[n]exus' \) -print
```

Expected: no matches.

- [ ] **Step 3: Check git status**

Run:

```bash
git status --short
```

Expected: clean worktree after the commit.

- [ ] **Step 4: Run full tests**

Run:

```bash
php test
```

Expected: test runner exits 0. Existing PHP 8.5 deprecation warnings may appear.

- [ ] **Step 5: Finish the branch**

Use `superpowers:finishing-a-development-branch` and present merge/PR/keep/discard options.
