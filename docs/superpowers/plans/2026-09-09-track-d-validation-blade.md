# Track D Validation & Blade Breadth Implementation Plan

> **For agentic workers:** Steps use checkbox (`- [ ]`) syntax for tracking. Implement task-by-task with tests green after each task.

**Goal:** Close the developer-facing Track D gaps from `docs/superpowers/specs/2026-05-31-laravel13-architecture-gap-spec.md`: fix the unknown-rule silent-pass bug, implement the six missing validation rules (`accepted`, `unique`, `exists`, `required_if`, `required_with`, `exclude`), wire validation messages through the localization subsystem, and build the Blade component subsystem (`@component`/`@slot`, anonymous `<x-*>` components, attribute bags, class merging).

**Architecture:** ValidationManager keeps its current instantiation surface (`new` / `make()` from 5 call sites — no constructor changes). DB-backed rules (`unique`/`exists`) resolve the PDO lazily via `ConnectionManager::getConnection()` at rule-execution time. Localization is opt-in: messages resolve through a static translator hook when one is registered (container binding or explicit `setTranslator()`), falling back to the built-in Chinese defaults otherwise — existing message assertions stay green. Blade components compile to a stack-based runtime (`ComponentFactory` + `ComponentAttributeBag`) driven by output buffering, mirroring how the existing compiler already composes passes (sections → includes → stacks → directives → components).

**Tech Stack:** PHP 8.3+, `php test` runner, `Bin\Testing\TestCase`, graphify.

---

## Scope

In scope:

- Unknown string rules fail loudly (exception naming the rule) instead of silently passing.
- `exclude` removes the field from validated data.
- `accepted`, `required_if`, `required_with`, `unique`, `exists` rules with messages and `Rule` builder methods.
- Validation messages resolve via `Translator` (`validation.*` keys) when a translator is available; full `en`/`zh` message files shipped.
- `@component` / `@slot` / `@endslot` / `@endcomponent` directives with `$slot` and `$slots`.
- Anonymous `<x-name>` components (paired and self-closing) with static and `:bound` attributes.
- `ComponentAttributeBag` with `merge()` class merging.
- Component views participate in compiled-cache dependency invalidation.

Out of scope:

- Class-based components (PHP component classes with render logic).
- Blade fragments, `@props`, forwarded attributes directives.
- Track E driver work and Track F AI/MCP.

## Current State (verified 2026-09-09)

- `ValidationManager::validateRule()` falls through to `return true` for unknown rules; `Rule::exclude()` compiles to a string that no handler processes (silent no-op).
- `$ruleMethods` covers 36 rules; missing: accepted, unique, exists, required_if, required_with, exclude.
- Messages are hardcoded Chinese in `$defaultMessages`; `lang/en/validation.php` has 3 keys; `lang/zh/` exists but has no validation.php; nothing in `bin/Validation/` touches `bin/Localization/`.
- `Translator` is a static singleton (`getInstance`/`resetInstance`) with dot-path `get()` that returns the key itself when missing.
- `ConnectionManager::getConnection(): PDO` is the global DB entry (static).
- BladeCompiler (479 lines) runs passes: inheritance → sections → includes → stacks → directives; `readWithDependencies()` powers cache invalidation for extends/include; no component code exists.

## File Structure

- Modify: `bin/Validation/ValidationManager.php` — unknown-rule failure, 6 new rules, translator-aware message resolution, exclude handling.
- Modify: `bin/Validation/Rule.php` — `accepted()`, `requiredIf()`, `requiredWith()`, `unique()`, `exists()`.
- Create: `lang/zh/validation.php`, rewrite `lang/en/validation.php` — full message sets.
- Create: `bin/View/ComponentFactory.php` — stack-based component/slot runtime.
- Create: `bin/View/ComponentAttributeBag.php` — attribute bag with `merge()`/class merging.
- Modify: `bin/View/Blade/BladeCompiler.php` — `compileComponents()` pass, `<x-*>` tag compilation, dependency scan for component views.
- Tests: extend `tests/ValidationTest.php` (rules + unknown-rule), create `tests/ValidationLocalizationTest.php`, `tests/BladeComponentTest.php`.

## Tasks

### Task 1 — D-0 Unknown rules fail loudly + `exclude`

- [x] `validateRule()` throws `InvalidArgumentException("Validation rule [{$rule}] is not supported.")` for unknown non-custom rules.
- [x] `exclude` rule: field removed from validated output; works via string and `Rule::exclude()`.
- [x] Tests: unknown rule throws with rule name in message; exclude removes field; exclude + other rules still validates before removal.

### Task 2 — D-1 Five missing rules

- [x] `accepted`: value in `yes/on/1/true` (loose).
- [x] `required_if:other,v1,v2`: required when `data[other]` equals any listed value; passes when empty and condition unmet.
- [x] `required_with:f1,f2`: required when any listed field is present and non-empty.
- [x] `unique:table,column,except,idColumn`: COUNT query via `ConnectionManager::getConnection()`; ignores the except id; skips empty values.
- [x] `exists:table,column`: COUNT > 0; skips empty values.
- [x] `Rule` builder methods for all five; default + localized messages.
- [x] Tests: each rule pass/fail cases; DB rules against in-memory SQLite via `ConnectionManager::setConnection()`.

### Task 3 — D-2 Validation message localization

- [x] `ValidationManager::setTranslator()` static hook (opt-in; no constructor changes).
- [x] `addError()` resolution order: `customMessages["field.rule"]` → `customMessages[rule]` → translator `validation.{rule}` → built-in defaults → generic fallback.
- [x] Full `lang/en/validation.php` + `lang/zh/validation.php` message sets including new rules.
- [x] Placeholder replacement (`:attribute`, `:min`, `:values`, …) unchanged for translated lines.
- [x] Tests: with translator + en locale messages are English; without translator messages remain the built-in Chinese; `zh` round-trips.

### Task 4 — D-3 Blade components

- [x] `ComponentFactory`: `startComponent(view, data)`, `slot(name)`, `endSlot()`, `renderComponent()` via output buffering; `$slot` + `$slots` in component view.
- [x] `ComponentAttributeBag`: array access, `get()`, `merge()` with class merging, `__toString`, `only()`/`except()`.
- [x] `@component('name', [...])` / `@slot('title')` / `@endslot` / `@endcomponent` compilation (innermost-first for nesting).
- [x] Anonymous `<x-alert type="error" :message="$msg">...</x-alert>` and self-closing `<x-icon name="check"/>`: attributes parsed (static + `:` bound + bare), passed as `$attributes` bag and snake-case variables; component view resolved under `components/` directory.
- [x] Compiled-cache dependency tracking includes component views (extends the include/extend dependency scan).
- [x] Tests: component render with data, named slots, default slot, attribute bag merge, bound attributes, self-closing tags, nested components, cache invalidation on component change.

### Task 5 — Verification

- [x] `php test` green (2107 passed vs. 2057 baseline; +50 new tests).
- [x] `graphify update .`
- [x] Status note in `docs/superpowers/specs/2026-05-31-laravel13-architecture-gap-spec.md` Track D section.

## Implementation Notes

- Attribute bags with quotes must be echoed raw (`{!! $attributes->merge([...]) !!}`); the compiler's `{{ }}` escapes everything (no HtmlString exemption like Laravel).
- Named slots are trimmed like the default slot (First semantics; the compiler is whitespace-naive).
- Class-based components (PHP classes with `render()`) and `@props` remain future work.

## Verification

- New tests fail before each task's implementation and pass after.
- Existing validation message assertions (Chinese) stay green without a registered translator.
- Full suite green after every task.
