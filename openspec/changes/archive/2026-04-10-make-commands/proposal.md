## Why

当前 `make:` 命令仅有 `make:seeder`。Laravel 的 `make:` 命令族是日常开发的高频工具：创建 Model、Controller、Middleware、Migration、Command、Request 等。缺少这些命令意味着每次都要手动创建文件、写命名空间、写样板代码。

## What Changes

- 新增 `make:model` — 创建 Model + 对应 Migration
- 新增 `make:controller` — 创建 Controller (支持 --resource / --api / --invokable)
- 新增 `make:middleware` — 创建 Middleware
- 新增 `make:migration` — 创建 Migration (支持 --create/--table)
- 新增 `make:command` — 创建 Console Command
- 新增 `make:request` — 创建 FormRequest
- 新增 `make:factory` — 创建 Model Factory
- 新增 `make:policy` — 创建 Policy
- 新增 `make:observer` — 创建 Observer
- 所有命令支持 stub 模板，可自定义

## Capabilities

### New Capabilities
- `make-commands`: make 命令族 (model/controller/middleware/migration/command/request/factory/policy/observer)
- `stub-templates`: Stub 模板系统，支持自定义模板

### Modified Capabilities

## Impact

- `bin/Console/Commands/MakeModelCommand.php` — 新增
- `bin/Console/Commands/MakeControllerCommand.php` — 新增
- `bin/Console/Commands/MakeMiddlewareCommand.php` — 新增
- `bin/Console/Commands/MakeMigrationCommand.php` — 新增
- `bin/Console/Commands/MakeCommandCommand.php` — 新增
- `bin/Console/Commands/MakeRequestCommand.php` — 新增
- `bin/Console/Commands/MakeFactoryCommand.php` — 新增
- `bin/Console/Commands/MakePolicyCommand.php` — 新增
- `bin/Console/Commands/MakeObserverCommand.php` — 新增
- `bin/Console/Stubs/` — 新增，stub 模板文件
- `bin/Console/Kernel.php` — 注册新命令
- `tests/MakeCommandsTest.php` — 新增测试
