# Spec: Queue Drivers & Worker

## SyncQueue

### Behavior
- `push()` — immediately unserialize and call `handle()`
- `later()` — same as push (no delay in sync)
- `pop()` — always returns null (no stored jobs)
- `delete()` / `release()` — no-ops, return true
- `size()` — always 0

## DatabaseQueue

### Table: jobs
| Column | Type | Description |
|--------|------|-------------|
| id | INTEGER PK AUTOINCREMENT | |
| queue | VARCHAR(255) | queue name |
| payload | TEXT | JSON serialized job |
| attempts | INTEGER DEFAULT 0 | attempt count |
| reserved_at | INTEGER NULL | reservation timestamp |
| available_at | INTEGER | when job becomes available |
| created_at | INTEGER | creation timestamp |

### Table: failed_jobs
| Column | Type | Description |
|--------|------|-------------|
| id | INTEGER PK AUTOINCREMENT | |
| connection | VARCHAR(255) | connection name |
| queue | VARCHAR(255) | queue name |
| payload | TEXT | JSON serialized job |
| exception | TEXT | error message + trace |
| failed_at | INTEGER | failure timestamp |

### Operations
- `push()` — INSERT INTO jobs
- `pop()` — SELECT available, UPDATE reserved_at, return Job
- `delete()` — DELETE from jobs
- `release()` — UPDATE: reserved_at=null, attempts++, available_at=now+delay
- `size()` — COUNT where queue=? and reserved_at is null

## RedisQueue

### Keys
- `queues:{name}` — list of available jobs
- `queues:{name}:delayed` — sorted set, score = available_at
- `queues:{name}:reserved` — list of reserved jobs

### Operations
- `push()` — RPUSH to list
- `later()` — ZADD to delayed set with score=available_at
- `pop()` — migrate delayed → main, then RPOPLPUSH to reserved
- `delete()` — LREM from reserved
- `release()` — ZADD to delayed (with delay) or RPUSH to main
- `size()` — LLEN + ZCARD

## Worker

### CLI Commands
- `queue:work [connection] [--queue=default] [--tries=3] [--daemon]`
  - Single process daemon loop: pop → execute → ack/fail
  - Signal handling: SIGTERM graceful stop
  - Sleep when queue empty
- `queue:retry {id}` — re-push failed job
- `queue:failed` — list failed jobs

### Execution Loop
```
while (running):
  job = queue.pop()
  if job:
    try:
      job.handle()
      queue.delete(job)
    except:
      if job.attempts >= job.maxTries:
        logToFailed(job, exception)
        queue.delete(job)
      else:
        queue.release(job, job.retryAfter)
  else:
    sleep(1)
```
