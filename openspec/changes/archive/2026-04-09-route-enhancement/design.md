# Design: route-enhancement

## 新增文件

### bin/Route/ResourceRegistrar.php
处理 `Route::resource()` / `Route::apiResource()` 批量注册。
- 构造函数接收 RouteCollection 类名
- `register($name, $controller, $options)` 方法生成 7 个 RESTful 路由
- `apiRegister()` 只注册 index/show/store/update/destroy（无 create/edit）
- 支持 `only`/`except` 选项过滤路由

### bin/Route/RouteBinding.php
路由模型绑定解析器。
- `bind($key, $resolver)` — 注册自定义绑定解析器
- `model($key, $class, $callback)` — 注册模型类自动解析
- `resolve($key, $value)` — 运行时解析参数值
- 隐式绑定：通过 Reflection 检测控制器参数类型提示

### bin/Console/Commands/RouteCacheCommand.php
路由缓存命令，将路由编译为 PHP 数组。

### bin/Console/Commands/RouteClearCommand.php
清除路由缓存。

## 修改文件

### bin/Route/RouteCollection.php
- 新增 `resource($name, $controller, $options)` — 委托 ResourceRegistrar
- 新增 `apiResource($name, $controller, $options)`
- 新增 `fallback($action)` — 注册兜底路由
- 新增 `redirect($path, $destination, $status)` / `permanentRedirect()`
- 新增 `view($path, $view, $data)` — 返回视图的路由
- 新增 `namedRoute($name)` — 查找命名路由
- group() 新增 `namespace` 和 `domain` 属性支持
- match() 优先查静态缓存，fallback 兜底

### bin/Route/Route.php
- 新增 `where($param, $pattern)` — 参数约束（补充 with）
- 新增 `defaults($key, $value)` — 参数默认值

### bin/Route/RouteAction.php
- 集成 RouteBinding 模型解析
- 控制器方法参数注入时检测模型类型

## 数据流

```
Route::resource('posts', PostController::class)
  → ResourceRegistrar::register()
  → 生成 7 条 Route（index/create/store/show/edit/update/destroy）

Route::get('/users/{user}', function(User $user) { ... })
  → RouteAction::dispatch()
  → Reflection 检测参数类型 User
  → RouteBinding::resolve('user', $id)
  → User::findOrFail($id) 注入
```
