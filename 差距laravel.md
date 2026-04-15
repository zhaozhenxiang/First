# First 框架 vs Laravel 差距分析

> 生成日期：2026-04-08
> 基于对框架 29 个核心模块的逐文件审查

---

## 成熟度总览

```
    完成度
    100% ┤
         │  ████ QueryBuilder (1996 行, 子查询/事务/锁/scope/chunk 全有)
     80% ┤  ████ Console (377 行, signature/IO/表格/进度条/交互问询)
         │  ████ ORM/Model (665+2000 行, 11 种关联/eager load/事件/Observer)
     60% ┤  ████ Container (上下文绑定/tag/rebinding/circular 检测)
         │  ████ Auth 基础 (session/token/Gate/Policy/RBAC/RateLimit)
         │
     40% ┤        ████ Routing (缺 resource/model-binding/cache/signed)
         │        ████ Config (仅 3 个配置文件)
         │               ████ Middleware (38 行抽象类, 无 pipeline)
     20% ┤                      ████ Validation (仅 XSS 清理, 无规则引擎)
         │                      ████ View/Blade (纯 PHP require_once)
      0% ┤                                     ████ Queue/Mail/Notification/...
         └────────────────────────────────────────────────────── 模块
```

---

## 第一梯队：深度对齐 (80%+)

这些模块已基本达到 Laravel 的功能覆盖度。

### QueryBuilder — 完成度 ~85%

**文件：** `bin/Database/QueryBuilder.php` (1996 行) + `CompilesQueries.php` (252 行)

| 功能 | 状态 | 说明 |
|------|------|------|
| WHERE 全系列 | ✅ | where/orWhere/whereIn/whereNotIn/whereNull/whereNotNull/whereBetween/whereLike/whereDate/whereColumn/whereExists/nested where |
| 子查询 | ✅ | whereIn 闭包子查询、whereExists/whereNotExists |
| Raw 表达式 | ✅ | selectRaw/whereRaw/orderByRaw/groupByRaw/havingRaw |
| 聚合函数 | ✅ | count/max/min/avg/sum |
| JOIN | ✅ | inner/left/right |
| Eager Loading | ✅ | with() + 嵌套点号 |
| 关联查询 | ✅ | whereHas/whereDoesntHave/withCount/withSum/withAvg/withMin/withMax |
| 分页 | ✅ | paginate/simplePaginate/cursorPaginate |
| 悲观锁 | ✅ | lockForUpdate/sharedLock |
| UNION | ✅ | union/unionAll |
| Chunk | ✅ | chunk/chunkById/each/eachById |
| Global Scopes | ✅ | withGlobalScope/withoutGlobalScope/withoutGlobalScopes |
| 条件构建 | ✅ | when/unless |
| 事务 | ✅ | beginTransaction/commit/rollBack |
| 调试 | ✅ | dump/dd/explain |
| Increment/Decrement | ✅ | |
| **读写分离** | ❌ | 单 PDO 连接，无 read/write 分离 |
| **Upsert** | ❌ | 无 updateOrInsert/upsert/insertOrIgnore |

### Console (Artisan) — 完成度 ~80%

**文件：** `bin/Console/Command.php` (377 行) + `Kernel.php` + `Input.php` + `Output.php`

| 功能 | 状态 |
|------|------|
| Signature 解析 | ✅ `{arg}`, `{arg?}`, `{arg=*}`, `{--option}`, `{--option=*}` |
| 参数/选项 | ✅ |
| 输出格式化 | ✅ info/warning/error/success/comment/line |
| 表格输出 | ✅ table() |
| 进度条 | ✅ createProgressBar() |
| 交互式输入 | ✅ ask/confirm/choice/secret |
| 命令调用 | ✅ call/callSilent |
| 内置命令 (10个) | ✅ list/help/migrate/seed/serve/test/cache:clear/config:cache/config:clear/make:seeder |
| **make: 命令族** | ❌ 无 make:model/make:controller/make:migration/make:middleware 等 |
| **事件钩子** | ❌ 无 before/after 命令事件 |

### ORM / Model — 完成度 ~80%

**文件：** `bin/Database/Model.php` (665 行) + 5 个 Trait + `Relations/` (13 个类)

