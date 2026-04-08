# Tasks: route-enhancement

## Phase 1: Resource 路由

- [x] 新增 ResourceRegistrar — resource/apiResource 批量注册
- [x] RouteCollection 集成 resource/apiResource

## Phase 2: 模型绑定

- [x] 新增 RouteBinding — model()/bind()/resolve()
- [x] RouteAction 集成模型绑定解析

## Phase 3: 路由组增强

- [x] group() 新增 namespace 支持
- [x] group() 新增 domain 支持

## Phase 4: 快捷路由

- [x] Route::fallback() 兜底路由
- [x] Route::redirect() / permanentRedirect() / view()

## Phase 5: 测试

- [x] RouteEnhancementTest — resource、绑定、组增强、快捷路由全覆盖
