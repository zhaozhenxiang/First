# Spec: HTTP Client Core

## PendingRequest (Builder)

### Configuration Methods (chainable)
- `withHeaders(array)` — merge headers
- `withToken(string $token, string $type = 'Bearer')` — auth header
- `withBasicAuth(string $user, string $pass)` — Basic auth
- `withCookies(array $cookies)` — set cookies
- `withOptions(array)` — curl options
- `timeout(int $seconds)` — request timeout
- `connectTimeout(int $seconds)` — connection timeout
- `retry(int $times, int $sleepMs = 0, ?Closure $when = null)` — retry on failure
- `withoutRedirecting()` — disable follow redirects
- `withoutVerifying()` — disable SSL verify
- `beforeRequest(Closure)` — request middleware
- `afterResponse(Closure)` — response middleware
- `baseUrl(string)` — set base URL for relative paths

### Body Methods
- `asJson()` — Content-Type: application/json, encode body
- `asForm()` — Content-Type: application/x-www-form-urlencoded
- `attach(string $name, mixed $content, ?string $filename)` — multipart

### Verb Methods → HttpResponse
- `get(string $url, array $query = [])`
- `post(string $url, array $data = [])`
- `put(string $url, array $data = [])`
- `patch(string $url, array $data = [])`
- `delete(string $url, array $data = [])`
- `head(string $url, array $query = [])`
- `options(string $url, array $query = [])`
- `send(string $method, string $url, array $options = [])`

## HttpResponse

### Accessors
- `status(): int` — HTTP status code
- `body(): string` — raw body
- `json(?string $key = null): mixed` — decoded JSON, optional dot-notation key
- `headers(): array` — response headers
- `header(string $name): ?string` — single header
- `cookies(): array` — response cookies
- `effectiveUrl(): string` — final URL after redirects
- `successful(): bool` — 2xx
- `failed(): bool` — 4xx or 5xx
- `clientError(): bool` — 4xx
- `serverError(): bool` — 5xx
- `redirect(): bool` — 3xx

### Actions
- `throw(): static` — throw RequestException on failure
- `onError(Closure): static` — callback on failure

## HttpClient (Manager)

### Singleton
- `getInstance()` → static
- `resetInstance()` → clear singleton

### Factory
- `make(): PendingRequest` — new builder with default config
- `__call` — forward verb shortcuts to make()

### Config
- Reads config/http.php at construction
- `setConfig(array)` — programmatic config

## Http Facade
- `Bin\Facade\Http` → static proxy to HttpClient::getInstance()
- All verb methods available as static calls
