<?php

declare(strict_types=1);

namespace Bin\Http;

use Closure;
use CurlHandle;
use RuntimeException;

/**
 * HTTP 请求构建器
 *
 * 流式 API 配置请求，通过 curl 执行。
 */
class PendingRequest
{
    protected string $baseUrl = '';
    protected array $headers = [];
    protected array $options = [];
    protected int $timeout = 30;
    protected int $connectTimeout = 10;
    protected bool $verifySsl = true;
    protected bool $followRedirects = true;
    protected int $maxRedirects = 5;

    /** @var Closure[] 请求中间件 */
    protected array $beforeCallbacks = [];

    /** @var Closure[] 响应中间件 */
    protected array $afterCallbacks = [];

    protected int $retryTimes = 0;
    protected int $retrySleepMs = 0;
    protected ?Closure $retryCondition = null;

    /** @var string body 编码方式: json|form|multipart */
    protected string $bodyFormat = 'json';

    protected array $cookies = [];

    public function __construct(array $config = [])
    {
        if ($config !== []) {
            $this->baseUrl = $config['base_url'] ?? '';
            $this->timeout = $config['timeout'] ?? 30;
            $this->connectTimeout = $config['connect_timeout'] ?? 10;
            $this->verifySsl = $config['verify_ssl'] ?? true;
            $this->followRedirects = $config['follow_redirects'] ?? true;
            $this->maxRedirects = $config['max_redirects'] ?? 5;
            $this->headers = $config['headers'] ?? [];
        }
    }

    // ─── 配置方法（链式） ───

    public function baseUrl(string $url): static
    {
        $this->baseUrl = rtrim($url, '/');
        return $this;
    }

