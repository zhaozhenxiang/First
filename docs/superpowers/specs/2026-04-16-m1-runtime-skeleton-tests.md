# M1 测试清单

## 关联文档

- 主 spec： [M1 主 spec](/home/x/src/install/php/First/docs/superpowers/specs/2026-04-16-m1-runtime-skeleton-spec.md)
- 任务拆解： [M1 任务拆解](/home/x/src/install/php/First/docs/superpowers/specs/2026-04-16-m1-runtime-skeleton-tasks.md)
- 改造顺序： [M1 改造顺序](/home/x/src/install/php/First/docs/superpowers/specs/2026-04-16-m1-runtime-skeleton-order.md)
- 索引页： [M1 文档索引](/home/x/src/install/php/First/docs/superpowers/specs/2026-04-16-m1-runtime-skeleton-index.md)

## 目标

这份清单只服务 `M1`。重点不是“全量跑所有测试”，而是优先守住运行时骨架。

## 一级回归集

这些测试建议作为每次主链路改动后的必跑集合：

- [tests/ApplicationLifecycleTest.php](/home/x/src/install/php/First/tests/ApplicationLifecycleTest.php)
- [tests/AppTest.php](/home/x/src/install/php/First/tests/AppTest.php)
- [tests/ContainerTest.php](/home/x/src/install/php/First/tests/ContainerTest.php)
- [tests/IocContainerTest.php](/home/x/src/install/php/First/tests/IocContainerTest.php)
- [tests/RouteTest.php](/home/x/src/install/php/First/tests/RouteTest.php)
- [tests/RouteEnhancementTest.php](/home/x/src/install/php/First/tests/RouteEnhancementTest.php)
- [tests/MiddlewarePipelineTest.php](/home/x/src/install/php/First/tests/MiddlewarePipelineTest.php)
- [tests/DispatcherIntegrationTest.php](/home/x/src/install/php/First/tests/DispatcherIntegrationTest.php)
- [tests/ExceptionHandlerTest.php](/home/x/src/install/php/First/tests/ExceptionHandlerTest.php)
- [tests/ResponseTest.php](/home/x/src/install/php/First/tests/ResponseTest.php)
- [tests/ConsoleArtisanParityTest.php](/home/x/src/install/php/First/tests/ConsoleArtisanParityTest.php)

## 二级回归集

这些测试建议在阶段性交付前跑一轮：

- [tests/FormRequestTest.php](/home/x/src/install/php/First/tests/FormRequestTest.php)
- [tests/ValidationTest.php](/home/x/src/install/php/First/tests/ValidationTest.php)
- [tests/ErrorPathTest.php](/home/x/src/install/php/First/tests/ErrorPathTest.php)
- [tests/RequestTest.php](/home/x/src/install/php/First/tests/RequestTest.php)
- [tests/MiddlewareTest.php](/home/x/src/install/php/First/tests/MiddlewareTest.php)
- [tests/ResourceTest.php](/home/x/src/install/php/First/tests/ResourceTest.php)
- [tests/CookieUploadTest.php](/home/x/src/install/php/First/tests/CookieUploadTest.php)
- [tests/LegacyExceptionHandlerTest.php](/home/x/src/install/php/First/tests/LegacyExceptionHandlerTest.php)

## 按任务映射测试

## T1 Bootstrap

- `ApplicationLifecycleTest`
- `AppTest`
- `ConfigRepositoryTest`
- `ConfigSystemTest`

## T2 容器

- `ContainerTest`
- `IocContainerTest`
- `IocBehaviorTest`

## T3 HTTP 主链路

- `RouteTest`
- `RouteEnhancementTest`
- `MiddlewarePipelineTest`
- `DispatcherIntegrationTest`
- `RequestTest`

## T4 异常出口

- `ExceptionHandlerTest`
- `LegacyExceptionHandlerTest`
- `ErrorPathTest`
- `ValidationTest`

## T5 Response

- `ResponseTest`
- `ErrorPathTest`
- `CookieUploadTest`

## T6 路由基础设施

- `RouteTest`
- `RouteEnhancementTest`
- `ResourceTest`
- `DispatcherIntegrationTest`

## 验收口径

满足下面几点，才算 `M1` 的测试基本过关：

1. 启动流程测试稳定。
2. 容器解析测试稳定。
3. 路由和中间件主链路稳定。
4. 异常出口和响应归一化稳定。
5. Console 基础链路未被破坏。

## 风险提醒

- 不要只跑局部单测就宣称 `M1` 完成。
- 不要在改异常或响应时跳过 Console 测试。
- 不要在改路由时只验证 happy path。
