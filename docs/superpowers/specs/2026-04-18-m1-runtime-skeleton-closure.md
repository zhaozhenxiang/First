# M1 运行时骨架统一 Closure

## 结论

`M1` 于 `2026-04-18` 完成收口，可以关账。

关账依据不是“代码看起来差不多”，而是两部分同时成立：

1. 主 spec 中定义的 6 条验收标准都已经有实现落点。
2. `M1` 测试清单中的一级、二级回归在本轮验收里全部通过。

---

## 验收标准对照

### 1. HTTP 与 Console 共用统一启动骨架

已满足。

实现落点：

- `HttpKernel` 固定 HTTP bootstrap 顺序，并把 `LoadMiddlewareConfiguration`、`LoadRoutes` 放进 HTTP 专属阶段。
- `ConsoleKernel` 复用同一套核心 bootstrap 阶段，只去掉 HTTP 专属阶段。
- `LoadMiddlewareConfiguration` 与 `LoadRoutes` 把中间件配置和路由加载从临时入口逻辑收口为显式 bootstrapper。

验收证据：

- `tests/ApplicationLifecycleTest.php` `Passed: 32, Failed: 0`
- `tests/ConsoleArtisanParityTest.php` `Passed: 24, Failed: 0`

### 2. 核心对象已经由容器稳定装配

已满足。

实现落点：

- `App` 持有容器并作为生命周期协调入口。
- `ProviderRepository` 通过 `$app->make()` 构造 provider，而不是直接硬编码实例化。
- `RouteAction` 与 `ControllerDispatcher` 通过容器解析调度器、请求和中间件实例。

验收证据：

- `tests/AppTest.php` `Passed: 41, Failed: 0`
- `tests/ContainerTest.php` `Passed: 25, Failed: 0`
- `tests/IocContainerTest.php` `Passed: 32, Failed: 0`
- `tests/DispatcherIntegrationTest.php` `Passed: 20, Failed: 0`

### 3. 请求生命周期可以清晰描述并由测试覆盖

已满足。

当前主链路可以稳定描述为：

1. `HttpKernel::handle()` 触发 bootstrap
2. `Request::capture()` 创建请求
3. `RouteAction::dispatch()` 收集中间件并构建 pipeline
4. `ControllerDispatcher` 解析控制器 / 闭包参数并执行 action
5. `ResponseFactory` 归一化返回值
6. `HttpKernel::terminate()` 触发 terminate 链路

验收证据：

- `tests/ApplicationLifecycleTest.php` `Passed: 32, Failed: 0`
- `tests/MiddlewarePipelineTest.php` `Passed: 94, Failed: 0`
- `tests/DispatcherIntegrationTest.php` `Passed: 20, Failed: 0`
- `tests/RouteTest.php` `Passed: 40, Failed: 0`
- `tests/RequestTest.php` `Passed: 90, Failed: 0`

### 4. 异常从统一出口转换

已满足。

实现落点：

- `ExceptionHandler` 统一承担 `report`、HTTP `render` 和 console `renderForConsole`。
- `ConsoleKernel` 的异常出口收口到容器解析的 `ExceptionHandler`。
- `abort()`、验证失败、HTTP 异常、常见业务异常都经由同一处理器语义转换。

验收证据：

- `tests/ExceptionHandlerTest.php` `Passed: 33, Failed: 0`
- `tests/LegacyExceptionHandlerTest.php` `Passed: 2, Failed: 0`
- `tests/ErrorPathTest.php` `Passed: 25, Failed: 0`
- `tests/ConsoleArtisanParityTest.php` `Passed: 24, Failed: 0`

### 5. 控制器与异常处理器共享同一响应体系

已满足。

实现落点：

- `ResponseFactory` 成为统一响应归一化入口。
- `ResponseServiceProvider` 注册 `ResponseFactory` 并暴露 `response.factory`。
- `response()` / `redirect()` helper、`ControllerDispatcher`、`ExceptionHandler` 都复用同一响应工厂。

验收证据：

- `tests/ResponseTest.php` `Passed: 13, Failed: 0`
- `tests/DispatcherIntegrationTest.php` `Passed: 20, Failed: 0`
- `tests/ExceptionHandlerTest.php` `Passed: 33, Failed: 0`

### 6. 路由已经能承担 URL 生成和绑定基础设施职责

已满足。

实现落点：

- `Route::url()` 固化必填参数、可选参数和缺参异常语义。
- `UrlGenerationException` 作为显式 URL 生成错误类型。
- `RouteBinding` 统一显式绑定、隐式绑定以及绑定失败到 `NotFoundHttpException` 的转换。

验收证据：

- `tests/RouteTest.php` `Passed: 40, Failed: 0`
- `tests/RouteEnhancementTest.php` `Passed: 52, Failed: 0`

---

## 本轮验收结果

### 一级回归集

本轮共重新执行 11 个测试文件，`406` 个测试全部通过：

- `tests/ApplicationLifecycleTest.php`
- `tests/AppTest.php`
- `tests/ContainerTest.php`
- `tests/IocContainerTest.php`
- `tests/RouteTest.php`
- `tests/RouteEnhancementTest.php`
- `tests/MiddlewarePipelineTest.php`
- `tests/DispatcherIntegrationTest.php`
- `tests/ExceptionHandlerTest.php`
- `tests/ResponseTest.php`
- `tests/ConsoleArtisanParityTest.php`

### 二级回归集

本轮共重新执行 8 个测试文件，`246` 个测试全部通过：

- `tests/FormRequestTest.php`
- `tests/ValidationTest.php`
- `tests/ErrorPathTest.php`
- `tests/RequestTest.php`
- `tests/MiddlewareTest.php`
- `tests/ResourceTest.php`
- `tests/CookieUploadTest.php`
- `tests/LegacyExceptionHandlerTest.php`

### 汇总

- 总计：`652` 通过，`0` 失败

---

## 已知限制

`tests/CookieUploadTest.php` 在 CLI 测试环境里仍会打印 `Cannot modify header information` warning。当前判断这是测试执行器已产生输出后再调用 `setcookie()` 的环境噪音，不影响本轮 `M1` 验收结论，因为：

- 测试结果仍为 `Passed: 22, Failed: 0`
- warning 指向的是 header 输出时机，而不是 runtime skeleton 的主链路回归

这项问题应在后续测试基础设施整理时单独处理，不作为阻塞 `M1` 关账的条件。

---

## 关账后状态

`M1` 现在从“实施中”切换为“已验收完成”。

后续如果继续推进，下一步不应再扩写 `M1` plan，而应开始 `M2` 拆分与规划。
