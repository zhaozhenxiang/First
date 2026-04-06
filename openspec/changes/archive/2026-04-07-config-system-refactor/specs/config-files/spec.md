## ADDED Requirements

### Requirement: config/app.php 应用配置
系统 SHALL 提供 `config/app.php` 配置文件，包含应用基础设置。

#### Scenario: app.php 结构
- **WHEN** 读取 `config/app.php`
- **THEN** SHALL 返回包含以下键的数组：
  - `name` — 应用名称，通过 `env('APP_NAME', 'First')` 读取
  - `env` — 环境标识，通过 `env('APP_ENV', 'production')` 读取
  - `debug` — 调试模式，通过 `env('APP_DEBUG', false)` 读取
  - `url` — 应用 URL，通过 `env('APP_URL', 'http://localhost')` 读取
  - `timezone` — 时区，默认 `'UTC'`

### Requirement: config/database.php 数据库配置
系统 SHALL 提供 `config/database.php` 替代现有 `config/db.php`。

#### Scenario: database.php 结构
- **WHEN** 读取 `config/database.php`
- **THEN** SHALL 返回包含以下键的数组：
  - `default` — 默认驱动，通过 `env('DB_CONNECTION', 'mysql')` 读取
  - `resultType` — PDO fetch 模式，默认 `PDO::FETCH_ASSOC`
  - `connections` — 连接配置数组，`mysql` 键包含 `host`、`port`、`user`、`pass`、`dbname`，均通过 `env()` 读取

#### Scenario: 兼容现有 Model 调用
- **WHEN** `Model::getConfig()` 调用 `config('database.connections.' . $driver)`
- **THEN** SHALL 返回对应驱动的连接配置数组

### Requirement: config/auth.php 认证配置
系统 SHALL 提供 `config/auth.php` 认证配置文件。

#### Scenario: auth.php 结构
- **WHEN** 读取 `config/auth.php`
- **THEN** SHALL 返回包含 `provider` 键的数组，默认值为 `'App\\Model\\User'`

### Requirement: 现有调用全部分隔符修正
所有使用 `:` 分隔符的 config 调用 SHALL 修正为 `.` 分隔符，且键名与新的 config 文件结构对齐。

#### Scenario: Model.php 调用修正
- **WHEN** `Model::getConfig()` 运行
- **THEN** SHALL 使用 `config('database.default')`、`config('database.resultType')`、`config('database.connections.' . $driver)`

#### Scenario: Handler.php 调用修正
- **WHEN** `Handler::shouldShowDebug()` 运行
- **THEN** SHALL 使用 `config('app.debug', false)`

### Requirement: 提供 .env.example 文件
项目根目录 SHALL 提供 `.env.example` 文件作为环境变量模板。

#### Scenario: .env.example 包含所有必需变量
- **WHEN** 查看 `.env.example`
- **THEN** SHALL 包含 `APP_NAME`、`APP_ENV`、`APP_DEBUG`、`APP_URL`、`DB_CONNECTION`、`DB_HOST`、`DB_PORT`、`DB_DATABASE`、`DB_USERNAME`、`DB_PASSWORD` 等变量及默认值
