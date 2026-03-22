# TODO - Framework Development Roadmap

## 已完成 ✅

| 功能 | 状态 | 文件 |
|------|------|------|
| Reflection (反射) | ✅ | `bin/Reflection/Reflection.php`, `bin/Reflection/Reflect.php` |
| Autoload (自动加载) | ✅ | `bin/autoload.php`, `composer.json` |
| Request (请求处理) | ✅ | `bin/Request/Request.php` |
| Response (响应处理) | ✅ | `bin/Response/Response.php` |
| Middleware (中间件) | ✅ | `bin/Middleware/Middleware.php`, `app/Middleware/` |
| IoC 容器 | ✅ | `bin/App/App.php` |
| DI (依赖注入) | ✅ | `bin/Reflection/Reflection.php` |
| View Template (视图模板) | ✅ | `bin/View/View.php`, `bin/View/Compiler.php` |
| Helper Functions (辅助函数) | ✅ | `bin/Func/helpers.php` |
| DB Model (数据库模型) | ✅ | `bin/Model/Model.php`, `bin/Model/Connection.php` |
| Route Pick (路由正则匹配) | ✅ | `bin/Route/Route.php` |
| Route Collection (路由集合) | ✅ | `bin/Route/RouteCollection.php` |
| Route Group (路由分组) | ✅ | `Route::middle()` 实现 |
| Facade Pattern | ✅ | `bin/Facade/Facade.php`, `bin/Facade/Request.php` |
| Route Action Dispatch | ✅ | `bin/Route/RouteAction.php` |
| 安全性修复 (SQL注入) | ✅ | `bin/Model/Model.php` |
| 输入验证器 | ✅ | `bin/Validation/Validator.php` |
| CSRF 防护中间件 | ✅ | `bin/Middleware/CsrfMiddleware.php` |
| 配置缓存 | ✅ | `bin/Config/ConfigCache.php` |
| 路由优化 (静态索引) | ✅ | `bin/Route/RouteCollection.php` |
| 反射缓存 | ✅ | `bin/Reflection/Reflect.php` |
| 接口抽象 | ✅ | `bin/Contracts/*.php` |

## 2026-03-16 测试系统完成 ✅

### 测试框架 (`bin/Testing/`)
- `TestCase` - 测试用例基类，支持 40+ 断言方法
- `TestRunner` - 测试运行器
- `TestResult` - 测试结果
- `TestSummary` - 测试摘要
- `TestSuite` - 数据库测试基类
- `Mock` - 模拟对象系统
- `MethodExpectation` - 方法期望

### 断言支持
- 相等: `assertEquals()`, `assertSame()`, `assertNotEquals()`, `assertNotSame()`
- 布尔: `assertTrue()`, `assertFalse()`
- 空值: `assertNull()`, `assertNotNull()`, `assertEmpty()`, `assertNotEmpty()`
- 数组: `assertArrayHasKey()`, `assertArrayNotHasKey()`, `assertCount()`, `assertContains()`
- 字符串: `assertStringContainsString()`, `assertStringStartsWith()`, `assertStringEndsWith()`
- 数值: `assertGreaterThan()`, `assertLessThan()`, `assertGreaterThanOrEqual()`
- 类型: `assertInstanceOf()`, `assertIsType()`
- 文件: `assertFileExists()`, `assertDirectoryExists()`
- 异常: `assertThrows()`
- 其他: `markTestSkipped()`, `markTestIncomplete()`, `fail()`

### 命令行工具
- `php test` - 运行所有测试
- `php test --verbose` - 详细输出
- `php test --stop-on-failure` - 失败时停止
- `php test tests/ExampleTest.php` - 运行指定文件

### 示例测试
- `tests/ExampleTest.php` - 基础示例
- `tests/CollectionTest.php` - 集合测试
- `tests/QueryBuilderTest.php` - 查询构建器测试

## 2026-03-16 ORM 系统完成 ✅

### 查询构建器 (`bin/Database/QueryBuilder.php`)
- 基础查询：`select()`, `where()`, `orWhere()`, `whereIn()`, `whereNotIn()`
- 空值查询：`whereNull()`, `whereNotNull()`, `whereBetween()`, `whereLike()`
- 日期查询：`whereDate()`, `whereDay()`, `whereMonth()`, `whereYear()`, `whereTime()`
- 连接查询：`join()`, `leftJoin()`, `rightJoin()`
- 排序：`orderBy()`, `latest()`, `oldest()`, `inRandomOrder()`
- 聚合：`count()`, `sum()`, `avg()`, `min()`, `max()`
- 增删改：`insert()`, `insertGetId()`, `update()`, `delete()`, `increment()`, `decrement()`
- 渴望加载：`with()`

### 模型基类 (`bin/Database/Model.php`)
- CRUD：`create()`, `save()`, `update()`, `delete()`
- 查询：`find()`, `findOrFail()`, `findOrNew()`, `all()`
- 属性：`fillable`, `guarded`, `hidden`, `visible`, `casts`
- 时间戳：自动管理 `created_at`, `updated_at`
- 状态检查：`isDirty()`, `isClean()`, `wasRecentlyCreated()`
- 序列化：`toArray()`, `toJson()`

### 集合类 (`bin/Database/Collection.php`)
- 过滤：`filter()`, `reject()`, `where()`, `whereIn()`
- 变换：`map()`, `pluck()`, `groupBy()`, `keyBy()`
- 排序：`sortBy()`, `reverse()`, `shuffle()`
- 聚合：`count()`, `sum()`, `avg()`, `min()`, `max()`
- 检查：`contains()`, `isEmpty()`, `isNotEmpty()`

