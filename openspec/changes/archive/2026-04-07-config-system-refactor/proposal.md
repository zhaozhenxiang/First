## Why

当前框架只有一个 `config/db.php` 配置文件，配置散落在代码各处且分隔符不一致（混用 `:` 和 `.`）。缺少 `.env` 文件加载机制，敏感信息（数据库密码等）硬编码在配置文件中。需要参考 Laravel 的配置架构，建立完整的 config 管理体系。

## What Changes

- 新增 `.env` 文件解析器，启动时加载到 `$_ENV`，配合已有 `env()` 辅助函数使用
- 整理 `config/` 目录：`db.php` → `database.php`，新增 `app.php`、`auth.php` 等标准配置文件
- 配置文件中通过 `env()` 读取环境变量，保持源文件可移植
- 新增编译命令 `php command config:cache`，将 `config/*.php` 逐文件编译为 `storage/config/*.php` 静态数组
- **BREAKING** `ConfigRepository` 运行时优先从 `storage/config/` 读取，fallback 到 `config/` 原始文件
- **BREAKING** 统一分隔符：所有 `config('key:sub')` 改为 `config('key.sub')`，`parseKey` 仅支持 `.`
- `autoload.php` 加载顺序调整：`.env` → helpers → config 编译

## Capabilities

### New Capabilities
- `env-loader`: `.env` 文件解析与加载，将键值对注入 `$_ENV` 和 `putenv()`
- `config-compile`: config 编译命令，逐文件将 `config/*.php`（含 `env()` 调用）编译为 `storage/config/*.php`（纯数组）
- `config-files`: 标准 config 文件定义（app、database、auth 等），统一键名和结构

### Modified Capabilities

## Impact

- `bin/Config/ConfigRepository.php` — 加载路径改为 `storage/config/`，fallback `config/`，`parseKey` 分隔符修正
- `bin/Func/helpers.php` — `env()` 已存在，无需改动；`config()` helper 可能微调
- `bin/autoload.php` — 新增 `.env` 加载步骤
- `bin/Model/Model.php` — `config('db:driver')` → `config('database.default')`，键名对齐
- `bin/Exception/Handler.php` — `config('app:debug')` → `config('app.debug')`
- `bin/Http/UploadedFile.php` — 已用 `.`，键名对齐
- `bin/Auth/AuthManager.php` — 已用 `.`，键名对齐
- `bin/Console/Kernel.php` — 注册 `config:cache` 命令
- `config/` — 文件重组
- `storage/config/` — 新建目录，存放编译产物
