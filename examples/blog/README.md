# Blog 示例应用

这是一个使用 First PHP 框架构建的简单博客应用，展示了框架的主要功能。

## 功能特性

- ✅ 用户认证（登录/登出）
- ✅ 文章 CRUD（创建、读取、列表）
- ✅ RESTful API
- ✅ JSON 响应转换（API Resources）
- ✅ 数据库 ORM
- ✅ 视图模板
- ✅ 中间件保护

## 安装运行

### 1. 初始化数据库

```bash
cd /home/x/src/install/php/First/examples/blog

# 运行迁移
php ../../../migrate migrate -p=public/database/migrations

# 填充示例数据
php ../../../command db:seed BlogSeeder -p=public/database/seeders
```

### 2. 启动服务器

```bash
php -S localhost:8000 -t public
```

### 3. 访问应用

- 首页: http://localhost:8000/
- 登录: http://localhost:8000/login
  - 邮箱: admin@example.com
  - 密码: password

## API 接口

### 获取文章列表

```bash
curl http://localhost:8000/api/posts
```

响应：
```json
{
    "data": [
        {
            "id": 1,
            "title": "First Post",
            "content": "...",
            "status": "published",
            "published_at": "2024-01-01 12:00:00",
            "created_at": "2024-01-01 12:00:00"
        }
    ]
}
```

### 获取单篇文章

```bash
curl http://localhost:8000/api/posts/1
```

### 用户登录

```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"password"}'
```

响应：
```json
{
    "message": "Login successful",
    "user": {
        "id": 1,
        "name": "Admin User",
        "email": "admin@example.com"
    }
}
```

## 代码结构

```
examples/blog/
├── app/
│   ├── Controllers/       # 控制器
│   │   ├── AuthController.php
│   │   └── PostController.php
│   ├── Models/           # 模型
│   │   ├── User.php
│   │   └── Post.php
│   └── routes/           # 路由
│       └── web.php
├── config/              # 配置
│   └── app.php
├── database/
│   ├── migrations/       # 迁移
│   │   ├── 2024_01_01_create_users_table.php
│   │   └── 2024_01_02_create_posts_table.php
│   └── seeders/          # 数据填充
│       └── BlogSeeder.php
├── public/              # 公共目录
│   └── index.php
└── views/               # 视图
    ├── layouts/
    │   └── app.php
    ├── posts/
    │   ├── index.php
    │   ├── show.php
    │   └── create.php
    └── auth/
        └── login.php
```

## 框架功能展示

### 1. 路由系统

```php
Route::get('/posts', 'PostController@index');
Route::get('/posts/{id}', 'PostController@show')->with('[0-9]+');

// 中间件保护
Route::middle(['auth' => []], function () {
    Route::get('/posts/create', 'PostController@create');
});
```

### 2. ORM 模型

```php
// 查询文章
$posts = Post::published()->orderBy('created_at', 'desc')->get();

// 创建文章
Post::create([
    'title' => $title,
    'content' => $content,
    'user_id' => AuthManager::id(),
]);
```

### 3. 认证系统

```php
// 登录
AuthManager::attempt(['email' => $email, 'password' => $password]);

// 检查认证状态
if (AuthManager::check()) {
    $user = AuthManager::user();
}

// 登出
AuthManager::logout();
```

### 4. API Resources

```php
class PostResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'id' => $this->id(),
            'title' => $this->resource['title'],
            'content' => $this->resource['content'],
        ];
    }
}

// 返回 JSON
$collection = PostResource::collection($posts);
return response($collection->jsonSerialize());
```

## 扩展建议

- 添加评论功能
- 添加文章编辑
- 添加分页
- 添加搜索功能
- 添加图片上传
- 添加标签系统
