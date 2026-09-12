# ORM 使用指南

这是一个类似 Laravel Eloquent 的 ORM 实现，提供简洁优雅的数据库操作接口。

## 目录

- [基础用法](#基础用法)
- [查询构建器](#查询构建器)
- [模型定义](#模型定义)
- [CRUD 操作](#crud-操作)
- [关系](#关系)
- [集合](#集合)
- [渴望加载](#渴望加载)

## 基础用法

```php
use Bin\Database\Model;

class User extends Model
{
    protected string $table = 'users';

    // 可批量赋值的字段
    protected array $fillable = ['name', 'email', 'password'];

    // 隐藏字段
    protected array $hidden = ['password'];

    // 类型转换
    protected array $casts = [
        'is_admin' => 'boolean',
        'created_at' => 'datetime',
    ];
}
```

## 查询构建器

### 基础查询

```php
// 获取所有记录
$users = User::all();

// 获取单条记录
$user = User::find(1);
$user = User::where('id', 1)->first();

// 获取指定列
$users = User::select('id', 'name')->get();

// 条件查询
$users = User::where('status', 'active')
    ->where('age', '>=', 18)
    ->get();

// OR 条件
$users = User::where('status', 'active')
    ->orWhere('role', 'admin')
    ->get();

// IN 查询
$users = User::whereIn('id', [1, 2, 3])->get();
$users = User::whereNotIn('id', [1, 2])->get();

// NULL 查询
$users = User::whereNull('deleted_at')->get();
$users = User::whereNotNull('email')->get();

// BETWEEN 查询
$users = User::whereBetween('age', [18, 65])->get();

// LIKE 查询
$users = User::whereLike('name', '%john%')->get();
```

### 排序和分页

```php
// 排序
$users = User::orderBy('created_at', 'desc')->get();
$users = User::latest()->get();
$users = User::oldest()->get();
$users = User::inRandomOrder()->get();

// 限制数量
$users = User::take(10)->get();
$users = User::limit(10)->get();

// 跳过
$users = User::skip(10)->take(10)->get();
$users = User::offset(10)->limit(10)->get();

// 分页
$page = 1;
$perPage = 15;
$users = User::offset(($page - 1) * $perPage)->limit($perPage)->get();
```

### 聚合查询

```php
// 统计
$count = User::count();
$count = User::where('status', 'active')->count();

// 最大/最小值
$max = User::max('age');
$min = User::min('age');

// 平均值
$avg = User::avg('age');

// 求和
$sum = User::sum('points');

// 获取单个值
$email = User::where('id', 1)->value('email');

// 获取指定列的数组
$names = User::pluck('name');
$names = User::pluck('name', 'id'); // 返回 [id => name]
```

### 日期查询

```php
// 日期查询
$users = User::whereDate('created_at', '2024-01-01')->get();
$users = User::whereMonth('created_at', '1')->get();
$users = User::whereDay('created_at', '1')->get();
$users = User::whereYear('created_at', '2024')->get();
$users = User::whereTime('created_at', '>=', '12:00:00')->get();
```

### JOIN 查询

```php
// INNER JOIN
$users = User::join('posts', 'users.id', '=', 'posts.user_id')
    ->select('users.*', 'posts.title')
    ->get();

// LEFT JOIN
$users = User::leftJoin('profiles', 'users.id', '=', 'profiles.user_id')
    ->get();

// RIGHT JOIN
$users = User::rightJoin('orders', 'users.id', '=', 'orders.user_id')
    ->get();
```

### GROUP BY 和 HAVING

```php
// 分组
$stats = User::select('status', User::raw('COUNT(*) as count'))
    ->groupBy('status')
    ->get();

// HAVING
$stats = User::select('status', User::raw('COUNT(*) as count'))
    ->groupBy('status')
    ->having('count', '>', 10)
    ->get();
```

## 模型定义

### 配置选项

```php
class User extends Model
{
    // 表名（默认为类名的蛇形复数形式）
    protected string $table = 'users';

    // 主键（默认为 id）
    protected string $primaryKey = 'id';

    // 主键类型
    protected string $keyType = 'int'; // int 或 string

    // 是否自增
    protected bool $incrementing = true;

    // 是否使用时间戳
    protected bool $timestamps = true;

    // 创建时间字段
    public const CREATED_AT = 'created_at';

    // 更新时间字段
    public const UPDATED_AT = 'updated_at';

    // 可批量赋值的字段
    protected array $fillable = ['name', 'email', 'password'];

    // 不可批量赋值的字段（黑名单）
    protected array $guarded = ['id', 'created_at', 'updated_at'];

    // 隐藏字段（序列化时隐藏）
    protected array $hidden = ['password', 'remember_token'];

    // 可见字段（只显示这些字段）
    protected array $visible = ['id', 'name', 'email'];

    // 类型转换
    protected array $casts = [
        'is_admin' => 'boolean',
        'options' => 'array',
        'settings' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
```

### 访问器和修改器

```php
class User extends Model
{
    // 访问器
    protected function getFullNameAttribute(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    // 修改器
    protected function setPasswordAttribute(string $value): void
    {
        $this->attributes['password'] = password_hash($value, PASSWORD_DEFAULT);
    }
}
```

## CRUD 操作

### 创建记录

```php
// 使用 create 方法（批量赋值）
$user = User::create([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'password' => 'secret',
]);

// 使用 save 方法
$user = new User();
$user->name = 'John Doe';
$user->email = 'john@example.com';
$user->password = 'secret';
$user->save();

// 批量插入
User::insert([
    ['name' => 'John', 'email' => 'john@example.com'],
    ['name' => 'Jane', 'email' => 'jane@example.com'],
]);
```

### 更新记录

```php
// 通过模型更新
$user = User::find(1);
$user->name = 'Jane Doe';
$user->save();

// 通过查询更新
User::where('id', 1)->update(['name' => 'Jane Doe']);
User::updateWhere(['status' => 'active'], ['last_login' => now()]);

// 增加列值
User::where('id', 1)->increment('points', 10);

// 减少列值
User::where('id', 1)->decrement('points', 5);
```

### 删除记录

```php
// 通过模型删除
$user = User::find(1);
$user->delete();

// 通过查询删除
User::where('id', 1)->delete();
User::deleteWhere(['status' => 'inactive']);

// 删除多条
User::whereIn('id', [1, 2, 3])->delete();
```

### 查询记录

```php
// 根据 ID 查找
$user = User::find(1);
$users = User::findMany([1, 2, 3]);

// 查找或失败
$user = User::findOrFail(1); // 抛出异常如果不存在

// 查找或创建
$user = User::findOrNew(1);

// 条件查询
$user = User::where('email', 'john@example.com')->first();
$users = User::where('status', 'active')->get();

// 检查是否存在
$exists = User::where('email', 'john@example.com')->exists();
$notExists = User::where('email', 'john@example.com')->doesntExist();
```

## 关系

### Has One

```php
class User extends Model
{
    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }
}

// 使用
$profile = User::find(1)->profile;
$profile = User::find(1)->profile()->where('active', true)->first();

// 创建关联
$user = User::find(1);
$profile = new Profile(['bio' => 'Developer']);
$user->profile()->save($profile);

// 或使用 create
$user->profile()->create(['bio' => 'Developer']);
```

### Has Many

```php
class User extends Model
{
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}

// 使用
$posts = User::find(1)->posts;
$posts = User::find(1)->posts()->where('published', true)->get();

// 创建关联
$user = User::find(1);
$user->posts()->create(['title' => 'New Post', 'content' => '...']);

// 批量创建
$user->posts()->createMany([
    ['title' => 'Post 1', 'content' => '...'],
    ['title' => 'Post 2', 'content' => '...'],
]);
```

### Belongs To

```php
class Post extends Model
{
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}

// 使用
$author = Post::find(1)->author;

// 关联父模型
$post = Post::find(1);
$post->author()->associate($user);
$post->save();

// 取消关联
$post->author()->dissociate();
$post->save();
```

### Belongs To Many

```php
class User extends Model
{
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }
}

// 使用
$roles = User::find(1)->roles;

// 附加关系
$user->roles()->attach(1);
$user->roles()->attach(1, ['expires_at' => '2024-12-31']);

// 分离关系
$user->roles()->detach(1);
$user->roles()->detach([1, 2, 3]);

// 同步关系（只保留指定 ID）
$user->roles()->sync([1, 2, 3]);

// 更新中间表
$user->roles()->updateExistingPivot(1, ['expires_at' => '2024-12-31']);

// 查询中间表
$user->roles()->withPivot('expires_at', 'is_primary')->get();
```

## 集合

```php
use Bin\Database\Collection;

// 创建集合
$collection = Collection::make([1, 2, 3]);

// 过滤
$filtered = $collection->filter(fn($item) => $item > 1);

// 映射
$mapped = $collection->map(fn($item) => $item * 2);

// 获取指定列
$names = $users->pluck('name');
$names = $users->pluck('name', 'id');

// 分组
$grouped = $users->groupBy('status');

// 排序
$sorted = $users->sortBy('age');
$sorted = $users->sortByDesc('age');

// 统计
$count = $collection->count();
$sum = $collection->sum('points');
$avg = $collection->avg('age');
$max = $collection->max('age');
$min = $collection->min('age');

// 检查
$contains = $collection->contains(1);
$isEmpty = $collection->isEmpty();
$isNotEmpty = $collection->isNotEmpty();

// 获取第一个/最后一个
$first = $collection->first();
$last = $collection->last();
$first = $collection->first(fn($item) => $item > 1);

// 切片
$chunk = $collection->take(2);
$skipped = $collection->skip(2);
$slice = $collection->slice(2, 2);

// 去重
$unique = $collection->unique('id');

// 转换
$array = $collection->toArray();
$json = $collection->toJson();
```

## 渴望加载

防止 N+1 查询问题：

```php
// 不好的做法（N+1 查询）
$users = User::all();
foreach ($users as $user) {
    echo $user->profile->bio; // 每次迭代都执行一次查询
}

// 好的做法（渴望加载）
$users = User::with('profile')->get();
foreach ($users as $user) {
    echo $user->profile->bio; // 不会额外查询
}

// 加载多个关系
$users = User::with(['profile', 'posts'])->get();

// 嵌套渴望加载
$users = User::with('posts.comments')->get();

// 带条件的渴望加载
$users = User::with(['posts' => function ($query) {
    $query->where('published', true);
}])->get();

// 延迟渴望加载
$users = User::all();
$users->load('profile');
$users->loadMultiple(['profile', 'posts']);
```

## 事务

```php
// 使用查询构建器
User::query()->beginTransaction();
try {
    User::insert(['name' => 'John']);
    Profile::insert(['user_id' => 1, 'bio' => '...']);
    User::query()->commit();
} catch (\Exception $e) {
    User::query()->rollBack();
}

// 链式调用
User::query()
    ->beginTransaction()
    ->insert(['name' => 'John'])
    ->commit();
```

## 模型方法

### 检查状态

```php
$user = User::find(1);

// 是否被修改
$isDirty = $user->isDirty();
$isDirty = $user->isDirty('email');

// 是否干净
$isClean = $user->isClean();
$isClean = $user->isClean('email');

// 是否存在
$exists = $user->exists;

// 是否是新创建的
$wasRecentlyCreated = $user->wasRecentlyCreated();
```

### 克隆和刷新

```php
// 复制模型
$clone = $user->replicate();

// 从数据库刷新
$fresh = $user->fresh();
$user->refresh();
```

### 获取属性

```php
// 获取所有属性
$attributes = $user->getAttributes();

// 获取原始属性
$original = $user->getOriginal();
$original = $user->getOriginal('email');

// 获取变更的属性
$dirty = $user->getDirty();

// 获取干净的属性
$clean = $user->getClean();
```

### 序列化

```php
// 转为数组
$array = $user->toArray();
$array = $user->toArrayWithRelations();

// 转为 JSON
$json = $user->toJson();
$json = (string) $user;
```

## 作用域

```php
class User extends Model
{
    // 本地作用域
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeAdmin($query)
    {
        return $query->where('role', 'admin');
    }
}

// 使用作用域
$users = User::active()->get();
$users = User::admin()->active()->get();
```

## Laravel 13 P0 对齐 API（2026-09 阶段8）

### 查询构建器

```php
// upsert：存在则更新，不存在则插入（模型启用时间戳时自动补齐）
User::query()->upsert(
    [['email' => 'a@x.com', 'name' => 'a'], ['email' => 'b@x.com', 'name' => 'b']],
    uniqueBy: ['email'],
    update: ['name'],
);

// 忽略冲突插入 / 存在则更新
User::query()->insertOrIgnore([['email' => 'a@x.com', 'name' => 'a']]);
User::query()->updateOrInsert(['email' => 'a@x.com'], ['name' => 'a2']);

// 流式迭代（返回 \Generator；无 LazyCollection，如需链式操作请用 get/collect）
foreach (User::query()->orderBy('id')->cursor() as $user) { /* 单条 SQL，逐行水合 */ }
foreach (User::query()->lazy(500) as $user) { /* 按页分块流式 */ }
foreach (User::query()->lazyById(500) as $user) { /* 按主键前进，边遍历边更新筛选列时安全 */ }

// 子查询 JOIN 与 CROSS JOIN（注意：update()/delete() 不编译 JOIN，子查询 JOIN 仅用于 SELECT）
User::query()
    ->joinSub(fn ($q) => $q->from('posts')->select('user_id')->selectRaw('SUM(views) AS v')->groupBy('user_id'),
        'post_stats', 'users.id', '=', 'post_stats.user_id')
    ->get();
User::query()->crossJoin('roles')->get();

// 按主键过滤
User::query()->whereKey([1, 2, 3])->get();
User::query()->whereKeyNot([1])->get();
```

- `upsert` 按驱动分方言：MySQL `ON DUPLICATE KEY UPDATE`（按表索引判重，`$uniqueBy` 仅作列集参考）；SQLite/PostgreSQL `ON CONFLICT($uniqueBy) DO UPDATE SET col = excluded.col`。

### 模型层

```php
// 静默家族：不触发任何模型事件
$user->saveQuietly();
$user->deleteQuietly();          // 软删除模型上同样是软删
$post->restoreQuietly();         // SoftDeletes
$post->forceDeleteQuietly();     // SoftDeletes

// 绕过批量赋值保护的创建
User::forceCreate(['name' => 'a', 'secret_field' => 'x']);

// 变更追踪（反映"上次保存"）
$user->wasChanged();             // 上次保存是否写入过属性
$user->wasChanged('name');
$user->getChanges();             // 上次保存实际写入的列 => 新值
$user->getPrevious('name');      // 上次保存前的原值

// 同行比较与复制排除
$user->is($otherUser);           // 表名 + 主键都相同
$copy = $post->replicate(['views']);  // 复制时排除列（主键始终排除）

// 模型事件映射到自定义事件类（命中映射时不再触发默认监听）
class User extends Model
{
    protected array $dispatchesEvents = ['saved' => UserSaved::class];
}
```

### 关系层

```php
// has 家族：'>= 1' 编译 EXISTS，计数比较编译 (SELECT COUNT(*) ...) op n
User::has('posts')->get();
User::has('posts', '>=', 3)->get();
User::doesntHave('posts')->get();
User::where('banned')->orHas('posts', '>=', 5)->get();

// 嵌套（has 与 whereHas 家族都支持点号，回调作用于最深一层）
User::has('posts.comments')->get();
User::whereHas('posts.comments', fn ($q) => $q->where('body', 'like', '%hi%'))->get();

// 关系 make()：实例化未保存的关联模型并接线外键
$draft = $user->posts()->make(['title' => '草稿']);
$note  = $post->notes()->make(['content' => 'hi']);   // 多态：morphId + morphType 已接线
$role  = $user->roles()->make(['name' => 'editor']);  // 多对多：纯实例化

// sync 系列
$user->roles()->sync([2 => ['note' => '主编辑'], 3]);   // 映射形式：附加列随行写入/更新
$user->roles()->syncWithoutDetaching([4]);              // 只增不删
$user->roles()->syncWithPivotValues([5], ['note' => 'x']);
$user->roles()->toggle([2, 4]);                         // 有关联则解除，无则建立
```

已知边界：`lazy()` 系列返回 `\Generator` 而非 LazyCollection（morphOne/morphMany 的 `save()`/`create()` 写方法已于阶段10补齐）。

## 阶段9：集合分层与多命名连接（2026-09）

### Collection 双层结构

```php
use Bin\Support\Collection;          // 通用层：纯数组操作，不感知模型
use Bin\Database\Collection;         // 模型集合层：继承通用层（ORM 查询/关系统一返回本类）

// 模型集合专属方法
$users = User::all();
$users->modelKeys();                 // 全部主键
$users->find(2);                     // 按主键在集合内找模型（可传列名找其他列）
$users->find('alice', 'name');
$users->load('posts.comments');      // 为集合内所有模型延迟加载关系
$users->loadCount('posts');

// 通用层可独立使用（Request::collect() 也返回通用层）
Collection::make([3, 1, 2])->filter(fn ($v) => $v > 1)->sort();
```

子类的 `make()/map()/filter()` 等返回子类实例；行为语义与拆分前一致（`pluck/groupBy/keyBy/combine` 仍返回原生数组、`pop` 仍返回集合）。

### 多命名连接

```php
// config/database.php
'connections' => [
    'mysql'  => ['driver' => 'mysql', 'host' => ..., 'dbname' => ...],
    'sqlite' => ['driver' => 'sqlite', 'database' => ':memory:'],  // 文件路径或内存库
    'report' => ['driver' => 'pgsql', 'host' => ..., 'database' => ...],
],

// 在指定连接上查询
User::on('sqlite')->count();
Schema::connection('sqlite')->create('users', ...);
$migrator = new Migrator(connection: 'sqlite');

// 实例级连接名
$model->setConnectionName('report');

// 容器属性注入命名连接
function handler(#[Db('report')] PDO $pdo) { ... }

// 连接管理
ConnectionManager::getConnection('sqlite');   // 按名缓存，缺省名取 database.default
ConnectionManager::setConnection($pdo, 'analytics');  // 注入命名槽位
ConnectionManager::purge('analytics');        // 丢弃（下次重连）
```

要点：
- 缺省名 = `config('database.default')`；测试注入 `Model::setConnection($pdo)` 写默认槽位，语义不变。
- DSN 分驱动构建：mysql 沿用 `dbname/user/pass` 键并兼容 Laravel 风格 `database/username/password`；sqlite 用 `database` 路径；pgsql 用 `host/port/database`。
- 未配置的连接名抛 `PDOException: Database connection [x] is not configured.`。
- 关系查询跟随**相关模型自身**的连接（与 Eloquent 一致）：`on('sqlite')` 的模型，其关系仍走相关模型默认连接，除非相关模型自身声明了 `$connectionName`。
- 查询日志的 `connection` 字段现为真实连接名（此前误标为表名）。
- 读写分离尚未支持（需按语句类型分流的 Connection 抽象层）。

## 阶段10：Schema 列族修复、模型 trait 与 JSON 子句（2026-09）

### Schema（详见 docs/Migrations.md）

```php
// 流式列修改（此前 modifyColumn 命令被静默丢弃，fullText/spatialIndex 编译缺失——均已修复）
Schema::table('users', fn (Blueprint $t) => $t->string('name', 100)->nullable()->change());

// 多态列对与 rememberToken
$t->morphs('commentable');        // commentable_type + commentable_id(unsignedBigInteger) + 联合索引
$t->nullableMorphs('commentable');
$t->uuidMorphs('commentable');
$t->rememberToken();

// 索引：fullText/spatialIndex 现已真正编译；drop 系列接受索引名或列数组
$t->fullText(['title', 'content']);
$t->spatialIndex('location');
$t->dropIndex(['state', 'city']);  // 按列数组推导索引名 users_state_city_index
$t->dropFullText(['title']);
```

### 模型 trait

```php
// UUID/ULID 主键（零依赖自实现；UUIDv7 与 ULID 均为时间戳前缀、字典序可排序）
class Session extends Model
{
    use HasUuids;   // 默认 UUIDv7；覆写 newUniqueId()/uniqueIds() 自定义
    protected string $table = 'sessions';
    protected array $fillable = ['id', 'payload'];  // 显式传 id 需在 fillable 或用 forceCreate
}

// 定期修剪
class OldPost extends Model
{
    use Prunable;               // 或 MassPrunable（批量 DELETE，不触发事件）

    public function prunable(): QueryBuilder
    {
        return static::where('created_at', '<', date('Y-m-d', strtotime('-1 year')));
    }

    protected function pruning(): void { /* 删除前清理关联资源 */ }
}
// 执行：php command model:prune [--pretend] [--model=...] [--except=...]
```

### 事件补齐与 JSON 子句

```php
// 新增事件：trashed（软删后）、forceDeleting/forceDeleted、replicating
// trashed 与实例方法同名，无法静态注册——经 Observer 或 ModelEventDispatcher 监听

// JSON 数组包含（MySQL JSON_CONTAINS；SQLite/PG json_each 展开实现 ALL 语义）
User::query()->whereJsonContains('tags', 'admin')->get();
User::query()->whereJsonContains('options->roles', ['a', 'b'])->get();  // col->path 路径
User::query()->whereJsonDoesntContain('tags', 'banned')->get();
```

已知边界：SQLite/PG 的 JSON 包含为"数组包含全部给定值"语义、对象包含不支持（MySQL 原生支持）；`model:prune` 无调度器绑定，需手动或程序化调用。

## 完整示例

```php
// 定义模型
class User extends Model
{
    protected array $fillable = ['name', 'email', 'password'];
    protected array $hidden = ['password'];
    protected array $casts = [
        'is_admin' => 'boolean',
        'settings' => 'array',
    ];

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}

// 使用模型
use Bin\Database\Model;

// 创建
$user = User::create([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'password' => bcrypt('secret'),
]);

// 查询
$user = User::with(['posts', 'roles'])
    ->active()
    ->where('email', 'john@example.com')
    ->firstOrFail();

// 更新
$user->posts()->create([
    'title' => 'My First Post',
    'content' => 'Hello World!',
]);

// 关联
$user->roles()->attach([1, 2]);

// 删除
$user->delete();
```

## 行为说明（2026-09 修复后）

本轮修复后以下语义与 Laravel Eloquent 对齐，迁移旧代码时请注意：

- **标识符包裹**：SQL 编译器对表名/列名统一反引号包裹（MySQL/SQLite 兼容）。断言 `toSql()` 字符串的代码需要包含反引号，例如 `` `users` ``。
- **belongsTo 默认外键**：按关系名推导（`user()` → `user_id`）。外键为 NULL 时关系返回 `null`，而不是目标表第一行。
- **eager 加载返回类型**：`with()` 加载的一对多关系返回 `Collection`（与懒加载一致，不再是裸数组）；`isset($model->relation)` 对已加载关系返回 `true`。
- **嵌套 eager 加载**：`with('a.b')` 对任意数量的父模型恒定 2 条关系查询（此前第二层按父模型逐条查询）。
- **全局作用域**：`update()` / `delete()` / `increment()` / `decrement()` 等查询级写操作会应用全局作用域；软删除模型上 `Model::where(...)->delete()` 执行软删除（UPDATE `deleted_at`），`withTrashed()->delete()` 才是物理删除。按主键的 `save()` / `forceDelete()` 不受作用域限制。
- **实例方法**：`$model->update([...])` 等价于 `fill + save`（按主键）；`$model->increment()/decrement()` 按主键约束执行并同步内存属性。原始 SQL 接口更名为 `Model::updateSql($sql, $params)`（原 `Model::update($sql, $params)`，避免与实例方法冲突）。
- **操作符白名单**：`where()` 的操作符参数必须是合法 SQL 操作符，非法值抛 `InvalidArgumentException`；`where('col', '=', null)` 编译为 `IS NULL`。
- **空 whereIn**：`whereIn('col', [])` 编译为 `0 = 1`（`whereNotIn` 为 `1 = 1`），不再是非法的 `IN ()`。
- **morphMap**：全局生效，通过 `Model::enforceMorphMap(['alias' => ModelClass::class])` 设置（或 `Relation::enforceMorphMap`）；`Model::enforceMorphMap([], false)` 整体重置。中间表的多态类型值统一存 `getMorphClass()`（别名优先）。
- **trait 引导递归**：父类（如中间基类）`use SoftDeletes` 对子类同样生效。
- **迁移**：`foreignId('user_id')->constrained('users')->cascadeOnDelete()` 流式链可用；`migrate fresh` 会同时清掉 migrations 台账表；`$table->nullable()` / `$table->default()` 蓝图级方法已移除（会静默影响所有列），请使用列级链式调用。
- **Seeder**：文件 seeder 命名空间固定为 `Database\Seeders`，按文件名发现。

### 阶段8（2026-09 P0 对齐批次）新增与修复

- **save() 现在维护变更快照**：保存成功后 `getChanges()`/`wasChanged()`/`getPrevious()` 可用；`setRawAttributes()` 会清空 changes。此前 `changes` 恒为空数组。
- **replicate() 支持排除列**：`replicate(['last_flown'])`；主键从"置 null"改为"直接剔除"（两者读取语义等价）。
- **sync() 返回值语义扩展**：支持 `id => [附加列]` 映射形式，已在关联且带附加列时走 `updateExistingPivot` 并计入 `updated`；`detach` 数组键经过 `array_values` 归一。
- **修复 hasMany/hasOne 关系上的 create()/createMany()**：此前 `$related` 属性从未赋值，调用必然抛 "Typed property must not be accessed before initialization"。
- **修复 morphOne/morphMany 的 whereHas**：此前走通用回退、丢失多态类型条件，同 `morph_id` 异类型行会跨类型泄漏；现带 `morph_type = '...'` 条件。
- **修复 morphOne/morphMany 的 withCount/withSum**：此前 `getAggregateSubQuery` 误调基类占位实现直接抛 `BadMethodCallException`。
- **绑定顺序**：查询构建器新增 `join` 绑定桶（子查询 JOIN 的绑定参数），`getBindings()` 顺序为 join → where → having → order → union。仅影响使用了 `joinSub`/`leftJoinSub` 的查询。

### 阶段9（2026-09 结构性重构）变更

- **Collection 拆为双层**：通用层 `Bin\Support\Collection` + 模型集合层 `Bin\Database\Collection`（继承）。ORM 返回类型不变；`Request::collect()` 改返回通用层。新增模型集合方法 `find()/load()/loadCount()`，`modelKeys()` 归入模型集合层。
- **ConnectionManager 按名缓存**：`getConnection(?string $name = null)` / `setConnection(?PDO, ?string $name = null)` / `purge()`；`reset()` 清全部。默认连接名取 `config('database.default')`（不再固定单槽）。
- **Model 多连接**：`$connectionName` 生效（此前为死属性）；新增 `Model::on()`/`getConnectionName()`/`setConnectionName()`；`Schema::connection()`、`Migrator(connection:)`、`#[Db('name')]` 命名解析可用。
- **查询日志连接字段修正**：`QueryLog->connection` 由表名改为真实连接名（默认连接为 `'default'`），依赖该字段的日志分析需注意。
- **附带修复**：`DatabaseQueue` 构造传入的连接名此前被静默忽略，现生效。

### 阶段10（2026-09 小件快批）变更

- **⚠ forceDelete 事件语义变化（对齐 Eloquent）**：软删除模型 `forceDelete()` 现触发 `forceDeleting`/`forceDeleted`，**不再触发** `deleting`/`deleted`；依赖旧事件的代码请迁移。软删路径 `delete()` 在 `deleted` 之后追加触发 `trashed`。
- **新增事件**：`trashed`/`forceDeleting`/`forceDeleted`/`replicating`（Observer::EVENTS 含前三者；`trashed` 与实例方法同名无法静态注册）。
- **Schema 修复**：`fullText()`/`spatialIndex()` 此前是静默空操作（无编译分支）、`modifyColumn()` 命令被静默丢弃——均已修复；新增流式 `->change()`。真实迁移 `2024_01_01_000002_create_posts_table.php` 的 fullText 自此生效（需重新迁移）。
- **dropIndex/dropUnique/dropFullText/dropSpatialIndex** 接受列数组并按命名规则推导索引名。
- **morphOne/morphMany 具备写方法**（save/saveMany/create/createMany），此前完全没有写入口。
- **HasUuids/HasUlids**：`getIncrementing()` 强制 false、`getKeyType()` 强制 string。