### 关系系统 (`bin/Database/Relations/`)
- `HasOne` - 一对一关系
- `HasMany` - 一对多关系
- `BelongsTo` - 反向一对一/多对一关系
- `BelongsToMany` - 多对多关系
- 渴望加载支持

## 2026-03-16 Migration 系统完成 ✅

### Schema Builder (`bin/Database/Schema/`)
- `Blueprint` - 表结构蓝图，支持 30+ 列类型
- `ColumnDefinition` - 列定义，支持修饰符
- `ForeignKey` - 外键约束
- `SchemaBuilder` - SQL 构建/执行
- `Schema` - 静态门面

### 列类型支持
- 整数: `id()`, `bigInteger()`, `integer()`, `tinyInteger()`, `smallInteger()`, `mediumInteger()`
- 字符串: `string()`, `text()`, `longText()`, `mediumText()`, `tinyText()`
- 小数: `decimal()`, `double()`, `float()`
- 日期: `date()`, `dateTime()`, `time()`, `timestamp()`, `year()`
- 其他: `boolean()`, `enum()`, `json()`, `uuid()`, `ipAddress()`, `geometry()`

### 迁移系统 (`bin/Database/Migrations/`)
- `Migration` - 迁移基类
- `Migrator` - 迁移执行器
- `MigrationCreator` - 迁移文件生成器
- `MigrateCommand` - 命令行工具

### 命令行工具
- `php migrate migrate` - 运行迁移
- `php migrate rollback` - 回滚
- `php migrate reset` - 重置
- `php migrate refresh` - 刷新
- `php migrate fresh` - 清空重建
- `php migrate status` - 查看状态
- `php migrate make:xxx` - 创建迁移

### 示例迁移
- `database/migrations/create_users_table.php`
- `database/migrations/create_posts_table.php`
- `database/migrations/create_comments_table.php`
- `database/migrations/create_roles_table.php`
- `database/migrations/create_role_user_table.php`

## 2026-03-16 框架优化完成 ✅

### 安全性修复
- SQL 注入漏洞修复 (`bin/Model/Model.php`)
- 新增输入验证器 (`bin/Validation/Validator.php`)
- 新增 CSRF 防护中间件 (`bin/Middleware/CsrfMiddleware.php`)

## 2026-03-17 IoC 容器与服务提供者系统完成 ✅

### 完整 IoC 容器 (`bin/Container/Container.php`)
- 服务绑定: `bind()`, `singleton()`, `instance()`
- 自动依赖注入: 通过反射自动解析构造函数依赖
- 别名系统: 支持多层别名解析
- 上下文绑定: 根据使用场景注入不同实现
- 扩展器: `extend()` 修改已解析的实例
- Mock 支持: `mock()` 用于测试
- 回调调用: `call()` 支持依赖注入的闭包调用
- 构建堆栈: 检测循环依赖
- 批量操作: `bindArray()`, `singletonArray()`, `instanceArray()`

### 应用程序重构 (`bin/App/App.php`)
- 集成新 Container 作为底层实现
- 支持服务提供者系统
- 延迟服务提供者支持
- 保持向后兼容的 API
- 应用启动: `boot()` 方法

### 服务提供者系统 (`bin/Providers/`)
- `ServiceProvider` - 服务提供者基类
- `ProviderRepository` - 提供者管理仓库
- 核心服务提供者:
  - `RequestServiceProvider` - 请求服务
  - `ResponseServiceProvider` - 响应服务
  - `RoutingServiceProvider` - 路由服务
  - `DatabaseServiceProvider` - 数据库服务
  - `ViewServiceProvider` - 视图服务

### Facade 改进 (`bin/Facade/Facade.php`)
- 支持容器注入
- 实例缓存管理
- 测试友好的 API
- `Request` Facade 增强方法

## 2026-03-17 异常处理系统完成 ✅

### 异常处理器 (`bin/Exception/ExceptionHandler.php`)
- 全局异常捕获和处理
- 错误到异常转换
- 调试模式支持
- 自定义报告和渲染回调
- AJAX/JSON 响应支持
- 关闭时错误处理

### 自定义异常类 (`bin/Exception/`)
- `HttpException` - HTTP 异常基类
- `NotFoundHttpException` - 404 错误
- `AuthenticationException` - 401 未授权
- `AuthorizationException` - 403 禁止访问
- `ValidationException` - 422 验证失败

### 错误视图 (`views/errors/`)
- `404.php` - 精美的 404 错误页
- `500.php` - 服务器错误页
- `401.php` - 未授权错误页
- `403.php` - 禁止访问错误页
- `debug.php` - 调试错误详情页

## 2026-03-17 配置管理系统完成 ✅

### 配置仓库 (`bin/Config/ConfigRepository.php`)
- 配置读取: `get()`, `has()`, `all()`
- 配置写入: `set()`, `save()`, `saveAll()`, `saveImmediately()`
- 嵌套配置访问: 支持 `app.database.default` 语法
- 配置缓存: 自动缓存已读取的配置
- 变更追踪: 追踪未保存的变更
- 宏支持: 扩展配置功能
- 预加载: `preload()` 批量加载配置

### 辅助函数增强 (`bin/Func/helpers.php`)
- `config()` 函数增强:
  - 读取配置: `config('app.name')`
  - 设置配置: `config('app.name', 'value')`
  - 立即保存: `config('app.name', 'value', true)`
  - 获取仓库: `config()` 返回 ConfigRepository
  - 批量设置: `config(['key' => 'value'])`

