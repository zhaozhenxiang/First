## ADDED Requirements

### Requirement: EnvLoader 解析 .env 文件
`Bin\Config\EnvLoader` SHALL 提供静态方法 `load(string $path): void`，读取指定 `.env` 文件并将键值对注入 `$_ENV` 和 `putenv()`。

#### Scenario: 加载标准 .env 文件
- **WHEN** 调用 `EnvLoader::load('/path/to/.env')` 且文件内容为 `DB_HOST=127.0.0.1`
- **THEN** `$_ENV['DB_HOST']` SHALL 等于 `'127.0.0.1'`，`getenv('DB_HOST')` SHALL 返回 `'127.0.0.1'`

#### Scenario: 文件不存在时静默跳过
- **WHEN** 调用 `EnvLoader::load('/path/to/.env')` 且文件不存在
- **THEN** SHALL 不抛异常，`$_ENV` 不受影响

#### Scenario: 支持注释和空行
- **WHEN** .env 文件包含 `# 这是注释` 和空行
- **THEN** 注释行和空行 SHALL 被跳过

#### Scenario: 支持引号包裹值
- **WHEN** .env 文件包含 `APP_NAME="My App"` 和 `DB_PASS='secret'`
- **THEN** 值 SHALL 去除引号，`$_ENV['APP_NAME']` 等于 `'My App'`

#### Scenario: 支持 export 前缀
- **WHEN** .env 文件包含 `export DB_HOST=localhost`
- **THEN** `$_ENV['DB_HOST']` SHALL 等于 `'localhost'`

#### Scenario: 已存在的环境变量不被覆盖
- **WHEN** `$_ENV['DB_HOST']` 已存在且 .env 文件也定义了 `DB_HOST`
- **THEN** 已有值 SHALL 保持不变（.env 中的定义被跳过）

### Requirement: EnvLoader 在 autoload 中自动调用
`bin/autoload.php` SHALL 在加载 helpers.php 之后调用 `EnvLoader::load(BASE_PATH . '/.env')`。

#### Scenario: 框架启动时 .env 自动加载
- **WHEN** 框架通过 `public/index.php` 启动
- **THEN** `.env` 文件 SHALL 在 config 文件被读取之前完成加载，`env()` 函数可用
