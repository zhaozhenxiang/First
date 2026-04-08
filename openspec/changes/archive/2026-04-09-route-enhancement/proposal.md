## Why

当前路由系统支持基本 HTTP 方法注册、命名路由和正则约束，但缺少 Laravel 的核心高级功能：RESTful resource 路由注册、路由模型绑定、路由缓存、签名路由、子域名路由、namespace 分组、fallback 路由。这些功能大幅减少 CRUD 路由的重复代码和 Controller 中的样板逻辑。

## What Changes

- 新增 `Route::resource()` / `Route::apiResource()` RESTful 路由批量注册
- 新增路由模型绑定：隐式 (类型提示) + 显式 (`Route::model()` / `Route::bind()`)
- 新增路由缓存：`route:cache` / `route:clear` 命令
- 新增路由组 namespace 支持
- 新增 `Route::fallback()` 兜底路由
- 新增 `Route::redirect()` / `Route::permanentRedirect()` / `Route::view()`
- 新增签名路由 (Signed Routes) 和临时签名 URL
- 修复 `group()` 回调重复调用和 prefix 未生效的 bug

## Capabilities

### New Capabilities
- `resource-routing`: Route::resource() / apiResource()，自动注册 CRUD 7 路由
- `route-model-binding`: 隐式 + 显式路由模型绑定，自动从数据库解析模型实例
- `route-caching`: 路由编译缓存，route:cache / route:clear 命令
- `signed-routes`: 签名 URL 生成和验证，支持临时过期
- `route-redirects`: Route::redirect() / view() 快捷注册

### Modified Capabilities
- `route-groups`: 修复 group() bug，新增 namespace/domain 选项

## Impact

- `bin/Route/RouteCollection.php` — 新增 resource/apiResource/group 修复
- `bin/Route/RouteAction.php` — 集成模型绑定解析
- `bin/Route/Route.php` — 新增 bind/model 静态方法
- `bin/Route/ResourceRegistrar.php` — 新增，处理 resource 路由注册
- `bin/Route/RouteBinding.php` — 新增，模型绑定解析逻辑
- `bin/Console/Commands/RouteCacheCommand.php` — 新增
- `bin/Console/Commands/RouteClearCommand.php` — 新增
- `bin/Console/Kernel.php` — 注册新命令
- `tests/RouteEnhancementTest.php` — 新增测试
