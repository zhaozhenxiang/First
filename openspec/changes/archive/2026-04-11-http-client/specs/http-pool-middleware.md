# Spec: HTTP Pool & Middleware

## HttpPool (Concurrent Requests)

### Usage
```php
$responses = HttpPool::pool(function ($pool) {
    return [
        $pool->as('users')->get('https://api.example.com/users'),
        $pool->as('posts')->get('https://api.example.com/posts'),
    ];
});
// $responses['users'] → HttpResponse
// $responses['posts'] → HttpResponse
```

### Implementation
- Uses `curl_multi_*` functions
- Each request wrapped as individual curl handle
- Executes all in parallel
- Returns array of HttpResponse keyed by label or index

### Methods
- `pool(Closure $callback): array` — run concurrent requests
- `as(string $key): static` — label a request in pool

## HTTP Middleware

### Pipeline
- Request middleware: `fn(HttpRequest $request, Closure $next): HttpResponse`
- Applied before curl execution
- Can modify request (add headers, log, etc.)

### Built-in Middleware

#### RetryMiddleware
- Configured via `retry($times, $sleepMs, $when)`
- Retries on connection failure or 5xx by default
- Customizable condition via Closure
- Sleep between retries (ms)

#### LogMiddleware (optional)
- Logs request method, URL, and response status
- Configured via config or manually

### User Middleware
- `beforeRequest(Closure $fn)` — modify request before sending
- `afterResponse(Closure $fn)` — inspect/modify response after receiving