### 测试覆盖
- `ContainerTest` - 40+ 容器测试用例
- `AppTest` - 50+ 应用测试用例
- `ExceptionHandlerTest` - 21 异常处理测试
- `ConfigRepositoryTest` - 19 配置仓库测试
- `CacheTest` - 24 缓存系统测试
- `LoggerTest` - 8 日志系统测试
- `SessionTest` - 25 Session 系统测试
- `ValidationTest` - 40 验证系统测试
- **总计 227 个测试全部通过**

### 代码质量提升
- 清理调试代码和注释代码
- 修复 Middleware 不可达代码
- 完善类型声明 (所有核心类)
- 修复单例模式实现 (`bin/App/App.php`)

### 性能优化
- 路由匹配优化：静态路由 O(1) 索引查找
- 配置缓存实现 (`bin/Config/ConfigCache.php`)
- 反射结果缓存 (`bin/Reflection/Reflect.php`)

### 架构改进
- 新增接口抽象 (`bin/Contracts/`)
  - `ContainerInterface` - IoC 容器接口
  - `RouterInterface` - 路由器接口
  - `ViewInterface` - 视图接口

## 2026-03-17 缓存系统完成 ✅

### 缓存接口 (`bin/Cache/CacheRepository.php`)
- PSR-16 兼容的缓存接口
- 基础操作: `get()`, `set()`, `delete()`, `clear()`
- 批量操作: `getMultiple()`, `setMultiple()`, `deleteMultiple()`
- 便利方法: `has()`, `pull()`, `remember()`, `getOrSet()`

### 缓存驱动 (`bin/Cache/`)
- `ArrayStore` - 内存缓存（请求级别）
- `FileStore` - 文件缓存，支持 TTL
- `NullStore` - 空缓存（测试用）
- `RedisStore` - Redis 缓存驱动

### 缓存管理器 (`bin/Cache/CacheManager.php`)
- 多驱动支持
- 默认存储配置
- 动态切换存储
- 辅助函数: `cache()`, `remember()`, `cache_forever()`, `cache_forget()`

### 计数器支持
- `increment()` - 增加缓存值
- `decrement()` - 减少缓存值
- `forever()` - 永久存储

## 2026-03-17 日志系统完成 ✅

### 日志记录器 (`bin/Log/Logger.php`)
- PSR-3 兼容的日志接口
- 8 个日志级别: debug, info, notice, warning, error, critical, alert, emergency
- 上下文数据支持
- 日志级别过滤
- 异常记录: `exception()`
- SQL 查询记录: `query()`

### 日志管理器 (`bin/Log/LogManager.php`)
- 多通道支持
- 默认通道配置
- 静态快捷方法
- 自定义通道注册: `registerChannel()`

### 辅助函数 (`bin/Func/helpers.php`)
- `logger($channel)` - 获取指定通道的 Logger
- `info()` - 快捷记录 info 日志
- `error()` - 快捷记录 error 日志

## 2026-03-17 Session 管理系统完成 ✅

### Session 接口 (`bin/Session/SessionInterface.php`)
- PSR 兼容的 Session 接口
- 基础操作: `get()`, `set()`, `has()`, `remove()`, `pull()`, `clear()`
- Flash 消息: `flash()`, `getFlash()`, `pullFlash()`, `hasFlash()`, `reflash()`
- 会话管理: `start()`, `save()`, `destroy()`, `regenerate()`
- CSRF 集成: `putCsrfToken()`, `getCsrfToken()`, `verifyCsrfToken()`
- 旧输入: `flashInput()`, `getOldInput()`

### Session 管理器 (`bin/Session/SessionManager.php`)
- 完整的 Session 管理实现
- 点号分隔的键访问支持
- 自动 Flash 数据老化
- CLI 测试环境兼容
- 可配置的生命周期和 Flash 键名

### Session 驱动 (`bin/Session/`)
- `FileSessionHandler` - 文件存储驱动
- `DatabaseSessionHandler` - 数据库存储驱动
- `RedisSessionHandler` - Redis 存储驱动

### Session Facade (`bin/Session/SessionFacade.php`)
- 静态访问所有 Session 方法
- CSRF Token 快捷方法
- 旧输入快捷方法

### 辅助函数 (`bin/Func/helpers.php`)
- `session_manager()` - 获取 Session 管理器
- `session_get()`, `session_set()` - 获取/设置 Session
- `session_has()`, `session_forget()`, `session_pull()` - 操作 Session
- `flash()`, `flash_get()`, `flash_pull()`, `flash_has()` - Flash 消息
- `flash_all()`, `flash_clear()`, `flash_reflash()` - Flash 批量操作
- `session_id()`, `session_regenerate()`, `session_destroy()` - 会话管理
- `with_old_input()` - 保存输入值

## 2026-03-17 验证系统完成 ✅

### 验证管理器 (`bin/Validation/ValidationManager.php`)
- 完整的表单验证系统
- 30+ 内置验证规则
- 自定义规则支持
- 点号分隔的键访问支持
- 字段别名和自定义错误消息
- Session Flash 集成（自动保存错误和旧输入）
- 验证异常支持

### 验证规则
- 必填: `required`, `filled`, `nullable`
- 类型: `string`, `integer`, `numeric`, `boolean`, `array`
- 格式: `email`, `url`, `ip`, `json`, `date`
- 字符: `alpha`, `alpha_num`, `alpha_dash`
- 长度: `min`, `max`, `between`, `size`
- 比较: `in`, `not_in`, `same`, `different`, `gt`, `lt`, `gte`, `lte`
- 模式: `regex`, `starts_with`, `ends_with`
- 确认: `confirmed`