| 功能 | 状态 |
|------|------|
| 11 种关联 | ✅ hasOne/hasMany/belongsTo/belongsToMany/hasOneThrough/hasManyThrough/morphOne/morphMany/morphTo/morphToMany/morphedByMany |
| Eager Loading | ✅ with() + 嵌套 |
| 模型事件 | ✅ creating/created/updating/updating/deleting/deleting/saving/saved |
| Observer | ✅ observe() |
| 软删除 | ✅ SoftDeletes trait |
| 时间戳 | ✅ HasTimestamps |
| 集合 | ✅ Collection |
| 序列化 | ✅ toArray/toJson/hidden/visible/append |
| Factory | ✅ Factory |
| 分页方法 | ✅ 通过 QueryBuilder 代理 |
| **Model::findOr()** | ❌ |
| **Model::firstOr()** | ❌ |
| **firstOrCreate / firstOrNew** | ❌ |
| **updateOrCreate** | ❌ |
| **Model::upsert()** | ❌ |
| **Lazy Eager Loading** | ❌ 无 load() |
| **accessor/setter 转换** | ⚠️ HasAttributes 有但需验证完整性 |
| **模型复制 replicate()** | ❌ |

### IoC Container — 完成度 ~85%

**文件：** `bin/Container/Container.php` + `ContextualBindingBuilder.php`

| 功能 | 状态 |
|------|------|
| bind/singleton/instance/scoped | ✅ |
| 上下文绑定 | ✅ when()->needs()->give() |
| Tag | ✅ tag()/tagged() |
| 自动注入 | ✅ 反射解析 |
| resolving/rebinding | ✅ |
| 循环依赖检测 | ✅ |
| 方法注入 | ✅ call() |
| PSR-11 | ✅ ContainerInterface |
| **Registry / ContainerInterface 别名** | ⚠️ 简化版 |
| **scoped 重置机制** | ⚠️ 需验证生命周期 |

---

## 第二梯队：骨架存在但有明显短板 (40-60%)

### Routing — 完成度 ~45%

**文件：** `bin/Route/RouteCollection.php` + `RouteAction.php` + `Route.php`

| 功能 | 状态 | 说明 |
|------|------|------|
| HTTP 方法注册 | ✅ | GET/POST/PUT/PATCH/DELETE/OPTIONS/HEAD/match/any |
| 命名路由 | ✅ | name() + url() |
| 正则约束 | ✅ | with() |
| 路由组 | ⚠️ | 仅 prefix + middleware，**无 namespace/domain** |
| **RESTful Resource** | ❌ | 无 Route::resource() / apiResource() |
| **Route Model Binding** | ❌ | 无自动解析 Model |
| **路由缓存** | ❌ | 无 route:cache |
| **Signed Routes** | ❌ | 无签名 URL |
| **Fallback Routes** | ❌ | |
| **路由限流** | ❌ | 无 throttle 中间件集成 |
| **路由重定向** | ❌ | 无 Route::redirect() / permanentRedirect() |
| **视图路由** | ❌ | 无 Route::view() |
| **子域名路由** | ❌ | |

### Auth — 完成度 ~50%

**文件：** `bin/Auth/` (8 个文件)

| 功能 | 状态 |
|------|------|
| Session 认证 | ✅ |
| Token 认证 | ✅ |
| Gate 授权 | ✅ |
| Policy | ✅ |
| RBAC | ✅ |
| 密码哈希 | ✅ HashManager |
| 密码重置 | ✅ PasswordResetManager |
| 速率限制 | ✅ RateLimiter + Throttle |
| **OAuth / Socialite** | ❌ |
| **API Token (Sanctum/Passport)** | ❌ |
| **邮箱验证** | ❌ |
| **Multi-Auth Guard** | ❌ |
| **Remember Me** | ❌ |
| **密码确认 (password.confirm)** | ❌ |

### Config — 完成度 ~30%

**文件：** `config/` 仅 3 个文件

| 现有配置 | 缺失配置 |
|----------|----------|
| app.php (name/env/debug/url/timezone) | ❌ cache.php |
| auth.php (provider model) | ❌ session.php |
| database.php (mysql connection) | ❌ logging.php |
| | ❌ mail.php |
| | ❌ queue.php |
| | ❌ filesystems.php |
| | ❌ hashing.php |
| | ❌ cors.php |
| | ❌ broadcasting.php |
| | ❌ services.php |
| | ❌ app.providers (ServiceProvider 注册) |

### Error Pages — 完成度 ~50%

| 页面 | 状态 |
|------|------|
| 401/403/404/500 | ✅ 有样式但纯静态，无 debug 信息 |
| debug.php | ✅ 异常类名/消息/文件行号/堆栈着色 |
| **代码上下文片段** | ❌ 无错误行附近代码显示 |
| **环境变量 dump** | ❌ |
| **Request/Session dump** | ❌ |
| **可折叠堆栈帧** | ❌ |

