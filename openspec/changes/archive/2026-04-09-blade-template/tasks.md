# Tasks: blade-template

## Phase 1: 核心编译器

- [x] 新增 BladeCompiler — 编译 {{ }}/{!! !!}和核心指令 (@if/@foreach/@for/@while/@switch/@isset/@empty)
- [x] 编译缓存机制 — storage/views/ 缓存目录 + 文件修改时间检查
- [x] View::make() 集成 — 优先查找 .blade.php，fallback 到 .php

## Phase 2: 模板继承

- [x] @extends/@section/@yield — 布局继承和区块系统
- [x] @include — 子视图包含

## Phase 3: 便捷指令 + 栈

- [x] 安全指令 — @csrf/@method
- [x] 栈系统 — @stack/@push/@prepend
- [x] 认证指令 — @auth/@guest

## Phase 4: 测试

- [x] BladeCompilerTest — 编译输出、继承、指令、缓存全覆盖
