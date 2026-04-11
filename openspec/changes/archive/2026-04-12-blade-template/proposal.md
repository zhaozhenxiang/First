## Why

当前 View 系统是纯 PHP `extract()` + `require_once`，没有任何模板引擎特性。没有布局继承、没有区块、没有组件、没有模板指令、没有 CSRF 隐藏域、没有模板缓存。这导致每个页面必须完全自包含 HTML，无法复用布局，视图层代码冗余严重。Laravel 的 Blade 引擎是开发者体验的核心组成部分。

## What Changes

- 新增 Blade 风格模板编译器，编译 `.blade.php` 为纯 PHP 并缓存
- 支持核心 Blade 指令：`@extends`/`@section`/`@yield`/`@include`/`@if`/`@foreach`/`@for`/`@while`/`@switch`
- 支持安全输出 `{{ }}` (自动转义) 和原始输出 `{!! !!}`
- 支持便捷指令：`@csrf`/`@method`/`@auth`/`@guest`/`@error`/`@stack`/`@push`
- 支持组件系统 `@component`/`@slot` 和匿名 Blade 组件
- 支持模板继承和区块覆盖
- 编译产物缓存到 `storage/views/`

## Capabilities

### New Capabilities
- `blade-compiler`: Blade 模板编译引擎，`.blade.php` → PHP + 缓存
- `blade-inheritance`: 模板继承 (@extends/@section/@yield) 和区块覆盖
- `blade-directives`: 内置指令集 (@if/@foreach/@for/@while/@switch/@isset/@empty)
- `blade-components`: 组件系统 (@component/@slot) + 匿名组件
- `blade-stacks`: 栈系统 (@stack/@push/@prepend)
- `blade-security`: 安全指令 (@csrf/@method) 和自动转义 {{ }}

### Modified Capabilities
- `view-engine`: View::make() 优先查找 .blade.php，fallback 到 .php

## Impact

- `bin/View/Compiler.php` — 重写为 Blade 编译器
- `bin/View/View.php` — 增加 Blade 文件查找逻辑
- `bin/View/Blade/` — 新增目录
  - `BladeCompiler.php` — 核心编译器
  - `BladeDirective.php` — 指令注册和管理
  - `Component.php` — 组件基类
  - `AnonymousComponent.php` — 匿名组件
- `storage/views/` — 新增编译缓存目录
- `views/` — 现有视图文件可迁移为 `.blade.php`
- `tests/BladeCompilerTest.php` — 新增测试
