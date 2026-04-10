# Design: HTTP Client

## Architecture

```
HttpClient (facade entry, config-driven)
  └── PendingRequest (builder pattern, immutable)
        ├── withHeaders(), withToken(), withBasicAuth()
        ├── withTimeout(), retry()
        ├── viaMiddleware()
        └── get/post/put/patch/delete/head/options() → HttpRequest
              └── execute via curl → HttpResponse

HttpPool (concurrent requests)
  └── pool(fn($pool) => [...requests]) → HttpResponse[]
```

## Components

### PendingRequest (Builder)
- Fluent API for configuring requests
- Stores: base_url, headers, query, timeout, verify, retry config
- Auth: withToken(), withBasicAuth(), withCookies()
- Body: asJson(), asForm(), attach() (multipart)
- Middleware: beforeRequest(), afterResponse()
- Each verb method creates an HttpRequest and executes it

### HttpRequest (Immutable Request)
- Method, URL, headers, body, query, options
- toArray() for curl setup
- Immutable — built by PendingRequest

### HttpResponse
- Status code, headers, body
- json(), body(), headers(), status(), successful(), failed(), clientError(), serverError()
- throw() — throw on 4xx/5xx
- cookies(), effectiveUrl()

### HttpClient (Manager)
- Singleton, reads config/http.php
- Default timeout, headers, base_url from config
- Factory for PendingRequest
- Pool() for concurrent requests

### HttpPool
- Uses curl_multi for concurrent execution
- Returns array of HttpResponse preserving order

### HttpMiddleware
- Callable pipeline: fn(Request, Closure) → Response
- Built-in: RetryMiddleware, LogMiddleware
- User-defined via beforeRequest/afterResponse

### Http Facade
- Static proxy to HttpClient::getInstance()

### Config (config/http.php)
- default timeout, max_redirects, verify_ssl
- default headers
- global middleware
- base_url
