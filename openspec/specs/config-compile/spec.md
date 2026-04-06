## ADDED Requirements

### Requirement: config:cache 编译命令
系统 SHALL 提供 CLI 命令 `php command config:cache`，将 `config/*.php` 逐文件编译为 `storage/config/*.php`。

#### Scenario: 编译所有配置文件
- **WHEN** 执行 `php command config:cache` 且 `config/` 目录有 `app.php`、`database.php`、`auth.php`
- **THEN** SHALL 在 `storage/config/` 目录生成 `app.php`、`database.php`、`auth.php`，每个文件直接 `return` 纯数组（`env()` 调用已解析为实际值）

#### Scenario: 编译产物格式
- **WHEN** `config/database.php` 内容为 `return ['default' => env('DB_CONNECTION', 'mysql')]` 且 `.env` 中 `DB_CONNECTION=sqlite`
- **THEN** `storage/config/database.php` SHALL 内容为 `return ['default' => 'sqlite']`，不含 `env()` 调用

#### Scenario: storage/config 目录不存在时自动创建
- **WHEN** `storage/config/` 目录不存在且执行编译命令
- **THEN** SHALL 自动创建 `storage/config/` 目录

#### Scenario: config:clear 清除编译缓存
- **WHEN** 执行 `php command config:clear`
- **THEN** SHALL 删除 `storage/config/` 目录下所有编译产物

### Requirement: ConfigRepository 优先读取编译产物
`ConfigRepository::load()` SHALL 优先从 `storage/config/` 加载，若文件不存在则 fallback 到 `config/`。

#### Scenario: 编译产物存在时使用编译版本
- **WHEN** `storage/config/database.php` 存在
- **THEN** `config('database.default')` SHALL 从 `storage/config/database.php` 读取

#### Scenario: 编译产物不存在时 fallback 到源文件
- **WHEN** `storage/config/database.php` 不存在但 `config/database.php` 存在
- **THEN** `config('database.default')` SHALL 从 `config/database.php` 读取

### Requirement: ConfigRepository 分隔符统一为点号
`ConfigRepository::parseKey()` SHALL 使用 `.` 作为唯一分隔符，`config('file.key')` 格式。

#### Scenario: 点号分隔正常解析
- **WHEN** 调用 `config('database.connections.mysql.host')`
- **THEN** SHALL 从 `database` 配置文件中读取 `connections.mysql.host` 的值
