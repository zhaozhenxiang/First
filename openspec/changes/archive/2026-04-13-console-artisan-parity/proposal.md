## Why

当前项目已经有命令注册、自动发现、别名、`make:*` 命令和程序化调用，但 Console 运行时仍主要是“命令调度器”，距离 Laravel 13 的 Artisan 还有一层差距：它还没有完全接入统一应用生命周期，也缺少更完整的命令定义和交互约定。

## What Changes

- 引入更明确的 Console Kernel
- 统一命令发现、注册、生命周期 bootstrapping
- 规范命令签名、交互、程序化调用与容器注入行为
- 为 closure command 或等价轻量命令定义留出扩展位
- 让 Console 与 HTTP 运行时共享同一应用装配基础

## Capabilities

### New Capabilities
- `console-runtime`: 基于 Console Kernel 的命令运行时
- `command-discovery-parity`: 更稳定的命令发现与注册行为
- `command-injection`: 命令执行时的容器注入和应用上下文接入

### Modified Capabilities
- `artisan-like-kernel`: 现有 `Kernel` 从调度器扩展为完整控制台运行时
- `make-commands-runtime`: `make:*` 命令运行在统一 Console 生命周期中

## Impact

- `bin/Console/Kernel.php` / `bin/Console/Command.php` / `bin/Console/Input.php` / `bin/Console/Output.php`
- CLI 入口文件与应用启动逻辑
- `bin/Console/Commands/*` — 命令实例化和执行路径调整
- `tests/ConsoleTest.php` / `MakeCommandsTest.php`
