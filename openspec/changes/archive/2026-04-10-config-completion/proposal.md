## Why

当前 `config/` 仅有 3 个配置文件 (app.php, auth.php, database.php)。缓存、Session、日志、哈希等模块的行为全部硬编码，无法通过配置文件调整。Laravel 有 20+ 配置文件覆盖所有基础设施。本 change 为现有模块补齐配置，使行为可配置化。

## What Changes

- 新增 `config/cache.php` — 缓存驱动、前缀、TTL 配置
- 新增 `config/session.php` — Session 驱动、生命周期、域名配置
- 新增 `config/logging.php` — 日志通道 (single/daily/syslog/errorlog) 配置
- 新增 `config/hashing.php` — 哈希算法和轮次配置
- 新增 `config/cors.php` — CORS 策略配置
- 新增 `config/app.php` — 补充 providers/aliases/fallback_locale 等
- 各 Manager 类从 config 读取配置替代硬编码默认值

## Capabilities

### New Capabilities
- `cache-config`: 缓存模块配置文件
- `session-config`: Session 模块配置文件
- `logging-config`: 日志模块配置文件
- `hashing-config`: 哈希模块配置文件
- `cors-config`: CORS 策略配置文件

### Modified Capabilities
- `config-files`: 补充 app.php 中缺失的配置项

## Impact

- `config/cache.php` — 新增
- `config/session.php` — 新增
- `config/logging.php` — 新增
- `config/hashing.php` — 新增
- `config/cors.php` — 新增
- `config/app.php` — 补充 providers/aliases/timezone/locale/fallback_locale/encryption_key
- `bin/Cache/CacheManager.php` — 从 config 读取驱动配置
- `bin/Session/SessionManager.php` — 从 config 读取驱动配置
- `bin/Log/LogManager.php` — 从 config 读取通道配置
- `bin/Auth/HashManager.php` — 从 config 读取算法配置
- `tests/ConfigCompletionTest.php` — 新增测试
