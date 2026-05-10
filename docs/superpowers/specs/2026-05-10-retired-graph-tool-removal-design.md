# Retired Graph Tool Removal Design

Date: 2026-05-10
Status: Approved for full cleanup by user selection

## Goal

Remove the retired graph-intelligence tool from the active project workflow and
clean its visible references from project documentation, including historical
plan documents.

## Scope

- Remove the retired tool instruction block from `AGENTS.md`.
- Delete project-local index directories for the retired tool.
- Delete the user-level registry/config directory for the retired tool.
- Replace tool-specific references in project documentation so current docs no
  longer instruct contributors to use that tool.

## Non-Goals

- Do not remove graphify instructions or graphify artifacts.
- Do not remove the existing code-review-graph MCP instructions.
- Do not change PHP application behavior.
- Do not rewrite unrelated historical content beyond removing retired-tool
  references.

## Approach

Use targeted documentation edits and explicit directory deletion. For historical
plans, replace retired-tool prerequisites with neutral wording that points to
available local context and tests. Preserve the intent of the plans while
removing the unavailable tool dependency.

## Validation

- Search the repository for retired-tool names and command prefixes; no matches
  should remain.
- Confirm retired-tool project-local and user-level directories are absent.
- Review `git status --short` to ensure changes are limited to this cleanup.

## Risks

Deleting the user-level registry/config directory affects all repositories for
this user account, not only this workspace. This is intentional because the
requested scope is full removal.