### 验证异常 (`bin/Exception/ValidationException.php`)
- 422 状态码
- 批量错误消息支持

### 原有验证器 (`bin/Validation/Validator.php`)
- XSS 防护
- SQL 注入防护
- 输入清理和转义
- 基本验证方法

## 待完成 🚧

### 核心功能

- [ ] **Unit Testing Framework** ✅ (已完成)
  - [ ] Code Coverage 报告
  - [ ] Test Data Fixtures
  - [ ] Parallel Testing

- [ ] **Cache (缓存)** ✅ (已完成)
  - [x] Cache 接口定义
  - [x] 文件缓存驱动
  - [x] Redis 缓存驱动
  - [x] Memcached 缓存驱动 (可选)
  - [x] `remember()` 辅助方法
  - [ ] Tagging 支持

- [ ] **Session (会话)** ✅ (已完成)
  - [x] Session 管理
  - [x] 文件 Session 驱动
  - [x] 数据库 Session 驱动
  - [x] Redis Session 驱动
  - [x] Flash 消息
  - [x] CSRF Token 集成

- [ ] **Log (日志)** ✅ (已完成)
  - [x] Logger 接口
  - [x] 文件日志通道
  - [x] 日志级别 (debug, info, warning, error)
  - [ ] 日志轮转
  - [x] 上下文数据支持

- [ ] **Exception (异常处理)** ✅ (已完成)
  - [ ] 全局异常处理器
  - [ ] 自定义异常类
  - [ ] 异常视图渲染
  - [ ] 错误页面 (404, 500等)
  - [ ] Whoops 集成 (可选)

### CLI 模式

- [ ] **PHP CLI Mode** ✅ (已完成)
  - [x] Artisan 风格命令行工具
  - [x] 命令注册系统
  - [x] 参数解析
  - [x] 交互式输入/输出
  - [ ] 任务调度 (Cron)

## 2026-03-18 CLI 命令行工具系统完成 ✅

### Console 命令系统 (`bin/Console/`)
- `Command` - 命令基类，支持签名解析
- `Kernel` - 命令调度器，支持注册和发现
- `Input` - 输入解析器，支持参数和选项
- `Output` - 输出处理器，支持 ANSI 颜色和格式化
- `ProgressBar` - 进度条组件

### 输入/输出功能
- 输入解析: 位置参数、短选项、长选项、等号语法
- 输出格式: `info()`, `success()`, `warning()`, `error()`, `comment()`
- 格式化输出: `table()`, `json()`, `list()`, `taskList()`
- 交互输入: `confirm()`, `ask()`, `choice()`, `secret()`
- 进度条: 支持消息、步进、自动完成

### 内置命令 (`bin/Console/Commands/`)
- `ListCommand` - 列出所有命令
- `HelpCommand` - 显示帮助信息
- `ClearCacheCommand` - 清除缓存
- `ServeCommand` - 启动开发服务器
- `MigrateCommand` - 运行数据库迁移
- `TestCommand` - 运行测试套件

### 命令特性
- 签名解析: `command {arg} {arg?} {--option} {--option=*}`
- 别名支持: `Kernel::alias('short', 'long:command')`
- 工厂模式: 延迟实例化命令
- 命令发现: 自动扫描命令目录
- 命令调用: `call()` 和 `callSilent()`

### 命令行工具
- `php command` - 运行 CLI
- `php command list` - 列出命令
- `php command help [command]` - 显示帮助

### 配置功能

- [ ] **Config Write**
  - [ ] `config()` 函数支持写入配置
  - [ ] 环境变量支持 (.env)
  - [ ] 配置缓存

### 验证功能

- [ ] **Validation** ✅ (已完成)
  - [x] 验证器类
  - [x] 常用验证规则 (30+)
  - [x] 自定义规则
  - [x] 错误消息显示
  - [x] 字段别名
  - [x] Session Flash 集成

### 数据库功能增强

- [ ] **Seeders (数据填充)** ✅ (已完成)
  - [x] Seeder 类
  - [x] 批量数据插入
  - [x] SeederFactory 工厂模式
  - [x] 状态和回调支持

## 2026-03-18 Seeder 数据填充系统完成 ✅

### Seeder 基类 (`bin/Database/Seeders/Seeder.php`)
- `run()` - 执行填充
- `call()` - 调用其他 Seeder
- `create()` - 创建单个模型实例
- `createMany()` - 批量创建模型实例
- `factory()` - 获取 SeederFactory 实例

### SeederFactory (`bin/Database/Seeders/SeederFactory.php`)
- 状态定义: `state()` - 定义数据状态
- 创建回调: `afterMaking()`, `afterCreating()`
- 实例创建: `make()`, `create()`, `createMany()`
- 状态应用: `withStates()` - 应用多个状态
- 属性设置: 支持 `setRawAttributes()` 和直接属性设置

### Seeder 管理器 (`bin/Database/Seeders/SeederRepository.php`)
- 注册: `register()`, `registerMany()`
- 发现: `discover()` - 自动扫描 seeder 目录
- 执行: `run()`, `runAll()`
- 路径管理: `addPath()`, `setAppNamespace()`

### Seeder 创建器 (`bin/Database/Seeders/SeederCreator.php`)
- 生成 seeder 文件模板
- 自动命名空间处理
- 使用说明提示

