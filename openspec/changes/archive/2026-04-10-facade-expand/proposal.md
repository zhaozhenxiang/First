## Why

当前 Facade 系统仅有 `Event` 和 `Request` 两个具体 Facade。Laravel 有 30+ Facade 作为各子系统的静态代理入口，是框架的标志性 API 风格。缺少 Facade 意味着每次访问子系统服务都必须通过 `app()` 容器或 `Manager::getInstance()`，代码冗长且不够优雅。

## What Changes

- 为所有核心模块新增 Facade：Cache/Config/DB/Log/Session/Auth/Gate/Hash/Route/URL/View/Validator/File/Cookie
- 每个 Facade 通过 `getFacadeAccessor()` 绑定到容器中的对应服务
- Facade 支持 `shouldProxyTo()` 运行时替换（测试友好）
- 补充 `config/app.php` 中 aliases 配置

## Capabilities

### New Capabilities
- `facade-registry`: Facade 注册表 + 别名系统

### Modified Capabilities

## Impact

- `bin/Facade/Cache.php` — 新增
- `bin/Facade/Config.php` — 新增
- `bin/Facade/DB.php` — 新增
- `bin/Facade/Log.php` — 新增
- `bin/Facade/Session.php` — 新增
- `bin/Facade/Auth.php` — 新增
- `bin/Facade/Gate.php` — 新增
- `bin/Facade/Hash.php` — 新增
- `bin/Facade/Route.php` — 新增
- `bin/Facade/URL.php` — 新增
- `bin/Facade/View.php` — 新增
- `bin/Facade/Validator.php` — 新增
- `bin/Facade/File.php` — 新增
- `bin/Facade/Cookie.php` — 新增
- `config/app.php` — 补充 aliases
- `tests/FacadeExpandTest.php` — 新增测试
