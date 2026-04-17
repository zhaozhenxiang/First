# M1 任务拆解

## 关联文档

- 主 spec： [M1 主 spec](/home/x/src/install/php/First/docs/superpowers/specs/2026-04-16-m1-runtime-skeleton-spec.md)
- 改造顺序： [M1 改造顺序](/home/x/src/install/php/First/docs/superpowers/specs/2026-04-16-m1-runtime-skeleton-order.md)
- 测试清单： [M1 测试清单](/home/x/src/install/php/First/docs/superpowers/specs/2026-04-16-m1-runtime-skeleton-tests.md)
- 索引页： [M1 文档索引](/home/x/src/install/php/First/docs/superpowers/specs/2026-04-16-m1-runtime-skeleton-index.md)

## 目标

`M1` 的目标是统一运行时骨架，不扩张新功能面，专注于：

1. Bootstrap 生命周期统一
2. 容器装配中心统一
3. HTTP 主链路收口
4. 异常统一出口
5. Response 归一化
6. 路由绑定与 URL 生成固化

## 任务列表

## A. Bootstrap 生命周期

### A1. 盘点当前入口

- 梳理 HTTP 入口
- 梳理 Console 入口
- 标注重复初始化点
- 标注隐式初始化点

### A2. 定义统一阶段

- Environment
- Config
- Container base bindings
- Provider register
- Provider boot
- Kernel ready

### A3. 收口入口实现

- 让 HTTP 与 Console 复用同一套核心初始化
- 去掉 Kernel 内部的临时初始化逻辑

## B. 容器装配中心

### B1. 核心对象创建盘点

- App
- Kernel
- Dispatcher
- Exception handler
- Response 相关对象

### B2. 绑定规则固化

- 哪些是 singleton
- 哪些是 bind
- 哪些允许 instance
- 哪些必须通过接口解析

### B3. 主链路容器化

- 控制器解析统一走容器
- 核心服务不再随意 `new`
- Provider 负责注册，不负责隐式执行主链路逻辑

## C. HTTP 主链路

### C1. 固定生命周期

- Request create
- Route match
- Middleware pipeline
- Controller dispatch
- Response normalize
- Terminate

### C2. 中间件行为统一

- global / group / route 叠加规则
- alias 解析
- priority 顺序
- exclude 逻辑
- terminate 逻辑

### C3. 控制器调度统一

- 容器注入
- FormRequest 注入
- Model binding 注入
- 普通参数注入

## D. 异常统一出口

### D1. 异常分层

- HTTP exception
- Validation exception
- Container exception
- Console exception
- 业务异常透传策略

### D2. 统一处理器

- report
- render
- environment aware output

### D3. 双出口联通

- HTTP 输出
- Console 输出

## E. Response 归一化

### E1. 返回值分类

- string
- array
- Response
- throwable -> handler -> response

### E2. 核心响应类型

- plain response
- json response
- redirect response
- file / stream response

### E3. 响应附加信息

- status code
- headers
- cookies

## F. 路由基础设施

### F1. 命名路由

- 注册规则
- 查找规则
- 冲突处理

### F2. URL 生成

- 参数替换
- 缺参错误
- 可选参数

### F3. 绑定语义

- 显式绑定
- 隐式绑定
- 绑定失败异常

## 完成标准

- HTTP 与 Console 共用统一 bootstrap 骨架
- 核心服务装配路径统一
- 请求生命周期固定并可测试
- 异常只从统一出口转换
- 控制器与异常处理器共享同一响应体系
- 路由承担 URL 生成和绑定基础设施职责