### CLI 命令
- `php command db:seed` - 运行所有 seeders
- `php command db:seed {name}` - 运行指定 seeder
- `php command db:seed {name} --force` - 强制执行（不确认）
- `php command make:seeder {name}` - 创建新 seeder

### 示例 Seeder (`database/seeders/UserSeeder.php`)
- 管理员用户创建
- 工厂模式批量创建
- 状态支持（用户/编辑角色）

- [ ] **Query Builder 增强功能**
  - [x] 分页 (`paginate()`)
  - [ ] 子查询
  - [x] 软删除支持
  - [x] 模型事件

### 认证与授权

- [ ] **Auth (认证)** ✅ (已完成)
  - [x] 用户认证系统
  - [x] 哈希/加密
  - [x] Remember Me
  - [x] 密码重置

- [ ] **Authorization (授权)** ✅ (已完成)
  - [x] Gate/Facade
  - [x] Policy
  - [x] RBAC (角色权限)

## 2026-03-18 Authorization 授权系统完成 ✅

### Gate 授权管理器 (`bin/Auth/Gate.php`)
- `define()` - 定义授权能力（闭包）
- `check()` - 检查权限
- `allows()` / `denies()` - 权限检查别名
- `any()` / `all()` - 批量权限检查
- `policy()` - 注册策略类
- 策略自动发现
- 用户解析器支持

### Policy 策略基类 (`bin/Auth/Policy.php`)
- 面向对象的授权策略
- `before()` - 预检查钩子
- `hasAdminRole()` - 管理员角色检查
- `hasRole()` / `hasAnyRole()` - 角色检查
- `isOwner()` - 所有权检查

### RBAC 基于角色的访问控制 (`bin/Auth/Rbac.php`)
- `defineRole()` - 定义角色和权限
- `assignRole()` / `removeRole()` - 用户角色管理
- `hasPermission()` / `hasRole()` - 权限/角色检查
- `setInheritance()` - 角色继承
- 通配符权限 (`*`)
- `addPermissionToRole()` / `removePermissionFromRole()` - 权限管理

### 辅助函数 (`bin/Func/helpers.php`)
- `can()` / `cannot()` - Gate 权限检查
- `allows()` / `denies()` - Gate 权限检查别名
- `has_permission()` - RBAC 权限检查
- `has_role()` / `has_any_role()` - RBAC 角色检查
- `gate()` - 获取 Gate 实例

## 2026-03-18 Auth 认证系统完成 ✅

### 哈希管理器 (`bin/Auth/HashManager.php`)
- `make()` - 哈希密码
- `check()` - 验证密码
- `needsRehash()` - 检查是否需要重新哈希
- `getInfo()` - 获取哈希信息
- `bcrypt()` - Bcrypt 算法哈希
- `argon2i()` - Argon2i 算法哈希
- `argon2id()` - Argon2id 算法哈希

### 认证管理器 (`bin/Auth/AuthManager.php`)
- `attempt()` - 尝试登录
- `login()` - 登录用户
- `logout()` - 登出用户
- `check()` - 检查是否已认证
- `guest()` - 检查是否是访客
- `user()` - 获取当前用户
- `id()` - 获取当前用户 ID
- `validate()` - 验证凭据但不登录
- `loginUsingId()` - 使用 ID 登录
- Remember Me 支持（30 天 cookie）

### 密码重置管理器 (`bin/Auth/PasswordResetManager.php`)
- `createToken()` - 生成重置令牌
- `validateToken()` - 验证令牌
- `deleteToken()` - 删除令牌
- `resetPassword()` - 重置密码
- `cleanExpiredTokens()` - 清理过期令牌
- 可配置令牌有效期（默认 1 小时）

### 认证中间件 (`bin/Middleware/AuthMiddleware.php`)
- `AuthMiddleware` - 保护需要认证的路由
- `GuestMiddleware` - 仅允许访客访问（如登录页）
- AJAX 请求支持（返回 JSON 401）
- 自定义重定向支持

### 辅助函数 (`bin/Func/helpers.php`)
- `auth()` - 获取当前用户
- `auth_check()` - 检查是否已认证
- `auth_guest()` - 检查是否是访客
- `auth_id()` - 获取当前用户 ID
- `auth_attempt()` - 尝试登录
- `auth_login()` - 登录用户
- `auth_logout()` - 登出用户
- `hash_make()` - 哈希密码
- `hash_check()` - 验证密码
- `hash_bcrypt()` - Bcrypt 哈希

### HTTP 增强

- [ ] **Cookie 管理** ✅ (已完成)
- [ ] **File Upload** ✅ (已完成)
- [ ] **Rate Limiting** ✅ (已完成)
- [ ] **API Resource** ✅ (已完成)

## 2026-03-18 Cookie 和文件上传系统完成 ✅

### CookieManager Cookie 管理器 (`bin/Cookie/CookieManager.php`)
- `set()` - 设置 Cookie，支持过期时间、路径、域等选项
- `get()` - 获取 Cookie 值，自动解密
- `has()` - 检查 Cookie 是否存在
- `forget()` / `forgetMultiple()` - 删除 Cookie
- `forever()` - 设置永久 Cookie（5 年）
- `setDefaults()` - 设置默认配置
- `setEncryptionKey()` - 设置/禁用加密密钥
- AES-256-CBC 加密支持
- 可配置的 SameSite 属性

