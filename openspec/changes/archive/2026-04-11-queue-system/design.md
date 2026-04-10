# Design: Queue System

## Architecture

```
QueueManager (singleton, config-driven)
  └── connection(name) → Queue (driver)
        ├── SyncQueue    — 同步执行（开发/测试）
        ├── DatabaseQueue — jobs 表存储（生产轻量）
        └── RedisQueue   — Redis list/Sorted Set（高性能）

Job (用户定义)
  ├── use Dispatchable  — dispatch()/dispatchSync()/delay()
  ├── implements ShouldQueue — 标记可入队
  └── handle()           — 实际执行逻辑

Worker (CLI daemon)
  └── daemon(connection, queue)
        └── loop: pop() → execute → acknowledge / logFailure
```

## Components

### QueueManager
- Singleton, reads config/queue.php
- Manages named connections (like CacheManager pattern)
- `connection(?name)` → Queue driver instance
- `push(job, queue)`, `later(delay, job, queue)`, `pop(queue)`

### Queue Driver Interface
- `push(job, queue)` — 入队
- `pushRaw(payload, queue)` — 原始入队
- `later(delay, job, queue)` — 延迟入队
- `pop(queue)` — 出队（返回 Job or null）
- `delete(job)` — 确认完成
- `release(job, delay)` — 重新入队
- `size(queue)` — 队列长度

### Job Base Class
- `displayName()` — 任务名
- `attempts()` — 已尝试次数
- `maxTries` — 最大重试
- `timeout` — 超时秒数
- `retryAfter` — 重试间隔
- `failed($exception)` — 失败回调
- `handle()` — 抽象，用户实现

### Dispatchable Trait
- `dispatch(...args)` — 创建并分发 Job
- `dispatchSync(...args)` — 同步执行
- `dispatchAfterResponse(...args)` — 响应后执行

### ShouldQueue Interface
- 标记接口，无方法
- Mailable/Notification 实现此接口则自动入队

### SyncQueue
- 立即执行 job->handle()
- 不存储，不重试

### DatabaseQueue
- jobs 表: id, queue, payload, attempts, reserved_at, available_at, created_at
- pop: SELECT + UPDATE reserved_at
- delete: DELETE by id
- release: UPDATE reserved_at=null, attempts++
- failed_jobs 表存储失败记录

### RedisQueue
- Redis list: `queues:{name}`
- 延迟: sorted set `queues:{name}:delayed`, score = available_at
- pop: RPOPLPUSH to `queues:{name}:reserved`

### Worker
- CLI daemon: `queue:work [connection] [--queue=name] [--tries=3] [--daemon]`
- `queue:listen` — 启动新进程处理
- `queue:retry {id}` — 重试失败任务
- `queue:failed` — 列出失败任务
- 失败处理: 超过 maxTries → 移到 failed_jobs

### Config (config/queue.php)
- default connection
- connections: sync/database/redis
- failed: table, database
