## 1. EnvLoader 实现

- [x] 1.1 创建 `bin/Config/EnvLoader.php`，实现静态 `load(string $path): void`，支持注释、空行、引号、export 前缀，写入 `$_ENV` + `putenv()`，已有变量不覆盖
- [x] 1.2 修改 `bin/autoload.php`：在 require helpers.php 之后调用 `EnvLoader::load(BASE_PATH . '/.env')`
- [x] 1.3 创建 `.env.example` 模板文件，包含 APP_* 和 DB_* 变量
- [x] 1.4 确认 `.gitignore` 包含 `.env`

## 2. Config 文件整理

- [x] 2.1 创建 `config/app.php`：name、env、debug、url、timezone，使用 `env()` 读取
- [x] 2.2 将 `config/db.php` 重构为 `config/database.php`：default、resultType、connections（mysql 配置全部用 env()），删除旧 `db.php`
- [x] 2.3 创建 `config/auth.php`：provider 等认证相关配置

## 3. ConfigRepository 改造

- [x] 3.1 修改 `ConfigRepository::load()`：优先读 `storage/config/{file}.php`，fallback 到 `config/{file}.php`
- [x] 3.2 确认 `parseKey()` 仅使用 `.` 分隔符（已实现，无需改动）

## 4. 编译命令

- [x] 4.1 创建 `bin/Console/Commands/ConfigCacheCommand.php`：扫描 `config/*.php`，逐文件 require 并 var_export 写入 `storage/config/*.php`
- [x] 4.2 创建 `bin/Console/Commands/ConfigClearCommand.php`：删除 `storage/config/` 下所有文件
- [x] 4.3 在 `bin/Console/Kernel.php` 注册 `config:cache` 和 `config:clear` 命令

## 5. 调用方修正

- [x] 5.1 修改 `bin/Model/Model.php`：`db:driver` → `database.default`，`db:resultType` → `database.resultType`，`db:connection:` → `database.connections.`
- [x] 5.2 修改 `bin/Exception/Handler.php`：`app:debug` → `app.debug`
- [x] 5.3 修改 `bin/Http/UploadedFile.php`：`app.url` 键名对齐新 config 结构
- [x] 5.4 修改 `bin/Auth/AuthManager.php`：`auth.provider` 键名对齐新 config 结构
- [x] 5.5 全局搜索其他 `config('` 调用，确保无遗漏的冒号分隔符

## 6. 测试

- [x] 6.1 为 `EnvLoader` 编写单元测试（解析各种 .env 格式、文件不存在、不覆盖已有变量）
- [x] 6.2 为 config 编译/清除命令编写测试
- [x] 6.3 为 `ConfigRepository` fallback 机制编写测试
- [x] 6.4 运行全量测试确认无回归
