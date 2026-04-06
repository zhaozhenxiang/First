## Context

框架当前只有一个 `config/db.php`，配置读取通过 `ConfigRepository` 实现。现有问题：
- 分隔符不一致：`parseKey()` 只处理 `.`，但代码中混用 `:` 和 `.`
- 没有 `.env` 支持，敏感信息硬编码
- 没有 config 编译机制，每次请求都要扫描文件

已有的基础设施：
- `env()` 辅助函数（读取 `$_ENV`，支持 bool/null 转换）
- `ConfigRepository`（支持 `get/set/has/save/load`，基于文件名.键名）
- `bin/Console/Kernel.php` CLI 命令系统
- `bin/autoload.php` 启动加载

## Goals / Non-Goals

**Goals:**
- `.env` 文件在框架启动最早期加载，`env()` 函数可用
- config 源文件（`config/*.php`）可使用 `env()` 读取环境变量
- 编译命令逐文件生成 `storage/config/*.php`，值为纯数组（env 已解析）
- 运行时 `config()` 优先读 `storage/config/`，不存在则 fallback 到 `config/`
- 统一分隔符为 `.`，所有现有调用修正

**Non-Goals:**
- 不做 `.env` 文件热重载（运行时修改 `.env` 不生效）
- 不做 config 文件变更监听（编译需手动触发命令）
- 不做 config 加密
- 不重构 `ConfigCache` 类

## Decisions

### 1. `.env` 加载方式

**选择**：自实现轻量解析器，不引入依赖

`.env` 格式简单（KEY=VALUE），框架不需要 vlucas/phpdotenv 的全部功能。自实现约 50 行代码，支持：
- `#` 注释
- 空行跳过
- 引号包裹值（`KEY="value"`）
- `export KEY=value` 格式
- 同时写入 `$_ENV` 和 `putenv()`

位置：`bin/Config/EnvLoader.php`，静态方法 `load(string $path): void`

### 2. config 编译策略

**选择**：逐文件 1:1 编译（非合并为单文件）

`config/app.php` → `storage/config/app.php`，每个文件独立。理由：
- 按需加载，不需要的 config 文件不读
- 文件名即 namespace，与 `parseKey` 天然对应
- 编译产物可读性好，方便调试

编译流程：`require config/file.php`（此时 `env()` 可用）→ 拿到数组 → `var_export` 写入 `storage/config/file.php`

### 3. 运行时加载优先级

**选择**：`storage/config/` > `config/`

```
config('database.default')
  → 检查 storage/config/database.php 是否存在
  → 存在：require storage/config/database.php
  → 不存在：require config/database.php
```

这与 Laravel 生产环境行为一致：编译缓存优先。

### 4. 分隔符统一

**选择**：统一用 `.`，修正所有现有调用

| 旧调用 | 新调用 |
|--------|--------|
| `config('db:driver')` | `config('database.default')` |
| `config('db:resultType')` | `config('database.resultType')` |
| `config('db:connection:' . $driver)` | `config('database.connections.' . $driver)` |
| `config('app:debug')` | `config('app.debug')` |

`parseKey()` 无需改动，已经用 `.`。只需修正调用方和 config 文件结构。

### 5. config 文件命名

**选择**：`db.php` → `database.php`，对齐 Laravel 命名

新增文件：
- `config/app.php` — app.name, app.url, app.debug, app.timezone, app.env
- `config/database.php` — database.default, database.connections.mysql.*
- `config/auth.php` — auth.provider, auth.guards

### 6. autoload 加载顺序

```
autoload.php:
  1. define BASE_PATH, APP_PATH
  2. spl_autoload_register
  3. require helpers.php        ← env() 和 config() 可用
  4. EnvLoader::load('.env')    ← .env 加载到 $_ENV
  5. routes.php（仅 web 请求）
```

`env()` 在 helpers.php 中定义，helpers 在 EnvLoader 之前加载，所以 `env()` 在 `.env` 加载后就可用。

## Risks / Trade-offs

- [编译产物可能过时] → `config:cache` 命令文档提示每次修改 config/ 后重新编译
- [`db.php` → `database.php` 是 breaking change] → 全局搜索替换所有 `config('db.` 和 `config('db:` 调用
- [`.env` 文件泄露风险] → `.gitignore` 中添加 `.env`，提供 `.env.example`
