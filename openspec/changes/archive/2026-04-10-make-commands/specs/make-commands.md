# make-commands Spec

## MakeCommand Abstract Base

### Methods
- `execute(): int` — 主流程：验证名称 → 检查文件 → 渲染 stub → 写入
- `getTargetPath(string $name): string` — 返回目标文件绝对路径
- `getStubFile(): string` — 返回 stub 文件名（不含目录）
- `getReplacements(string $name): array` — 返回 `['{{ placeholder }}' => value]` 映射
- `validateName(string $name): void` — 验证类名格式，不合法抛异常
- `renderStub(string $stubFile, array $replacements): string` — 加载 stub + 替换占位符
- `resolveStubPath(string $stub): string` — 项目 stubs/ 优先，否则 bin/Console/Stubs/

### Name Validation
- 类名：`/^[A-Z][a-zA-Z0-9]*$/`（PascalCase）
- 路径支持：`make:model Blog/Post` → `app/Model/Blog/Post.php`

## make:model

```
php command make:model Post
php command make:model Post --no-migration
```

- 创建 `app/Model/{Name}.php`，继承 `Bin\Database\Model`
- 默认创建对应 migration：`database/migrations/YYYY_MM_DD_HHMMSS_create_{table}_table.php`
- 表名从模型名自动推导（Post → posts）
- `--no-migration` 跳过 migration 创建

## make:controller

```
php command make:controller PostController
php command make:controller PostController --resource
php command make:controller PostController --api
php command make:controller PostController --invokable
```

- 创建 `app/Controllers/{Name}.php`
- `--resource` — 7 个 CRUD 方法
- `--api` — 5 个 API CRUD 方法（无 create/edit）
- `--invokable` — `__invoke` 方法
- 默认 — 空 Controller

## make:middleware

```
php command make:middleware AuthMiddleware
```

- 创建 `app/Middleware/{Name}.php`
- 继承 `Bin\Middleware\Middleware`
- 包含 `handle()` 方法模板

## make:migration

```
php command make:migration create_posts_table
php command make:migration add_status_to_posts --table=posts
php command make:migration create_users_table --create=users
```

- 创建 `database/migrations/YYYY_MM_DD_HHMMSS_{name}.php`
- 文件名前缀为当前时间戳
- `--create` — Schema::create 模板
- `--table` — Schema::table 模板
- 无选项 — 空 migration

## make:command

```
php command make:command SendEmails
```

- 创建 `app/Console/Commands/{Name}.php`
- 继承 `Bin\Console\Command`
- 包含 `$signature`, `$description`, `execute()` 模板

## make:request

```
php command make:request StorePostRequest
```

- 创建 `app/Requests/{Name}.php`
- 继承 `Bin\Validation\FormRequest`
- 包含 `authorize()`, `rules()`, `messages()` 模板

## make:factory

```
php command make:factory PostFactory
```

- 创建 `database/factories/{Name}.php`
- 包含 `define()` 模板

## make:policy

```
php command make:policy PostPolicy
```

- 创建 `app/Policies/{Name}.php`
- 继承 `Bin\Auth\Policy`
- 包含 `view()`, `create()`, `update()`, `delete()` 方法模板

## make:observer

```
php command make:observer PostObserver
```

- 创建 `app/Observers/{Name}.php`
- 继承 `Bin\Database\Observer`
- 包含 `created()`, `updated()`, `deleted()` 方法模板
