# facade-expand Design

## Architecture

每个 Facade 是一个极简类：仅定义 `getClassName()` 返回底层服务类名。通过 Facade 基类的 `__callStatic` 代理调用。

两种风格：
1. **纯代理型**（如 Cache/Log/Config/DB）：仅 `getClassName()` + `@method` 注解，所有调用走 `__callStatic`
2. **快捷方法型**（如 Auth/Gate/Session）：额外定义常用静态方法以 IDE 友好

### Facade → 服务映射

| Facade | 底层类 | 容器注册方式 |
|--------|--------|-------------|
| Cache | CacheManager::getInstance() | singleton |
| Config | ConfigRepository | singleton |
| DB | ConnectionManager | static — Facade 特殊处理 |
| Log | LogManager::getInstance() | singleton |
| Session | SessionManager | singleton |
| Auth | AuthManager::getInstance() | singleton |
| Gate | Gate::getInstance() | singleton |
| Hash | HashManager | 纯静态类 — Facade 特殊处理 |
| Route | RouteCollection | 纯静态类 — Facade 特殊处理 |
| URL | RouteCollection | 同 Route |
| View | View | 每次 new |
| Validator | ValidationManager | 每次 new |
| Cookie | CookieManager | 纯静态类 — Facade 特殊处理 |
| File | （预留） | — |

### 特殊处理

对于纯静态类（HashManager, CookieManager, RouteCollection），Facade 不通过容器解析，直接在 `getInstance()` 中返回类实例或使用特殊解析逻辑。

### 核心别名注册

在 `App::$coreAliases` 中注册所有服务，在 `App::$facades` 中注册所有 Facade。

## Files to Create/Modify

### New Files (14 Facades)
1. `bin/Facade/Cache.php`
2. `bin/Facade/Config.php`
3. `bin/Facade/DB.php`
4. `bin/Facade/Log.php`
5. `bin/Facade/Session.php`
6. `bin/Facade/Auth.php`
7. `bin/Facade/Gate.php`
8. `bin/Facade/Hash.php`
9. `bin/Facade/Route.php`
10. `bin/Facade/URL.php`
11. `bin/Facade/View.php`
12. `bin/Facade/Validator.php`
13. `bin/Facade/Cookie.php`
14. `bin/Facade/File.php`

### Modified Files
1. `bin/App/App.php` — 扩展 `$coreAliases` 和 `$facades` 数组
2. `tests/FacadeExpandTest.php` — 新增测试
