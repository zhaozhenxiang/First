<?php

/**
 * ORM 使用示例
 *
 * 这个文件展示了如何使用 ORM 进行数据库操作
 */

require_once __DIR__ . '/../bin/autoload.php';

use App\Model\User;

// ==================== 基础查询 ====================

// 获取所有用户
$users = User::all();

// 根据 ID 查找
$user = User::find(1);

// 条件查询
$user = User::where('email', 'john@example.com')->first();

// 多条件查询
$users = User::where('status', 'active')
    ->where('age', '>=', 18)
    ->orderBy('created_at', 'desc')
    ->get();

// IN 查询
$users = User::whereIn('id', [1, 2, 3])->get();

// ==================== 聚合查询 ====================

// 统计
$count = User::count();
$count = User::where('status', 'active')->count();

// 最大值/最小值
$maxAge = User::max('age');
$minAge = User::min('age');

// 平均值
$avgAge = User::avg('age');

// 求和
$totalPoints = User::sum('points');

// ==================== 创建记录 ====================

// 使用 create 方法（批量赋值）
$user = User::create([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'password' => password_hash('secret', PASSWORD_DEFAULT),
]);

// 使用 save 方法
$user = new User();
$user->name = 'Jane Doe';
$user->email = 'jane@example.com';
$user->password = password_hash('secret', PASSWORD_DEFAULT);
$user->save();

// 批量插入
User::insert([
    ['name' => 'Alice', 'email' => 'alice@example.com'],
    ['name' => 'Bob', 'email' => 'bob@example.com'],
]);

// ==================== 更新记录 ====================

// 通过模型更新
$user = User::find(1);
$user->name = 'Updated Name';
$user->save();

// 通过查询更新
User::where('id', 1)->update(['name' => 'Updated Name']);

// 增加列值
User::where('id', 1)->increment('points', 10);

// 减少列值
User::where('id', 1)->decrement('points', 5);

// ==================== 删除记录 ====================

// 通过模型删除
$user = User::find(1);
$user->delete();

// 通过查询删除
User::where('id', 1)->delete();

// ==================== 关系操作 ====================

// 获取用户的文章
$posts = User::find(1)->posts;

// 获取用户的文章（带条件）
$posts = User::find(1)->posts()->where('published', true)->get();

// 创建关联文章
$user = User::find(1);
$user->posts()->create([
    'title' => 'New Post',
    'content' => 'Post content here...',
]);

// 附加多对多关系
$user->roles()->attach(1);

// 分离多对多关系
$user->roles()->detach(1);

// 同步多对多关系
$user->roles()->sync([1, 2, 3]);

// ==================== 渴望加载 ====================

// 防止 N+1 查询
$users = User::with('posts')->get();

// 加载多个关系
$users = User::with(['posts', 'roles'])->get();

// 嵌套渴望加载
$users = User::with('posts.comments')->get();

// 带条件的渴望加载
$users = User::with(['posts' => function ($query) {
    $query->where('published', true);
}])->get();

// ==================== 作用域 ====================

// 使用本地作用域
$users = User::active()->get();
$users = User::admin()->active()->get();

// ==================== 集合操作 ====================

use Bin\Database\Collection;

$collection = Collection::make([1, 2, 3, 4, 5]);

// 过滤
$filtered = $collection->filter(fn($item) => $item > 2);

// 映射
$mapped = $collection->map(fn($item) => $item * 2);

// 获取指定列
$names = $users->pluck('name');
$names = $users->pluck('name', 'id');

// 分组
$grouped = $users->groupBy('status');

// 排序
$sorted = $users->sortBy('age');

// 统计
$count = $collection->count();
$sum = $collection->sum('points');
$avg = $collection->avg('age');

// ==================== 事务 ====================

// 开启事务
User::query()->beginTransaction();
try {
    User::insert(['name' => 'John']);
    Profile::insert(['user_id' => 1, 'bio' => '...']);
    User::query()->commit();
} catch (\Exception $e) {
    User::query()->rollBack();
}

// ==================== 模型方法 ====================

$user = User::find(1);

// 检查状态
$isDirty = $user->isDirty();
$isDirty = $user->isDirty('email');

// 克隆模型
$clone = $user->replicate();

// 从数据库刷新
$fresh = $user->fresh();
$user->refresh();

// 序列化
$array = $user->toArray();
$json = $user->toJson();

// ==================== 原始 SQL ====================

// 使用原始表达式
$users = User::select([
    'id',
    'name',
    User::raw('COUNT(*) as post_count')
])
    ->join('posts', 'users.id', '=', 'posts.user_id')
    ->groupBy('users.id')
    ->get();