### UploadedFile 文件上传 (`bin/Http/UploadedFile.php`)
- `createFromGlobal()` - 从 $_FILES 创建实例
- `createFromArray()` - 从数组创建实例（支持测试模式）
- `getClientOriginalName()` - 获取原始文件名
- `getClientOriginalExtension()` - 获取原始扩展名
- `getMimeType()` - 获取 MIME 类型
- `getSize()` - 获取文件大小
- `isValid()` - 检查上传是否有效
- `getErrorMessage()` - 获取错误消息
- `move()` / `store()` / `storeAs()` - 移动/存储文件
- `validate()` - 验证文件（大小、类型、扩展名）
- `hashName()` - 生成哈希文件名
- `delete()` / `exists()` - 文件操作
- `url()` - 获取文件 URL
- 测试模式支持（避免实际文件操作）

### 文件验证规则
- `max_size` - 最大文件大小
- `allowed_types` - 允许的 MIME 类型
- `allowed_extensions` - 允许的文件扩展名
- `isValidMimeType()` - MIME 类型验证
- `isValidExtension()` - 扩展名验证
- `isValidSize()` - 大小验证

### 辅助函数 (`bin/Func/helpers.php`)
- `cookie()` - 获取/设置 Cookie
- `cookie_has()` - 检查 Cookie 是否存在
- `cookie_forget()` - 删除 Cookie
- `cookie_forever()` - 设置永久 Cookie
- `uploaded_file()` - 获取上传文件实例
- `file_upload_validate()` - 验证上传文件

## 2026-03-18 Rate Limiting 速率限制系统完成 ✅

### RateLimiter 速率限制器 (`bin/Auth/RateLimiter.php`)
- `attempt()` - 尝试执行操作（检查是否超过限制）
- `remaining()` - 获取剩余尝试次数
- `availableIn()` - 获取重置时间（秒）
- `clear()` - 清除限制记录
- `attempts()` - 获取当前尝试次数
- `isLocked()` - 检查是否被限制
- `key()` - 生成限制键名
- 缓存持久化支持

### Throttle 节流器 (`bin/Auth/Throttle.php`)
- `api()` - API 速率限制（60次/分钟）
- `login()` - 登录尝试限制（5次/分钟）
- `register()` - 注册尝试限制（3次/小时）
- `passwordReset()` - 密码重置限制（3次/小时）
- `sms()` - 短信发送限制（10次/小时）
- `email()` - 邮件发送限制（20次/小时）
- `upload()` - 文件上传限制（10次/分钟）
- `custom()` - 自定义限制
- `byIp()` / `byUser()` - 基于IP/用户的限制
- `ip()` / `userId()` - 标识符获取器

### RateLimitMiddleware 中间件 (`bin/Middleware/RateLimitMiddleware.php`)
- 可配置最大尝试次数和时间窗口
- 支持多种标识符类型（ip, user, both）
- AJAX/JSON 响应支持
- 自定义重定向支持
- 速率限制响应头（X-RateLimit-*）
- 429 Too Many Requests 响应

### 辅助函数 (`bin/Func/helpers.php`)
- `rate_limiter()` - 获取速率限制器实例
- `throttle()` - 检查速率限制
- `rate_limit_remaining()` - 获取剩余尝试次数
- `rate_limit_clear()` - 清除速率限制

## 2026-03-18 API Resource 资源转换系统完成 ✅

### JsonResource 资源基类 (`bin/Resource/JsonResource.php`)
- `make()` - 创建资源实例
- `collection()` - 创建资源集合
- `toArray()` - 转换为数组
- `toJson()` - 转换为 JSON
- `toResponse()` - 转换为 HTTP 响应
- `with()` - 添加附加元数据
- `includes()` - 设置需要包含的资源关系
- `only()` - 设置只显示指定字段（白名单）
- `hide()` - 设置需要隐藏的字段（黑名单）
- `id()` - 获取资源 ID
- `exists()` - 检查资源是否存在
- `when()` - 条件数据
- `mergeWhen()` - 条件合并数组
- 实现 JsonSerializable 接口

### ResourceCollection 资源集合 (`bin/Resource/ResourceCollection.php`)
- `make()` - 创建资源集合
- `collect()` - 收集并转换所有资源
- `pagination()` - 设置分页信息
- `with()` / `includes()` / `only()` / `hide()` - 批量设置过滤选项
- `count()` - 获取资源数量
- `first()` - 获取第一个资源
- `map()` - 映射每个资源
- `filter()` - 过滤资源
- `slice()` / `take()` / `skip()` - 切片操作
- `isEmpty()` / `isNotEmpty()` - 检查是否为空
- 实现 Countable, IteratorAggregate 接口

### AnonymousResourceCollection 匿名资源集合 (`bin/Resource/AnonymousResourceCollection.php`)
- 支持自定义转换回调
- 无需定义专门资源类即可使用

### 辅助函数 (`bin/Func/helpers.php`)
- `resource_collection()` - 创建资源集合
- `json_resource()` - 创建 JSON 资源响应
- `paginate()` - 创建分页资源集合

### 测试覆盖
- `ResourceTest` - 22 个资源系统测试用例

## 2026-03-18 Database Debug 数据库调试工具完成 ✅

### QueryLog 查询日志 (`bin/Database/Debug/QueryLog.php`)
- `make()` - 创建查询日志
- `toFormattedSql()` - 获取带绑定参数的格式化 SQL
- `getType()` / `isSelect()` / `isInsert()` 等 - 查询类型检查
- `setRowCount()` - 设置结果行数
- `setFailed()` - 标记查询失败
- `toArray()` - 转换为数组