---

## 第三梯队：名存实亡 / 基本缺失 (<20%)

### Validation — 完成度 ~5%

**文件：** `bin/Validation/Validator.php` (147 行) + `ValidationManager.php`

> **本质上是输入清理工具，不是验证引擎。**

| 现有功能 (8个清理方法) | 缺失功能 |
|------------------------|----------|
| escape (XSS) | ❌ 60+ 验证规则 (required/min/max/confirmed/email/unique/exists/...) |
| string/int/url/email | ❌ 错误消息 Bag |
| array | ❌ FormRequest 验证 |
| likePattern | ❌ 自定义规则对象 |
| fromRequest | ❌ 条件验证 / sometimes |
| escapeArray | ❌ 验证消息本地化 |
| | ❌ 规则构建器 (Rule::*) |
| | ❌ 数据准备 (prepareForValidation) |
| | ❌ after 钩子 |
| | ❌ exclude/validateSometimesWith |

### View / Template — 完成度 ~5%

**文件：** `bin/View/View.php` + `Compiler.php`

> **整个模板系统 = extract() + require_once**

| 功能 | 状态 |
|------|------|
| 变量传递 (with) | ✅ |
| **模板继承 @extends** | ❌ |
| **区块 @section / @yield** | ❌ |
| **包含 @include** | ❌ |
| **组件 @component / @slot** | ❌ |
| **栈 @stack / @push** | ❌ |
| **@csrf** | ❌ |
| **@method** | ❌ |
| **@auth / @guest** | ❌ |
| **@error** | ❌ |
| **@foreach / @for / @while** | ❌ |
| **@if / @else / @elseif** | ❌ |
| **@isset / @empty** | ❌ |
| **@switch** | ❌ |
| **@once** | ❌ |
| **@php / @endphp** | ❌ |
| **{{ }} / {!! !!}** | ❌ |
| **视图 Composer** | ❌ |
| **视图 Creator** | ❌ |
| **Blade 编译缓存** | ❌ |

### Middleware — 完成度 ~10%

**文件：** `bin/Middleware/Middleware.php` (38 行)

> **无 Pipeline，无生命周期**

| 功能 | 状态 |
|------|------|
| 抽象 handle() 方法 | ✅ |
| **Middleware Pipeline** | ❌ 无洋葱模型 |
| **全局 Middleware** | ❌ |
| **Middleware 组** | ❌ |
| **Middleware 优先级** | ❌ |
| **terminate() 生命周期** | ❌ |
| **Middleware 参数** | ❌ |
| **before/after 区分** | ❌ |
| **可排除 Middleware** | ❌ withoutMiddleware() |

### Facade — 完成度 ~15%

**文件：** `bin/Facade/Facade.php` + `Event.php` + `Request.php`

| 现有 Facade | Laravel 有但 First 缺失 |
|-------------|------------------------|
| Event | ❌ Cache / Config / DB / Hash |
| Request | ❌ Log / Mail / Queue / Route |
| | ❌ Session / Storage / URL / View |
| | ❌ Auth / Gate / Validator |
| | ❌ App / Artisan / File |
| | ❌ HTTP (客户端) / Schema |

---

## 第四梯队：完全不存在 (0%)

### 生态级功能

| 功能模块 | Laravel 对应 | 说明 |
|----------|-------------|------|
| **Queue / Job** | `illuminate/queue` | 异步任务、失败重试、Queue Worker、Job Dispatch |
| **Mail** | `illuminate/mail` | Markdown Mailable、多驱动 (smtp/sendmail/mailgun/ses) |
| **Notification** | `illuminate/notifications` | 多渠道通知 (mail/sms/slack/database) |
| **Broadcasting** | `illuminate/broadcasting` | WebSocket 事件推送、Pusher/Redis pub-sub |
| **Filesystem** | `illuminate/filesystem` | 磁盘抽象 (local/s3/ftp/sftp)、Storage facade |
| **Task Scheduling** | `illuminate/console` Scheduling | Cron 调度器、schedule:run |
| **Localization** | `illuminate/translation` | 多语言、__() / @lang |
| **HTTP Client** | `illuminate/http` Client | Guzzle 封装、Pool/Retry/Middleware |
| **Encryption** | `illuminate/encryption` | 独立加密模块 (AES-256-CBC/GCM) |
| **Telescope** | laravel/telescope | 调试监控面板 |
| **Horizon** | laravel/horizon | Queue 监控仪表盘 |
| **Sanctum** | laravel/sanctum | SPA/API Token 认证 |
| **Passport** | laravel/passport | OAuth2 服务端 |
| **Socialite** | laravel/socialite | OAuth 社交登录 |
| **Scout** | laravel/scout | 全文搜索 (Algolia/Meilisearch) |
| **Cashier** | laravel/cashier | Stripe/Paddle 支付 |
| **Dusk** | laravel/dusk | 浏览器自动化测试 |
| **Pennant** | laravel/pennant | Feature Flags |
| **Pulse** | laravel/pulse | 性能监控 |
| **Reverb** | laravel/reverb | WebSocket 服务端 |

