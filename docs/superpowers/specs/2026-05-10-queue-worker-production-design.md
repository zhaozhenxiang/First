# Queue Worker Production Design

Date: 2026-05-10
Status: Ready for user review

## Goal

Make the existing Queue / Job / Worker ecosystem production-ready enough to
support later Mail, Notification, and Scheduling work.

This stage does not rewrite the queue subsystem. It stabilizes the current
`QueueManager`, `Job`, `PendingDispatch`, `SyncQueue`, `DatabaseQueue`, and
`Worker` implementation around a reliable database-backed worker lifecycle.

## Context

First already has a queue subsystem:

- `bin/Queue/QueueManager.php`
- `bin/Queue/Job.php`
- `bin/Queue/PendingDispatch.php`
- `bin/Queue/Dispatchable.php`
- `bin/Queue/ShouldQueue.php`
- `bin/Queue/Drivers/SyncQueue.php`
- `bin/Queue/Drivers/DatabaseQueue.php`
- `bin/Queue/Worker.php`
- `config/queue.php`
- `database/migrations/2026_04_11_000001_create_jobs_table.php`
- `database/migrations/2026_04_11_000002_create_failed_jobs_table.php`
- `tests/QueueTest.php`

The existing OpenSpec queue docs also define the intended surface area:
connections, queues, sync/database drivers, failed jobs, worker commands, and
retry behavior.

Laravel 13 treats queues as a unified API across backend connections and
separate named queues. It also exposes `queue:work`, failed job inspection,
retry, and cleanup commands. First should follow the same core model while
avoiding advanced Laravel queue features until the base lifecycle is stable.

## Scope

### In Scope

- Add production-oriented queue CLI commands:
  - `queue:work {connection?} {--queue=default} {--tries=3} {--sleep=1} {--once}`
  - `queue:failed {connection?}`
  - `queue:retry {id} {connection?}`
  - `queue:forget {id} {connection?}`
  - `queue:flush {connection?} {--force}`
- Support queue priority order through comma-separated queues such as
  `--queue=high,default`.
- Make `Worker` process jobs through one lifecycle:
  pop, execute, delete on success, release on retry, fail permanently after
  attempts are exhausted.
- Make worker execution call job `handle()` through the container so method
  injection works.
- Make `DatabaseQueue` own database storage behavior for available, reserved,
  released, failed, retried, forgotten, and flushed jobs.
- Make `PendingDispatch` support explicit connection / queue / delay selection
  while preventing duplicate dispatch after explicit dispatch.
- Preserve `SyncQueue` as the development and test driver, but align execution
  semantics with worker behavior where practical.
- Add regression tests for worker success, retry, permanent failure, priority
  queues, failed job commands, bad payload handling, and method injection.

### Out of Scope

- Redis, SQS, Beanstalkd, or other non-database queue drivers.
- Laravel Horizon, Telescope, dashboards, or queue monitoring UI.
- Job batching, unique jobs, encrypted jobs, job middleware, queue failover, and
  transaction-aware dispatch.
- Task scheduling, notifications, broadcasting, or broader ecosystem modules.
- A large Console rewrite.
- Replacing `QueueManager` with a new abstraction.

## Architecture

### QueueManager

`QueueManager` remains the connection resolver and public queue entry point.
It should continue reading `config/queue.php`, caching resolved connections,
and proxying calls to the default connection.

It should gain a test-friendly way to inject or replace resolved connections so
tests no longer need reflection to seed a driver instance. This keeps driver
storage tests and command tests isolated.

### DatabaseQueue

`DatabaseQueue` owns persistence. It should provide explicit methods for:

- pushing immediate jobs
- pushing delayed jobs
- atomically reserving available jobs
- deleting completed jobs
- releasing reserved jobs
- logging failed jobs
- listing failed jobs
- retrying failed jobs
- forgetting one failed job
- flushing failed jobs

Attempts should be incremented when a job is reserved by `pop()`, not when it
is released. Release should only clear reservation state and set the next
availability timestamp.

If a payload cannot be hydrated into a valid `Job`, the queue must not leave it
reserved forever. The worker should be able to treat it as a permanent failure,
write a failed job record, and remove the original job.

### Worker

`Worker` owns the execution lifecycle. It should not parse CLI arguments or
know how to format tables.

The worker should accept connection, queue list, max tries, sleep, and once
options from callers. For a queue list such as `high,default`, it should check
queues in order and process the first available job. This matches the Laravel
priority queue model while staying simple.

A job succeeds when `handle()` returns without throwing. A job fails when
`handle()` throws or the payload cannot be executed.

