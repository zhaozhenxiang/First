## Why

框架完全没有异步任务处理能力。Laravel Queue 是处理耗时操作（发送邮件、生成报告、同步数据）的核心基础设施。没有队列系统，所有耗时操作只能同步执行，严重影响请求响应时间。

## What Changes

- 新增 Queue 系统：Job + Dispatcher + Worker
- 支持 3 种驱动：sync (同步/开发用) / database / redis
- 支持 Job dispatch、delayed dispatch、queued closures
- 新增 failed_jobs 表和重试机制
- 新增 `queue:work` / `queue:listen` / `queue:retry` / `queue:failed` 命令
- 新增 `config/queue.php` 配置文件
- 支持 `ShouldQueue` 接口标记（Mailable/Notification 可异步）

## Capabilities

### New Capabilities
- `queue-core`: Queue 管理器 + Job 基类 + Dispatcher
- `queue-drivers`: sync/database/redis 驱动实现
- `queue-worker`: Worker 进程 + daemon 模式
- `queue-retry`: 失败任务重试机制
- `queue-config`: queue 配置文件

### Modified Capabilities

## Impact

- `bin/Queue/QueueManager.php` — 新增
- `bin/Queue/Queue.php` — 新增，Queue 基类
- `bin/Queue/Job.php` — 新增，Job 基类
- `bin/Queue/Worker.php` — 新增
- `bin/Queue/Drivers/SyncQueue.php` — 新增
- `bin/Queue/Drivers/DatabaseQueue.php` — 新增
- `bin/Queue/Drivers/RedisQueue.php` — 新增
- `bin/Queue/Dispatchable.php` — 新增，trait
- `bin/Queue/ShouldQueue.php` — 新增，interface
- `config/queue.php` — 新增
- `database/migrations/*_create_jobs_table.php` — 新增
- `database/migrations/*_create_failed_jobs_table.php` — 新增
- `tests/QueueTest.php` — 新增测试