---

## 改进优先级建议 (按 ROI 排序)

### 高 ROI — 影响日常开发体验

| 优先级 | 模块 | 预估工作量 | 理由 |
|--------|------|-----------|------|
| **P0** | Validation 规则引擎 | 大 | 每个表单/API 都需要，当前完全缺失 |
| **P0** | Blade 模板引擎 | 大 | View 层是 MVC 核心，当前只是 include |
| **P1** | Middleware Pipeline | 中 | 请求处理架构的脊梁 |
| **P1** | Route::resource() | 小 | 减少 CRUD 路由的重复代码 |
| **P1** | Route Model Binding | 中 | 大幅减少 Controller 样板代码 |
| **P2** | FormRequest | 中 | 配合 Validation 的请求级验证 |
| **P2** | Config 完善 | 小 | 让各模块行为可配置化 |

### 中 ROI — 中大型项目必需

| 优先级 | 模块 | 理由 |
|--------|------|------|
| **P3** | Filesystem 磁盘抽象 | 文件存储的基础抽象 |
| **P3** | HTTP Client | API 调用是常见需求 |
| **P3** | Localization | 多语言支持 |
| **P4** | Queue / Job | 异步处理能力 |
| **P4** | Mail | 邮件通知 |
| **P4** | Task Scheduling | 定时任务 |

### 低 ROI — 生态差异化（可选）

| 优先级 | 模块 |
|--------|------|
| **P5** | Notification |
| **P5** | Broadcasting |
| **P5** | Sanctum/Passport |
| **P5** | Telescope/Horizon |

---

## 面积对比图

```
                Laravel          First           差距
                ────────         ──────          ────
  QueryBuilder  ████████████     ██████████░░    ~15%
  Console       ████████████     █████████░░░    ~20%
  ORM/Model     ████████████     █████████░░░    ~20%
  Container     ████████████     █████████░░░    ~15%
  Auth          ████████████     ██████░░░░░░    ~50%
  Routing       ████████████     █████░░░░░░░    ~55%
  Config        ████████████     ███░░░░░░░░░    ~70%
  Error Pages   ████████████     █████░░░░░░░    ~50%
  Middleware    ████████████     █░░░░░░░░░░░    ~90%
  Validation    ████████████     ░░░░░░░░░░░░    ~95%
  View/Blade    ████████████     ░░░░░░░░░░░░    ~95%
  Facades       ████████████     █░░░░░░░░░░░    ~90%
  Queue         ████████████     ░░░░░░░░░░░░   100%
  Mail          ████████████     ░░░░░░░░░░░░   100%
  Notification  ████████████     ░░░░░░░░░░░░   100%
  Filesystem    ████████████     ░░░░░░░░░░░░   100%
  Broadcasting  ████████████     ░░░░░░░░░░░░   100%
  Scheduling    ████████████     ░░░░░░░░░░░░   100%
  Localization  ████████████     ░░░░░░░░░░░░   100%
  HTTP Client   ████████████     ░░░░░░░░░░░░   100%
```

---

## 附录：测试覆盖现状

| 模块 | 测试文件 | 测试数 |
|------|----------|--------|
| QueryBuilder | QueryBuilderTest.php | ~200 |
| ORM/Model | ModelTest.php + RelationTest.php | ~100 |
| Container | ContainerTest.php + IocBehaviorTest.php | ~80 |
| Auth | AuthTest.php + GateTest.php + RateLimiterTest.php | ~70 |
| Pagination | PaginatorTest.php | 45 |
| Soft Deletes | SoftDeletesTest.php | 22 |
| Cookie/Upload | CookieUploadTest.php | 22 |
| Console | ConsoleTest.php | ~30 |
| Validation | — | **0** |
| View/Blade | — | **0** |
| Middleware | — | **0** |
| **总计** | **51 个测试文件** | **~1085** |
