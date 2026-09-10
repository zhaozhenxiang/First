# Track E Queue & Filesystem Driver Expansion Implementation Plan

> **For agentic workers:** Steps use checkbox (`- [ ]`) syntax for tracking. Implement task-by-task with tests green after each task.

**Goal:** Close the Track E gaps from `docs/superpowers/specs/2026-05-31-laravel13-architecture-gap-spec.md` for the queue and filesystem subsystems: worker backoff and timeout enforcement, a contract-based Redis queue driver, a filesystem driver contract with an FTP adapter, and verification of already-working queued mail.

**Architecture:** The queue already has `Bin\Queue\Contracts\QueueInterface`; sync/database drivers conform. The Redis driver follows the established `Cache\RedisStore` precedent — zero composer dependencies, `\Redis` extension required and failing loudly when the driver is constructed from config, but the driver accepts an injected connection object so tests can exercise all logic against an in-memory fake without the extension. Worker hardening stays inside `Worker` + `Job` (a `backoff` property alongside the existing `maxTries`/`timeout`/`retryAfter`); timeouts use `pcntl_alarm` with async signal delivery, already available in this environment. Filesystem gains a `FilesystemDriver` contract extracted from `LocalDriver`'s public surface; the FTP adapter rides PHP's built-in `ftp://`/`ftps://` stream wrappers (core PHP, no extension).

**Tech Stack:** PHP 8.3+, `php test` runner, `Bin\Testing\TestCase`, pcntl (present), graphify.

---

## Scope

In scope:

- `Job::$backoff` (int or array) with Laravel semantics: fixed delay, or per-attempt schedule reusing the last value once exceeded.
- Worker release delay honors backoff over the fixed `retryAfter`.
- Worker timeout enforcement: `pcntl_alarm` + async `SIGALRM` throwing `QueueTimeoutException`; effective timeout = `min(worker timeout, job timeout)`; alarm cleared after each job; no-op without pcntl.
- `RedisQueue` driver implementing `QueueInterface`: list for ready jobs, zset for delayed, attempts tracked in payload, due delayed jobs migrated on pop.
- `QueueManager` resolves `redis` from `config/queue.php`.
- `FilesystemDriver` contract; `LocalDriver` conforms.
- `FtpDriver` via stream wrappers (host/user/pass/port/root/ssl), `StorageManager` resolves `ftp` disks.
- Config examples for redis queue and ftp disk in `config/queue.php` / `config/filesystems.php` with `.env` keys.
- Verification that queued mailable delivery keeps passing (already covered by `tests/MailTest.php`).

Out of scope:

- SQS/Beanstalk drivers, queue batching, chains, unique jobs.
- Horizon-style observability UI.
- S3/SFTP filesystem adapters (stream-wrapper FTP is the one remote-style adapter for this phase).
- Notifications (spec: only after mail+queue contracts stable — queued as the next change).

## Current State (verified 2026-09-10)

- Worker: max-tries/retry/graceful-stop/failed-jobs present; release delay is fixed `$job->retryAfter`; `Job::$timeout = 60` exists but nothing enforces it.
- `QueueManager::resolveConnection()` matches only `sync`/`database`.
- pcntl present; `ext-redis` absent in this environment (RedisStore already fails loudly without it; tests will use an injected fake).
- Filesystem: `LocalDriver` with 16+ public methods, no interface; `StorageManager` resolves only `local`.
- Queued mailable: implemented and tested (`MailTest` lines ~276-295).

## File Structure

- Modify: `bin/Queue/Job.php` — `$backoff` property + payload serialization.
- Modify: `bin/Queue/Worker.php` — `calculateBackoff()`, timeout alarm wiring, `timeout` parameter threaded through `run/daemon/runNextJob/process`.
- Create: `bin/Queue/QueueTimeoutException.php`.
- Create: `bin/Queue/Drivers/RedisQueue.php`.
- Modify: `bin/Queue/QueueManager.php` — redis driver resolution.
- Modify: `config/queue.php` — redis connection example (commented).
- Create: `bin/Filesystem/Contracts/FilesystemDriver.php`.
- Modify: `bin/Filesystem/Drivers/LocalDriver.php` — implement contract.
- Create: `bin/Filesystem/Drivers/FtpDriver.php`.
- Modify: `bin/Filesystem/StorageManager.php` — ftp disk resolution.
- Modify: `config/filesystems.php` — ftp disk example (commented).
- Create/extend tests: `tests/QueueBackoffTimeoutTest.php`, `tests/RedisQueueDriverTest.php`, `tests/FilesystemFtpTest.php`.

## Tasks

### Task 1 — E-1 Worker backoff + timeout enforcement

- [x] `Job::$backoff` (int|array, default 0 = use retryAfter) serialized in payload.
- [x] `Worker::calculateBackoff()`: array indexed by attempt (1-based), last value reused; int fixed; 0 falls back to retryAfter.
- [x] Timeout: alarm set/cleared around `handle()`, `QueueTimeoutException` flows the normal failure path (tries/backoff), effective = min(worker, job) timeout.
- [x] Without pcntl, behavior unchanged (no alarm).
- [x] Tests: backoff math (int/array/fallback); sleeping job killed at ~timeout and released with backoff; timeout counts toward maxTries.
- [x] `queue:work` gains `--timeout=60` option.

### Task 2 — E-2 Redis queue driver

- [x] `RedisQueue` implements `QueueInterface` with injected-connection constructor (`?object $redis = null` + config fallback that requires `ext-redis` loudly, RedisStore precedent).
- [x] push/pushRaw/later/pop/delete/release/size over list + delayed zset; attempts increment on pop; due delayed migration on pop.
- [x] `QueueManager` resolves `redis` driver; config example documented (commented) in `config/queue.php`.
- [x] Tests with in-memory fake connection object: FIFO order, delay migration, attempts, release (immediate + delayed), size; manager loud-failure resolution test.

### Task 3 — E-3 Filesystem contract + FTP adapter

- [x] Contract already existed as `Bin\Filesystem\FilesystemAdapter`; both drivers verified to conform, contract surface test added.
- [x] `FtpDriver` via `ftp://`/`ftps://` stream wrappers with root prefix, credential-safe url(), raw-connection fallback for mkdir/rmdir.
- [x] `StorageManager` resolves `ftp` disks; config example documented (commented) in `config/filesystems.php`.
- [x] Tests: contract conformance for both drivers, FtpDriver URL/root mapping, StorageManager resolution + unknown-driver rejection.

### Task 4 — Verification

- [x] Queued mailable tests still green (no code change).
- [x] `php test` green (2141 passed vs. 2107 baseline; +34 new tests).
- [x] `graphify update .`
- [x] Status note in Track E section of the gap spec.

## Implementation Notes

- The filesystem driver contract already existed (`FilesystemAdapter`); this change only added a conforming remote-style driver rather than extracting a new contract.
- Live FTP server interaction is not unit-tested (no server in CI); coverage is contract conformance + URL/path mapping + manager resolution.
- Redis driver logic is fully covered via an injected in-memory fake connection; live Redis integration remains environment-dependent (ext-redis is not loaded in this dev environment).

## Verification

- New tests fail before each task's implementation and pass after.
- Timeout test tolerates timing jitter (assert killed well before the full sleep, released/failed correctly).
- Full suite green after every task.
