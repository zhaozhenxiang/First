# make-commands Design

## Architecture

采用 **Generator 模式**：抽象基类 `MakeCommand` 提取公共逻辑（名称验证、目录创建、文件存在检查、Stub 渲染），各具体命令只需定义 stub 模板和目标路径。

```
Bin\Console\Command (existing base)
  └── Bin\Console\Commands\MakeCommand (NEW abstract)
        ├── MakeModelCommand
        ├── MakeControllerCommand
        ├── MakeMiddlewareCommand
        ├── MakeMigrationCommand
        ├── MakeCommandCommand
        ├── MakeRequestCommand (refactor existing)
        ├── MakeFactoryCommand
        ├── MakePolicyCommand
        └── MakeObserverCommand
```

## Key Decisions

### 1. Abstract MakeCommand base

公共逻辑：
- `validateName()` — 验证类名格式
- `ensureDirectory()` — 创建目标目录
- `fileExists()` — 检查文件是否已存在
- `writeFile()` — 写入文件 + 输出成功信息
- `getStubPath()` — 获取 stub 文件路径（支持自定义 stubs 目录）

子类实现：
- `getTargetPath(string $name): string` — 目标文件路径
- `getStubFile(): string` — stub 文件名
- `getReplacements(string $name): array` — stub 变量替换

### 2. Stub 系统

Stub 模板放在 `bin/Console/Stubs/` 目录，使用 `{{ placeholder }}` 占位符：

```
bin/Console/Stubs/
  model.stub
  controller.stub
  controller.api.stub
  controller.invokable.stub
  middleware.stub
  migration.create.stub
  migration.update.stub
  command.stub
  request.stub
  factory.stub
  policy.stub
  observer.stub
```

用户可在 `stubs/` 目录覆盖默认 stub（项目级优先）。

### 3. make:model 特殊行为

`make:model Post` 默认同时创建 Migration（可通过 `--no-migration` 跳过）。
内部调用 `MakeMigrationCommand::createForModel()`。

### 4. make:controller 选项

- `--resource` — 生成 CRUD 方法 (index/create/store/show/edit/update/destroy)
- `--api` — 生成 API CRUD 方法 (index/store/show/update/destroy)
- `--invokable` — 生成 __invoke 单一方法
- 无选项 — 生成空 Controller

### 5. make:migration 选项

- `--create=table` — 创建表迁移
- `--table=table` — 修改表迁移
- 无选项 — 空迁移

### 6. 输出目录约定

| 命令 | 默认目录 |
|------|---------|
| make:model | `app/Model/` |
| make:controller | `app/Controllers/` |
| make:middleware | `app/Middleware/` |
| make:migration | `database/migrations/` |
| make:command | `app/Console/Commands/` |
| make:request | `app/Requests/` |
| make:factory | `database/factories/` |
| make:policy | `app/Policies/` |
| make:observer | `app/Observers/` |

## Files to Create/Modify

### New Files
1. `bin/Console/Commands/MakeCommand.php` — abstract base
2. `bin/Console/Commands/MakeModelCommand.php`
3. `bin/Console/Commands/MakeControllerCommand.php`
4. `bin/Console/Commands/MakeMiddlewareCommand.php`
5. `bin/Console/Commands/MakeMigrationCommand.php`
6. `bin/Console/Commands/MakeCommandCommand.php`
7. `bin/Console/Commands/MakeFactoryCommand.php`
8. `bin/Console/Commands/MakePolicyCommand.php`
9. `bin/Console/Commands/MakeObserverCommand.php`
10. `bin/Console/Stubs/*.stub` — 11 stub templates
11. `tests/MakeCommandsTest.php`

### Modified Files
1. `bin/Console/Commands/MakeRequestCommand.php` — 重构为继承 MakeCommand
2. `bin/Console/Commands/MakeSeederCommand.php` — 重构为继承 MakeCommand (可选)
