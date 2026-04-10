# Spec: Queue Core

## QueueManager

### Singleton
- `getInstance()` → static
- `resetInstance()` → clear (testing)

### Configuration
- Reads `config('queue.connections')` and `config('queue.default')` at construction
- `setConfig(array)` and `setDefaultConnection(string)` for programmatic config

### Connection Resolution
- `connection(?name)` → Queue driver instance (lazy, cached)
- `flush()` → clear all resolved connections
- Unconfigured → RuntimeException
- Unsupported driver → RuntimeException

### Proxy Methods (delegate to default connection)
- `push(job, queue)`, `pushRaw(payload, queue)`
- `later(delay, job, queue)`, `pop(queue)`
- `size(queue)`

## Queue Driver Interface

All drivers implement `Bin\Queue\Contracts\QueueInterface`.

### Methods
- `push(mixed $job, string $queue = 'default'): mixed` — push job, return job ID
- `pushRaw(string $payload, string $queue = 'default'): mixed`
- `later(int $delay, mixed $job, string $queue = 'default'): mixed` — delayed push
- `pop(string $queue = 'default'): ?Job` — dequeue
- `delete(mixed $job): bool` — mark complete
- `release(mixed $job, int $delay = 0): bool` — re-queue
- `size(string $queue = 'default'): int` — queue length

## Job Base Class

### Properties
- `$maxTries = 3` — max attempts before failure
- `$timeout = 60` — seconds
- `$retryAfter = 90` — seconds before retry
- `$queue = 'default'` — target queue name
- `$delay = 0` — delay before execution

### Methods
- `handle()` — abstract, user implements
- `displayName(): string` — class name by default
- `attempts(): int` — current attempt count
- `failed(\Throwable $e): void` — optional failure callback
- `getQueue(): string` — target queue
- `setAttempts(int)` — set attempt counter

### Serialization
- Job serialized to JSON payload with: displayName, attempts, maxTries, timeout, queue, data (constructor args)

## Dispatchable Trait

### Static Methods
- `dispatch(mixed ...$args): PendingDispatch` — push to queue
- `dispatchSync(mixed ...$args): mixed` — execute immediately
- `dispatchAfterResponse(mixed ...$args): PendingDispatch`

### PendingDispatch
- Holds job instance until response sent
- `__destruct()` triggers actual dispatch

## ShouldQueue Interface
- Marker interface, no methods
- Classes implementing this can be auto-dispatched to queue