    public function withHeaders(array $headers): static
    {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    public function withToken(string $token, string $type = 'Bearer'): static
    {
        $this->headers['Authorization'] = "{$type} {$token}";
        return $this;
    }

    public function withBasicAuth(string $username, string $password): static
    {
        $this->headers['Authorization'] = 'Basic ' . base64_encode("{$username}:{$password}");
        return $this;
    }

    public function withCookies(array $cookies): static
    {
        $this->cookies = array_merge($this->cookies, $cookies);
        return $this;
    }

    public function withOptions(array $options): static
    {
        $this->options = array_merge($this->options, $options);
        return $this;
    }

    public function timeout(int $seconds): static
    {
        $this->timeout = $seconds;
        return $this;
    }

    public function connectTimeout(int $seconds): static
    {
        $this->connectTimeout = $seconds;
        return $this;
    }

    public function withoutRedirecting(): static
    {
        $this->followRedirects = false;
        return $this;
    }

    public function withoutVerifying(): static
    {
        $this->verifySsl = false;
        return $this;
    }

    public function retry(int $times, int $sleepMs = 0, ?Closure $when = null): static
    {
        $this->retryTimes = $times;
        $this->retrySleepMs = $sleepMs;
        $this->retryCondition = $when;
        return $this;
    }

    public function beforeRequest(Closure $callback): static
    {
        $this->beforeCallbacks[] = $callback;
        return $this;
    }

    public function afterResponse(Closure $callback): static
    {
        $this->afterCallbacks[] = $callback;
        return $this;
    }

    // ─── Body 格式 ───

    public function asJson(): static
    {
        $this->bodyFormat = 'json';
        return $this;
    }

    public function asForm(): static
    {
        $this->bodyFormat = 'form';
        return $this;
    }

    public function bodyFormat(string $format): static
    {
        $this->bodyFormat = $format;
        return $this;
    }

    // ─── HTTP 方法 ───

    public function get(string $url, array $query = []): HttpResponse
    {
        return $this->send('GET', $url, ['query' => $query]);
    }

    public function post(string $url, mixed $data = []): HttpResponse
    {
        return $this->send('POST', $url, ['data' => $data]);
    }

    public function put(string $url, mixed $data = []): HttpResponse
    {
        return $this->send('PUT', $url, ['data' => $data]);
    }

    public function patch(string $url, mixed $data = []): HttpResponse
    {
        return $this->send('PATCH', $url, ['data' => $data]);
    }

    public function delete(string $url, mixed $data = []): HttpResponse
    {
        return $this->send('DELETE', $url, ['data' => $data]);
    }

    public function head(string $url, array $query = []): HttpResponse
    {
        return $this->send('HEAD', $url, ['query' => $query]);
    }

    public function options(string $url, array $query = []): HttpResponse
    {
        return $this->send('OPTIONS', $url, ['query' => $query]);
    }

    // ─── 发送请求 ───

    public function send(string $method, string $url, array $options = []): HttpResponse
    {
        $method = strtoupper($method);
        $fullUrl = $this->buildUrl($url, $options['query'] ?? []);
        $headers = $this->headers;
        $bodyData = $options['data'] ?? null;

        // 构建 body 和 Content-Type
        $body = $this->buildBody($bodyData, $headers);

        // 应用请求中间件
        foreach ($this->beforeCallbacks as $callback) {
            $result = $callback($method, $fullUrl, $headers, $body);
            if (is_array($result)) {
                [$method, $fullUrl, $headers, $body] = array_values($result) + [0 => $method, 1 => $fullUrl, 2 => $headers, 3 => $body];
            }
        }

        // 执行请求（含重试）
        $response = $this->executeWithRetry($method, $fullUrl, $headers, $body);

        // 应用响应中间件
        foreach ($this->afterCallbacks as $callback) {
            $result = $callback($response);
            if ($result instanceof HttpResponse) {
                $response = $result;
            }
        }

        return $response;
    }

    // ─── 内部方法 ───

    /**
     * 构建完整 URL
     */
    protected function buildUrl(string $url, array $query = []): string
    {
        if ($this->baseUrl !== '' && !str_starts_with($url, 'http')) {
            $url = $this->baseUrl . '/' . ltrim($url, '/');
        }

        if ($query !== []) {
            $separator = str_contains($url, '?') ? '&' : '?';
            $url .= $separator . http_build_query($query);
        }

        return $url;
    }

    /**
     * 根据 bodyFormat 构建 body 和相应 headers
     */
    protected function buildBody(mixed $data, array &$headers): string
    {
        if ($data === null || $data === []) {
            return '';
        }

        return match ($this->bodyFormat) {
            'json' => $this->buildJsonBody($data, $headers),
            'form' => $this->buildFormBody($data, $headers),
            default => is_string($data) ? $data : json_encode($data),
        };
    }

    protected function buildJsonBody(mixed $data, array &$headers): string
    {
        $headers['Content-Type'] = 'application/json';
        return is_string($data) ? $data : json_encode($data);
    }

    protected function buildFormBody(mixed $data, array &$headers): string
    {
        $headers['Content-Type'] = 'application/x-www-form-urlencoded';
        return is_array($data) ? http_build_query($data) : (string) $data;
    }

    /**
     * 带重试的执行
     */
    protected function executeWithRetry(string $method, string $url, array $headers, string $body): HttpResponse
    {
        $attempt = 0;
        $maxAttempts = $this->retryTimes + 1;

        while (true) {
            $attempt++;
            $response = $this->execute($method, $url, $headers, $body);

            if ($attempt >= $maxAttempts) {
                return $response;
            }

            if (!$this->shouldRetry($response)) {
                return $response;
            }

            if ($this->retrySleepMs > 0) {
                usleep($this->retrySleepMs * 1000);
            }
        }
    }

    /**
     * 判断是否应重试
     */
    protected function shouldRetry(HttpResponse $response): bool
    {
        if ($this->retryCondition !== null) {
            return (bool) ($this->retryCondition)($response);
        }

        return $response->serverError() || $response->status() === 0;
    }

    /**
     * 执行单次 curl 请求
     */
    protected function execute(string $method, string $url, array $headers, string $body): HttpResponse
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_FOLLOWLOCATION => $this->followRedirects,
            CURLOPT_MAXREDIRS => $this->maxRedirects,
            CURLOPT_SSL_VERIFYPEER => $this->verifySsl,
            CURLOPT_SSL_VERIFYHOST => $this->verifySsl ? 2 : 0,
        ]);

        // 设置 headers
        if ($headers !== []) {
            $curlHeaders = [];
            foreach ($headers as $key => $value) {
                $curlHeaders[] = "{$key}: {$value}";
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);
        }

        // 设置 body
        if ($body !== '' && in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        // 设置 cookies
        if ($this->cookies !== []) {
            $cookieStr = http_build_query($this->cookies, '', '; ');
            curl_setopt($ch, CURLOPT_COOKIE, $cookieStr);
        }

        // 用户自定义 curl 选项
        if ($this->options !== []) {
            curl_setopt_array($ch, $this->options);
        }

        $rawResponse = (string) curl_exec($ch);

        if (curl_errno($ch) !== 0) {
            $errorMsg = curl_error($ch);
            curl_close($ch);
            return new HttpResponse(0, $errorMsg);
        }

        $response = HttpResponse::fromCurl($ch, $rawResponse);
        curl_close($ch);

        return $response;
    }

    /**
     * 构建 curl 请求（用于 Pool）
     */
    public function buildCurlHandle(string $method, string $url, array $options = []): CurlHandle
    {
        $fullUrl = $this->buildUrl($url, $options['query'] ?? []);
        $headers = $this->headers;
        $body = $this->buildBody($options['data'] ?? null, $headers);

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $fullUrl,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_FOLLOWLOCATION => $this->followRedirects,
            CURLOPT_MAXREDIRS => $this->maxRedirects,
            CURLOPT_SSL_VERIFYPEER => $this->verifySsl,
            CURLOPT_SSL_VERIFYHOST => $this->verifySsl ? 2 : 0,
        ]);

        if ($headers !== []) {
            $curlHeaders = [];
            foreach ($headers as $key => $value) {
                $curlHeaders[] = "{$key}: {$value}";
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);
        }

        if ($body !== '' && in_array(strtoupper($method), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        if ($this->cookies !== []) {
            curl_setopt($ch, CURLOPT_COOKIE, http_build_query($this->cookies, '', '; '));
        }

        return $ch;
    }
}
