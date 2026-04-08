# Spec: resource-routing

## Route::resource($name, $controller, $options)

注册 RESTful 资源路由，自动映射 CRUD 操作：

| HTTP 方法 | URI | 动作 | 路由名 |
|-----------|-----|------|--------|
| GET | /{name} | index | {name}.index |
| GET | /{name}/create | create | {name}.create |
| POST | /{name} | store | {name}.store |
| GET | /{name}/{id} | show | {name}.show |
| GET | /{name}/{id}/edit | edit | {name}.edit |
| PUT/PATCH | /{name}/{id} | update | {name}.update |
| DELETE | /{name}/{id} | destroy | {name}.destroy |

### Options
- `only: ['index', 'show']` — 只注册指定路由
- `except: ['create', 'edit']` — 排除指定路由
- `names: ['index' => 'posts.list']` — 自定义路由名
- `parameters: ['post' => 'post:slug']` — 自定义参数名

## Route::apiResource($name, $controller, $options)

同 resource 但排除 create 和 edit（API 不需要表单页面）。

## ResourceRegistrar

内部类处理路由注册逻辑，支持嵌套资源 `Route::resource('posts.comments', ...)`。
