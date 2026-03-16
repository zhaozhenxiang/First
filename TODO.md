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

## 2026-03-16 框架优化完成 ✅

### 安全性修复
- SQL 注入漏洞修复 (`bin/Model/Model.php`)
- 新增输入验证器 (`bin/Validation/Validator.php`)
- 新增 CSRF 防护中间件 (`bin/Middleware/CsrfMiddleware.php`)

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

## 待完成 🚧

### 核心功能

- [ ] **DB Builder (查询构建器)**
  - [ ] `where()` - WHERE 条件
  - [ ] `orWhere()` - OR WHERE 条件
  - [ ] `whereIn()` - IN 查询
  - [ ] `orderBy()` - 排序
  - [ ] `limit()` / `take()` - 限制数量
  - [ ] `offset()` / `skip()` - 偏移量
  - [ ] `join()` - JOIN 连接
  - [ ] `leftJoin()` - 左连接
  - [ ] `get()` - 获取多条记录
  - [ ] `first()` - 获取单条记录
  - [ ] `find()` - 按 ID 查找
  - [ ] `create()` - 创建记录
  - [ ] `update()` - 更新记录
  - [ ] `delete()` - 删除记录
  - [ ] `paginate()` - 分页

- [ ] **Cache (缓存)**
  - [ ] Cache 接口定义
  - [ ] 文件缓存驱动
  - [ ] Redis 缓存驱动
  - [ ] Memcached 缓存驱动
  - [ ] `remember()` 辅助方法
  - [ ] Tagging 支持

- [ ] **Session (会话)**
  - [ ] Session 管理
  - [ ] 文件 Session 驱动
  - [ ] 数据库 Session 驱动
  - [ ] Flash 消息
  - [ ] CSRF Token 管理

- [ ] **Log (日志)**
  - [ ] Logger 接口
  - [ ] 文件日志通道
  - [ ] 日志级别 (debug, info, warning, error)
  - [ ] 日志轮转
  - [ ] 上下文数据支持

- [ ] **Exception (异常处理)**
  - [ ] 全局异常处理器
  - [ ] 自定义异常类
  - [ ] 异常视图渲染
  - [ ] 错误页面 (404, 500等)
  - [ ] Whoops 集成 (可选)

### CLI 模式

- [ ] **PHP CLI Mode**
  - [ ] Artisan 风格命令行工具
  - [ ] 命令注册系统
  - [ ] 参数解析
  - [ ] 交互式输入/输出
  - [ ] 任务调度 (Cron)

### 配置功能

- [ ] **Config Write**
  - [ ] `config()` 函数支持写入配置
  - [ ] 环境变量支持 (.env)
  - [ ] 配置缓存

### 验证功能

- [ ] **Validation**
  - [ ] 验证器类
  - [ ] 常用验证规则
  - [ ] 自定义规则
  - [ ] 错误消息显示

### 数据库功能增强

- [ ] **Migrations (迁移)**
  - [ ] 迁移文件生成
  - [ ] `up()` / `down()` 方法
  - [ ] 迁移执行/回滚

- [ ] **Seeders (数据填充)**
  - [ ] Seeder 类
  - [ ] 批量数据插入

- [ ] **Query Builder 增强**
  - [ ] Aggregates (`count`, `sum`, `avg`, `min`, `max`)
  - [ ] Subqueries
  - [ ] Raw expressions

### 认证与授权

- [ ] **Auth (认证)**
  - [ ] 用户认证系统
  - [ ] 哈希/加密
  - [ ] Remember Me
  - [ ] 密码重置

- [ ] **Authorization (授权)**
  - [ ] Gate/Facade
  - [ ] Policy
  - [ ] RBAC (角色权限)

### HTTP 增强

- [ ] **Cookie 管理**
- [ ] **File Upload**
- [ ] **Rate Limiting**
- [ ] **API Resource**

### 开发工具

- [ ] **Database Debug**
  - [ ] SQL 查询日志
  - [ ] 查询执行时间统计

- [ ] **Profiler**
  - [ ] 请求性能分析
  - [ ] 内存使用统计

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