Failure rules:

- If attempts are below the effective max tries, release the job with its
  retry delay.
- If attempts are exhausted, call the job's `failed(Throwable $e)` callback,
  log the failed job, delete the original job, and increment the worker failed
  counter.
- The `failed()` callback should only run on final failure, not on every retry.

The effective max tries should come from the command option when provided, with
the job's `$maxTries` still honored as a per-job cap.

### Job

`Job` remains the base class for queueable work. This phase keeps its existing
metadata fields:

- `maxTries`
- `timeout`
- `retryAfter`
- `queue`
- `delay`
- `attempts`
- `jobId`

The design does not require Laravel-style model serialization in this phase.
Jobs continue to serialize through the current payload approach. Advanced
payload minimization can be handled after ORM lifecycle and queue lifecycle are
both stable.

### PendingDispatch

`PendingDispatch` should support:

- `onConnection(string $connection)`
- `onQueue(string $queue)`
- `delay(int $seconds)`
- explicit `dispatch()`

The destructor behavior can remain for compatibility, but explicit dispatch
must mark the pending dispatch as already dispatched so the destructor does not
enqueue the job twice.

### Console Commands

Queue commands should be thin adapters:

- parse command options
- resolve `QueueManager` and `Worker`
- call the queue or worker API
- render output and return meaningful exit codes

They should not duplicate job execution, retry, or failure logic.

## Data Flow

### Dispatch

1. Application code calls `SomeJob::dispatch(...$args)`.
2. `Dispatchable` creates a `PendingDispatch`.
3. Callers may configure connection, queue, and delay.
4. `PendingDispatch` sends the job to `QueueManager`.
5. `QueueManager` resolves the selected connection.
6. The selected queue driver stores the job payload.

### Work

1. `php command queue:work database --queue=high,default --tries=3` starts a
   worker.
2. The worker checks `high`, then `default`.
3. `DatabaseQueue::pop()` reserves one available job and increments attempts.
4. The worker executes the job through the application container.
5. On success, the worker deletes the jobs row.
6. On retryable failure, the worker releases the job with a retry delay.
7. On final failure, the worker records a failed job and deletes the jobs row.

### Failed Job Retry

1. `queue:failed` lists records from `failed_jobs`.
2. `queue:retry 5` reads the failed payload for id `5`.
3. The queue pushes the payload back to the original queue.
4. The failed record is deleted after successful requeue.

## Error Handling

- Missing queue connection: return a non-zero command exit code and display a
  clear message.
- Unsupported driver for failed-job commands: return non-zero and explain that
  the selected connection does not support failed jobs.
- Missing failed job id: return non-zero.
- Bad payload: fail permanently, record the payload and exception text, and
  remove the original jobs row.
- Job exception before max tries: release the job.
- Job exception after max tries: record failed job and delete original job.

## Testing

Extend `tests/QueueTest.php` for low-level driver and worker behavior:

- attempts increment on pop, not release
- release makes the job available at the expected time
- final failure writes to `failed_jobs`
- retryable failure releases without calling `failed()`
- permanent failure calls `failed()` once
- queue priority processes `high` before `default`
- bad payload does not remain reserved forever
- worker invokes `handle()` through container method injection

Add `tests/QueueCommandTest.php` for command-level behavior:

- queue commands are discoverable
- `queue:work --once` processes exactly one job
- `queue:failed` lists failed jobs
- `queue:retry` requeues a failed job
- `queue:forget` removes one failed job
- `queue:flush --force` clears failed jobs
- invalid ids and unsupported connections return non-zero exit codes

Focused verification:

```bash
php test tests/QueueTest.php
php test tests/QueueCommandTest.php
php test tests/ConsoleTest.php
php test tests/ConsoleArtisanParityTest.php
```

Final verification:

```bash
php test
```

## Acceptance Criteria

1. `php command queue:work --once` can process one database-backed queued job.
2. `queue:work --queue=high,default` processes higher-priority queues first.
3. Retryable failures are released back to the queue without failed-job records.
4. Exhausted failures call `failed()`, write `failed_jobs`, and delete the
   original jobs row.
5. `queue:failed`, `queue:retry`, `queue:forget`, and `queue:flush --force`
   work against database-backed failed jobs.
6. Job `handle()` supports container method injection.
7. Bad payloads do not leave jobs permanently reserved.
8. Existing queue and console tests continue to pass.

## References

- Laravel 13 Queue documentation: https://laravel.com/docs/13.x/queues
- Laravel 13 release notes: https://laravel.com/docs/13.x/releases
