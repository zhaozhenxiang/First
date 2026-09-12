# 数据库迁移系统使用指南

迁移系统提供了版本控制数据库架构的功能，类似于 Laravel 的 Migration。

## 目录

- [基础用法](#基础用法)
- [创建迁移](#创建迁移)
- [运行迁移](#运行迁移)
- [回滚迁移](#回滚迁移)
- [Schema Builder](#schema-builder)
- [列类型](#列类型)
- [索引](#索引)
- [外键](#外键)
- [修改表](#修改表)

## 基础用法

### 创建迁移

```bash
# 创建空白迁移
php bin/Database/Migrations/MigrateCommand.php make:create_users_table

# 创建指定表的迁移
php bin/Database/Migrations/MigrateCommand.php make:create_posts_table posts
```

### 运行迁移

```bash
# 运行所有待执行的迁移
php bin/Database/Migrations/MigrateCommand.php migrate
```

### 回滚迁移

```bash
# 回滚最后一次迁移
php bin/Database/Migrations/MigrateCommand.php rollback

# 回滚所有迁移
php bin/Database/Migrations/MigrateCommand.php reset

# 回滚并重新运行
php bin/Database/Migrations/MigrateCommand.php refresh

# 删除所有表并重新运行
php bin/Database/Migrations/MigrateCommand.php fresh
```

### 查看状态

```bash
php bin/Database/Migrations/MigrateCommand.php status
```

## 创建迁移

迁移文件存放在 `database/migrations/` 目录下。

### 空白迁移

```php
<?php

use Bin\Database\Migrations\Migration;
use Bin\Database\Schema\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 创建表
        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // 回滚
        Schema::dropIfExists('users');
    }
};
```

## 运行迁移

### 命令行

```bash
php bin/Database/Migrations/MigrateCommand.php migrate
```

### 代码中使用

```php
use Bin\Database\Migrations\Migrator;

$migrator = new Migrator(basePath('/database/migrations'));
$migrator->run();
```

## 回滚迁移

### 回滚最后一次

```bash
php bin/Database/Migrations/MigrateCommand.php rollback
```

### 回滚指定步数

```bash
php bin/Database/Migrations/MigrateCommand.php rollback:step 3
```

### 回滚所有

```bash
php bin/Database/Migrations/MigrateCommand.php reset
```

### 刷新迁移

```bash
# 回滚并重新运行
php bin/Database/Migrations/MigrateCommand.php refresh

# 删除所有表并重新运行
php bin/Database/Migrations/MigrateCommand.php fresh
```

## Schema Builder

### 创建表

```php
use Bin\Database\Schema\Schema;

Schema::create('users', function ($table) {
    $table->id();
    $table->string('name');
    $table->string('email');
    $table->timestamps();
});
```

### 修改表

```php
Schema::table('users', function ($table) {
    $table->string('phone')->nullable();
    $table->text('bio')->after('email');
});
```

### 删除表

```php
Schema::dropIfExists('users');
```

### 重命名表

```php
Schema::rename('users', 'accounts');
```

### 检查表/列是否存在

```php
if (Schema::hasTable('users')) {
    //
}

if (Schema::hasColumn('users', 'email')) {
    //
}
```

## 列类型

### 数值类型

```php
// 自增主键
$table->id();
$table->bigIncrements('id');
$table->increments('id');
$table->tinyIncrements('id');
$table->smallIncrements('id');
$table->mediumIncrements('id');

// 普通整数
$table->bigInteger('votes');
$table->integer('votes');
$table->tinyInteger('votes');
$table->smallInteger('votes');
$table->mediumInteger('votes');

// 无符号整数
$table->unsignedBigInteger('user_id');
$table->unsignedInteger('votes');
$table->unsignedTinyInteger('status');

// 小数
$table->decimal('amount', 8, 2);
$table->unsignedDecimal('amount', 8, 2);
$table->double('amount', 8, 2);
$table->float('amount', 8, 2);
```

### 字符串类型

```php
$table->string('name', 100);       // VARCHAR(100)
$table->text('description');        // TEXT
$table->longText('content');        // LONGTEXT
$table->mediumText('content');      // MEDIUMTEXT
$table->tinyText('note');           // TINYTEXT
```

### 日期时间类型

```php
$table->date('birth_date');
$table->dateTime('created_at');
$table->dateTimeTz('created_at');
$table->time('open_time');
$table->timeTz('open_time');
$table->timestamp('created_at');
$table->timestampTz('created_at');
$table->timestamps();               // created_at, updated_at
$table->timestampsTz();
$table->year('birth_year');
```

### 其他类型

```php
$table->boolean('is_admin');        // TINYINT(1)
$table->enum('status', ['pending', 'active', 'inactive']);
$table->set('options', ['option1', 'option2']);
$table->json('settings');
$table->jsonb('settings');
$table->uuid('id');
$table->ipAddress('visitor');
$table->macAddress('device');
```

### 修改列

```php
Schema::table('users', function ($table) {
    // 添加列
    $table->string('phone')->nullable();
    $table->text('bio')->after('email');

    // 修改列
    $table->string('name', 100)->change();

    // 重命名列
    $table->renameColumn('from', 'to');

    // 删除列
    $table->dropColumn('phone');
    $table->dropColumn(['phone', 'bio']);
});
```

## 列修饰符

```php
$table->string('name')->nullable();           // 可为空
$table->string('name')->default('John');      // 默认值
$table->integer('votes')->unsigned();         // 无符号
$table->bigInteger('user_id')->autoIncrement(); // 自增
$table->string('email')->unique();            // 唯一
$table->string('name')->first();              // 第一位
$table->string('email')->after('name');       // 在某列之后
$table->string('name')->comment('用户名');    // 注释
$table->timestamp('created_at')->useCurrent();  // 使用当前时间
$table->timestamp('updated_at')->useCurrentOnUpdate();
```

## 索引

### 创建索引

```php
Schema::table('users', function ($table) {
    // 主键
    $table->primary('id');
    $table->primary(['first_name', 'last_name']);

    // 唯一索引
    $table->unique('email');
    $table->unique(['email', 'company_id'], 'unique_email_company');

    // 普通索引
    $table->index('state');
    $table->index(['email', 'state'], 'index_email_state');

    // 全文索引
    $table->fullText('body');
    $table->fullText(['title', 'body']);

    // 空间索引
    $table->spatialIndex('location');
});
```

### 删除索引

```php
Schema::table('users', function ($table) {
    $table->dropPrimary('users_id_primary');
    $table->dropUnique('users_email_unique');
    $table->dropIndex('users_state_index');
    $table->dropFullText('posts_body_fulltext');
    $table->dropSpatialIndex('places_location_spatialindex');

    // drop 系列也接受列数组，按 {表}_{列}_{类型} 规则推导索引名
    $table->dropIndex(['state', 'city']);        // users_state_city_index
    $table->dropUnique('email');                 // users_email_unique
});
```

> 2026-09 阶段10 前：`dropFullText`/`dropSpatialIndex` 曾在本文档记载但未实现；`fullText()`/`spatialIndex()` 编译分支缺失（静默空操作）——均已修复。

## 修改列

```php
// 流式：修改已存在的列（编译为 ALTER TABLE ... MODIFY COLUMN）
Schema::table('users', function ($table) {
    $table->string('name', 100)->nullable()->change();
    $table->integer('age')->unsigned()->default(0)->change();
});

// 命令式：等价写法
Schema::table('users', function ($table) {
    $table->modifyColumn('name', 'string', ['length' => 100, 'nullable' => true]);
});
```

注意：MySQL 的 MODIFY COLUMN 需要完整列定义，未指定的默认值会被移除（MySQL 方言语义，与 Laravel 一致）。

## 多态列

```php
Schema::create('comments', function ($table) {
    $table->id();
    // commentable_type + commentable_id（unsignedBigInteger）+ 联合索引
    $table->morphs('commentable');
    // 可空版本
    $table->nullableMorphs('taggable');
    // UUID 外键版本（commentable_id 为 CHAR(36)）
    $table->uuidMorphs('notable');
    // remember_token VARCHAR(100) NULL
    $table->rememberToken();
});
```

## 外键

### 创建外键

```php
Schema::table('posts', function ($table) {
    // 方法一：使用 foreignId
    $table->foreignId('user_id')
        ->constrained()
        ->onUpdate('cascade')
        ->onDelete('cascade');

    // 方法二：使用 foreign
    $table->unsignedBigInteger('user_id');
    $table->foreign('user_id')
        ->references('id')
        ->on('users')
        ->onDelete('cascade');

    // 方法三：使用 foreignKey 命令
    $table->unsignedBigInteger('user_id');
    $table->foreignKey(
        'user_id',
        'posts_user_id_foreign',
        'users',
        'id',
        'cascade',
        'cascade'
    );
});
```

### 删除外键

```php
Schema::table('posts', function ($table) {
    $table->dropForeign('posts_user_id_foreign');
    $table->dropForeign(['user_id']);
});
```

## 时间戳

### 自动时间戳

```php
Schema::table('users', function ($table) {
    // 添加 created_at 和 updated_at
    $table->timestamps();

    // 添加软删除
    $table->softDeletes();

    // 添加时间戳带时区
    $table->timestampsTz();

    // 添加软删除带时区
    $table->softDeletesTz();
});
```

### 软删除

```php
Schema::table('users', function ($table) {
    $table->softDeletes();              // deleted_at
    $table->softDeletesTz();            // deleted_at with timezone
});

// 使用软删除的模型
$users = User::withTrashed()->get();
$users = User::onlyTrashed()->get();
$user->restore();
$user->forceDelete();
```

## 存储引擎

```php
Schema::create('users', function ($table) {
    $table->engine = 'InnoDB';
    $table->charset = 'utf8mb4';
    $table->collation = 'utf8mb4_unicode_ci';

    $table->id();
    $table->string('name');
    $table->timestamps();
});
```

## 完整示例

### 用户表

```php
Schema::create('users', function ($table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->timestamp('email_verified_at')->nullable();
    $table->string('password');
    $table->rememberToken();
    $table->timestamps();
    $table->softDeletes();
});
```

### 文章表

```php
Schema::create('posts', function ($table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('title');
    $table->string('slug')->unique();
    $table->text('content')->nullable();
    $table->boolean('published')->default(false);
    $table->timestamp('published_at')->nullable();
    $table->timestamps();
    $table->softDeletes();

    $table->index(['user_id', 'published']);
    $table->fullText(['title', 'content']);
});
```

### 多对多关系表

```php
Schema::create('role_user', function ($table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('role_id')->constrained()->cascadeOnDelete();
    $table->timestamps();
});
```

## 编程方式使用

```php
use Bin\Database\Migrations\Migrator;
use Bin\Database\Schema\Schema;

// 运行迁移
$migrator = new Migrator(basePath('/database/migrations'));
$migrator->run();

// 回滚迁移
$migrator->rollback();
$migrator->reset();
$migrator->refresh();

// 检查状态
$files = $migrator->getMigrationFiles();
$ran = $migrator->getRanMigrations();
```

## 事务支持

```php
use Bin\Database\Schema\Schema;

Schema::transaction(function () {
    Schema::create('users', function ($table) {
        $table->id();
        $table->string('name');
    });

    Schema::create('posts', function ($table) {
        $table->id();
        $table->string('title');
    });
});
```

## 注意事项

1. **迁移文件命名**: 使用描述性名称，如 `create_users_table`
2. **回滚**: 确保每个迁移都有正确的 `down()` 方法
3. **外键**: 外键列需要先创建，再添加约束
4. **索引**: 不要过度索引，只对经常查询的列添加索引
5. **数据安全**: 使用 `fresh` 命令会删除所有数据，生产环境慎用
