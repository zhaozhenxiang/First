## Why

框架没有 HTTP 客户端封装。Laravel HTTP Client (基于 Guzzle) 提供优雅的 API 进行外部 API 调用，支持并发请求 (Pool)、重试、中间件、Bearer token 认证等。调用外部 API 是现代 Web 应用的常见需求。

## What Changes

- 新增 HTTP Client：HttpClient + Request + Response + Pool
- 支持常见 HTTP 方法：GET/POST/PUT/PATCH/DELETE/HEAD/OPTIONS
- 支持请求头、Query 参数、Form 数据、JSON body、Multipart 上传
- 支持 Bearer Token / Basic Auth 认证
- 支持请求/响应中间件
- 支持并发请求 (Pool)
- 支持超时和重试配置
- 新增 `config/http.php` 配置文件

## Capabilities

### New Capabilities
- `http-client`: HttpClient 封装 + Request Builder + Response 对象
- `http-pool`: 并发请求 (Pool)
- `http-middleware`: 请求/响应中间件 (retry/logging/auth)
- `http-config`: http 配置文件

### Modified Capabilities

## Impact

- `bin/Http/HttpClient.php` — 新增
- `bin/Http/HttpRequest.php` — 新增
- `bin/Http/HttpResponse.php` — 新增
- `bin/Http/HttpPool.php` — 新增
- `bin/Http/PendingRequest.php` — 新增
- `config/http.php` — 新增
- `bin/Facade/Http.php` — 新增
- `tests/HttpClientTest.php` — 新增测试