### QueryTimer 查询计时器 (`bin/Database/Debug/QueryTimer.php`)
- `start()` / `stop()` - 开始/停止计时
- `getElapsed()` - 获取经过时间（毫秒）
- `getMemoryUsage()` - 获取内存使用
- `isSlow()` - 检查是否为慢查询
- `startNew()` - 创建并启动新计时器

### DatabaseDebugger 数据库调试器 (`bin/Database/Debug/DatabaseDebugger.php`)
- `enable()` / `disable()` - 启用/禁用调试
- `log()` / `logQuery()` - 记录查询
- `getQueries()` - 获取所有查询日志
- `getCount()` - 获取查询数量
- `getTotalTime()` / `getAverageTime()` - 获取总/平均查询时间
- `getSlowestQuery()` - 获取最慢的查询
- `getSlowQueries()` - 获取慢查询列表
- `getFailedQueries()` - 获取失败的查询
- `getTypeStats()` - 获取查询类型统计
- `setSlowQueryThreshold()` - 设置慢查询阈值
- `setCallback()` - 设置查询回调
- `getReport()` / `printSummary()` - 生成调试报告
- `toJson()` / `toArray()` - 导出数据

### 辅助函数 (`bin/Func/helpers.php`)
- `db_debug()` - 获取调试器实例
- `db_debug_enable()` / `db_debug_disable()` - 启用/禁用调试
- `db_queries()` - 获取所有查询
- `db_query_count()` - 获取查询数量
- `db_query_time()` - 获取总查询时间
- `db_slow_queries()` - 获取慢查询
- `db_query_report()` - 获取调试报告
- `db_query_summary()` - 打印查询摘要
- `db_query_log()` - 记录查询

### 测试覆盖
- `DatabaseDebugTest` - 32 个数据库调试测试用例

## 2026-03-18 Profiler 性能分析器完成 ✅

### Profiler 性能分析器 (`bin/Profiler/Profiler.php`)
- `enable()` / `disable()` - 启用/禁用分析
- `start()` / `stop()` - 开始/停止分析
- `checkpoint()` - 记录性能测量点
- `record()` - 记录自定义数据
- `recordQuery()` - 记录数据库查询
- `getElapsed()` - 获取经过时间（毫秒）
- `getMemoryUsage()` - 获取内存使用（字节）
- `getMemoryPeak()` - 获取内存峰值
- `getMemoryUsageFormatted()` / `getMemoryPeakFormatted()` - 格式化内存显示
- `formatBytes()` - 格式化字节数
- `getReport()` - 获取性能报告
- `printSummary()` - 打印性能摘要
- `toJson()` - 导出为 JSON
- `clear()` - 清除数据

### 辅助函数 (`bin/Func/helpers.php`)
- `profiler()` - 获取分析器实例
- `profiler_enable()` / `profiler_disable()` - 启用/禁用分析
- `profiler_checkpoint()` - 记录测量点
- `profiler_record()` - 记录数据
- `profiler_get_elapsed()` - 获取经过时间
- `profiler_get_memory()` - 获取内存使用
- `profiler_get_memory_peak()` - 获取内存峰值
- `profiler_report()` - 获取报告
- `profiler_print()` - 打印摘要

### 测试覆盖
- `ProfilerTest` - 16 个性能分析测试用例

## 2026-03-22 分页系统完成 ✅

### 分页器类 (`bin/Database/`)
- `LengthAwarePaginator` - 完整分页器（带总数）
- `Paginator` - 简单分页器（无总数统计）
- `CursorPaginator` - 游标分页器（适用于大数据集）

### 分页功能
- **LengthAwarePaginator**
  - 总记录数统计
  - 页码导航（首页/末页/上一页/下一页）
  - 页码范围生成
  - HTML 渲染
  - JSON 序列化
  - ArrayAccess/Countable/IteratorAggregate 接口

- **Paginator（简单分页）**
  - 不统计总数，适用于大数据集
  - 上一页/下一页导航
  - 更高效的查询性能

- **CursorPaginator（游标分页）**
  - 基于游标的分页
  - 适用于无限滚动场景
  - 支持游标编码/解码
  - 无需计算 offset

### QueryBuilder 分页方法
- `paginate($perPage, $columns, $pageName, $page)` - 完整分页
- `simplePaginate($perPage, $columns, $pageName, $page)` - 简单分页
- `cursorPaginate($perPage, $columns, $cursorName, $cursor)` - 游标分页
- `forPage($page, $perPage)` - 设置分页

### Model 分页方法
- `User::paginate(15)` - 完整分页
- `User::simplePaginate(15)` - 简单分页
- `User::cursorPaginate(15)` - 游标分页

### 辅助函数
- `create_paginator($items, $total, $perPage, $currentPage)` - 创建分页器
- `simple_paginator($items, $perPage, $currentPage, $options, $hasMore)` - 创建简单分页器
- `cursor_paginator($items, $perPage, $cursor, $nextCursor)` - 创建游标分页器
- `current_page($pageName)` - 获取当前页码
- `per_page($paramName, $default, $max)` - 获取每页数量

### 测试覆盖
- `PaginationTest` - 45 个分页测试用例

## 2026-03-22 软删除系统完成 ✅

### SoftDeletes Trait (`bin/Database/SoftDeletes.php`)
- `delete()` - 软删除模型（设置 deleted_at）
- `forceDelete()` - 强制永久删除
- `restore()` - 恢复软删除的模型
- `trashed()` - 检查是否已软删除
- `isSoftDeleted()` - 同上，别名
- `withTrashed()` - 包含软删除记录的查询
- `onlyTrashed()` - 只获取软删除记录
- `withoutTrashed()` - 只获取未删除记录（默认）
- `restoreMany()` - 批量恢复
- `forceDeleteMany()` - 批量强制删除

