## Why

当前 `RouteCollection` 已经支持静态/动态路由、分组、resource、named route、fallback 和绑定代理，但行为仍偏“功能存在”，还没有完全达到 Laravel 13 的规则一致性，尤其是组属性合并、约定行为和路由元信息组织。

## What Changes

- 对齐 Laravel 风格的路由组属性合并规则
- 明确 prefix / name / domain / where / namespace 的组合行为
- 规范 resource / apiResource 的生成规则
- 统一 fallback、redirect、view、binding 等快捷路由语义
- 为后续 middleware 与 controller dispatch 提供稳定路由元数据

## Capabilities

### New Capabilities
- `route-group-merging`: 路由组属性按规则继承和合并
- `route-metadata`: 统一存储命名、约束、domain、controller action 等元数据

### Modified Capabilities
- `route-registration`: 路由注册行为更接近 Laravel 13 约定
- `resource-routing`: resource / apiResource 规则与命名进一步标准化
- `route-binding-hooks`: 参数绑定入口与路由解析配合更清晰

## Impact

- `bin/Route/RouteCollection.php` — 路由注册、分组和查找行为调整
- `bin/Route/Route.php` / `RouteAction.php` / `ResourceRegistrar.php` — 路由元数据与生成规则收敛
- `bin/Route/RouteBinding.php` — 与路由参数解析协同
- `tests/Route*` — 组属性、resource、绑定、fallback 行为测试补强
