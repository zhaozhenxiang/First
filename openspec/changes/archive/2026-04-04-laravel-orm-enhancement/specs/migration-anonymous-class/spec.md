## ADDED Requirements

### Requirement: Migrator supports anonymous class migration files
Migrator SHALL 支持使用匿名类的 migration 文件（`return new class extends Migration`），无需通过类名解析。

#### Scenario: 运行匿名类 migration
- **WHEN** `database/migrations/` 中的文件使用 `return new class extends Migration { public function up() { ... } }`
- **AND** 调用 `$migrator->run()`
- **THEN** migration 的 `up()` 方法被执行
- **AND** migration 文件名被记录到 migrations 表

#### Scenario: 回滚匿名类 migration
- **WHEN** 调用 `$migrator->rollback(1)`
- **THEN** 最近一批 migration 的 `down()` 方法被执行
- **AND** 对应记录从 migrations 表删除

### Requirement: Migration table tracks filenames
migrations 数据库表 SHALL 记录 migration 文件名（不含路径和扩展名），而非类名。

#### Scenario: 记录格式
- **WHEN** migration 文件名为 `2026_04_03_000000_create_posts_table.php`
- **THEN** migrations 表的 `migration` 列值为 `2026_04_03_000000_create_posts_table`

### Requirement: MigrationCreator generates anonymous classes
MigrationCreator 生成的 stub 文件 SHALL 使用匿名类语法，与 Migrator 的解析方式兼容。

#### Scenario: 生成的 stub 格式
- **WHEN** 调用 `MigrationCreator::create('create_posts_table')`
- **THEN** 生成的文件包含 `return new class extends Migration`
- **AND** 文件包含 `up()` 和 `down()` 方法