### 全局作用域支持
- `Model::addGlobalScope()` - 添加全局作用域
- `Model::forgetGlobalScope()` - 移除全局作用域
- `Model::clearGlobalScopes()` - 清除所有全局作用域
- `Model::boot()` - 模型引导机制
- `Model::bootTraits()` - 自动引导 traits

### QueryBuilder 全局作用域
- `withGlobalScope()` - 添加全局作用域
- `withoutGlobalScope()` - 移除全局作用域
- `withoutGlobalScopes()` - 移除多个全局作用域
- `applyScopes()` - 应用全局作用域

### 使用示例
```php
class User extends Model
{
    use SoftDeletes;
}

// 软删除
$user->delete();

// 恢复
$user->restore();

// 强制删除
$user->forceDelete();

// 查询（自动排除软删除）
$users = User::all();

// 包含软删除
$users = User::withTrashed()->get();

// 只查询软删除
$users = User::onlyTrashed()->get();
```

### 测试覆盖
- `SoftDeletesTest` - 22 个软删除测试用例

## 2026-03-22 模型事件系统完成 ✅

### 事件调度器 (`bin/Database/ModelEventDispatcher.php`)
- `listen($event, $callback)` - 注册事件监听器
- `dispatch($event, $model)` - 触发事件
- `dispatchForModel($modelClass, $event, $model)` - 触发模型类特定事件
- `forget($event)` - 移除事件监听器
- `forgetAll()` - 移除所有监听器

### 支持的事件
| 事件 | 触发时机 | 可阻止操作 |
|------|---------|-----------|
| `creating` | 创建模型前 | ✅ |
| `created` | 创建模型后 | ❌ |
| `updating` | 更新模型前 | ✅ |
| `updated` | 更新模型后 | ❌ |
| `saving` | 保存模型前 | ✅ |
| `saved` | 保存模型后 | ❌ |
| `deleting` | 删除模型前 | ✅ |
| `deleted` | 删除模型后 | ❌ |
| `restoring` | 恢复模型前 | ✅ |
| `restored` | 恢复模型后 | ❌ |

### Model 事件方法
```php
// 注册事件监听器
User::creating(function ($user) {
    // 返回 false 可阻止创建
});

User::created(function ($user) {
    // 创建后执行
});

User::updating(function ($user) {
    // 返回 false 可阻止更新
});

User::deleting(function ($user) {
    // 返回 false 可阻止删除
});

// 清除所有事件监听器
User::flushEventListeners();
```

### 使用示例
```php
class User extends Model
{
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($user) {
            $user->api_token = Str::random(60);
        });

        static::updating(function ($user) {
            // 记录变更日志
            Log::info('User updated', $user->getDirty());
        });
    }
}
```

### 测试覆盖
- `ModelEventTest` - 19 个模型事件测试用例

## 2026-03-18 Blog 示例应用完成 ✅

### 示例应用 (`examples/blog/`)
- 完整的博客应用展示框架功能
- 用户认证（登录/登出）
- 文章 CRUD（创建、读取、列表）
- RESTful API 接口
- JSON 响应转换（API Resources）
- 数据库 ORM 操作
- 视图模板渲染

### 目录结构
- `app/Controllers/` - 控制器（PostController, AuthController）
- `app/Models/` - 模型（User, Post）
- `app/routes/` - 路由定义（web.php）
- `views/` - 视图模板
- `database/migrations/` - 数据库迁移
- `database/seeders/` - 数据填充
- `public/` - 入口文件
- `config/` - 应用配置

### 功能展示
1. **路由系统** - 静态路由、动态路由、中间件保护
2. **认证系统** - 登录/登出、会话管理
3. **ORM 模型** - 查询、创建、关系
4. **API Resources** - JSON 数据转换
5. **视图模板** - 布局继承、数据传递
6. **数据库迁移** - 表结构管理
7. **RESTful API** - JSON API 接口

### 使用方法
```bash
cd examples/blog

# 初始化数据库
php ../../../migrate migrate -p=public/database/migrations
php ../../../command db:seed BlogSeeder -p=public/database/seeders

# 启动服务器
php -S localhost:8000 -t public

# 访问应用
# http://localhost:8000/ - 首页
# http://localhost:8000/login - 登录页
# http://localhost:8000/posts - 文章列表
# http://localhost:8000/api/posts - API 接口
```

### 测试账号
- 邮箱: admin@example.com
- 密码: password

### 开发工具

- [ ] **Database Debug** ✅ (已完成)
  - [x] SQL 查询日志
  - [x] 查询执行时间统计

- [ ] **Profiler** ✅ (已完成)
  - [x] 请求性能分析
  - [x] 内存使用统计

### 文档

- [ ] API 文档
- [ ] 使用示例
- [ ] 贡献指南
- [ ] 升级指南

## 按优先级排序

### P0 (核心功能)
1. DB Builder (查询构建器)
2. Exception Handler (异常处理)
3. Config Write (配置写入)

### P1 (常用功能)
1. Cache (缓存)
2. Session (会话)
3. Log (日志)
4. Validation (验证)

### P2 (增强功能)
1. CLI Mode
2. Migrations/Seeders
3. Auth (认证)

### P3 (高级功能)
1. Authorization (授权)
2. Rate Limiting
3. API Resources
