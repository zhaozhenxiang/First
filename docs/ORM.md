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
