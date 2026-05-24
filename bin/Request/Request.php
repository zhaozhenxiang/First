<?php

declare(strict_types=1);

namespace Bin\Request;

use Bin\Auth\AuthManager;
use Bin\Database\Collection;
use Bin\Http\UploadedFile;
use Bin\Route\SignedUrl;
use Closure;
use UnitEnum;

/**
 * HTTP 请求封装 — 参考 Laravel Illuminate\Http\Request 设计
 *
 * 输入源分离：query($_GET) / post($_POST) / input(合并) / JSON body 自动解析
 * 支持点号嵌套访问、类型转换、文件集成、old input、内容协商
 */
class Request implements \ArrayAccess, \Iterator
{
    /** @var array<string, mixed> 查询字符串参数 ($_GET) */
    protected array $query = [];
    /** @var array<string, mixed> POST 请求体 ($_POST) */
    protected array $post = [];
    /** @var array<string, mixed> 服务器变量 ($_SERVER) */
    protected array $server = [];
    /** @var array<string, mixed> Cookie ($_COOKIE) */
    protected array $cookies = [];
    /** @var array<string, string> 解析后的请求头 */
    protected array $headers = [];
    /** @var array<string, mixed>|null 懒加载 JSON body */
    protected ?array $jsonPayload = null;
    /** @var array<string, mixed> 路由参数 */
    protected array $routeParams = [];
    /** @var array<string, mixed> 手动合并的输入 */
    protected array $mergedInput = [];
    /** @var Closure|null Resolver for the current authenticated user in this request scope. */
    protected ?Closure $userResolver = null;
    /** @var array<string, mixed> 迭代器当前位置缓存 */
    private array $iterableData = [];
    private int $iteratorPosition = 0;

    /**
     * @param array<string, mixed>|null $query 查询参数，null 时读取 $_GET
     * @param array<string, mixed>|null $post POST 数据，null 时读取 $_POST
     * @param array<string, mixed>|null $server 服务器变量，null 时读取 $_SERVER
     * @param array<string, mixed>|null $cookies Cookie 数据，null 时读取 $_COOKIE
     */
    public function __construct(
        ?array $query = null,
        ?array $post = null,
        ?array $server = null,
        ?array $cookies = null,
    ) {
        $this->query = $query ?? $_GET;
        $this->post = $post ?? $_POST;
        $this->server = $server ?? $_SERVER;
        $this->cookies = $cookies ?? $_COOKIE;
        $this->headers = $this->parseHeaders($this->server);
    }

    /**
     * 从 PHP 超全局变量创建请求实例（Laravel 风格工厂方法）
     */
    public static function capture(): static
    {
        return new static($_GET, $_POST, $_SERVER, $_COOKIE);
    }

    // =========================================================================
    // 输入源访问
    // =========================================================================

    /**
     * 获取查询字符串参数（仅 $_GET）
     */
    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->query;
        }
        return data_get($this->query, $key, $default);
    }

    /**
     * 获取 POST 请求体数据（仅 $_POST）
     */
    public function post(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->post;
        }
        return data_get($this->post, $key, $default);
    }

    /**
     * 获取 Cookie 数据（仅 $_COOKIE）
     */
    public function cookie(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->cookies;
        }

        return data_get($this->cookies, $key, $default);
    }

    public function server(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->server;
        }

        return data_get($this->server, $key, $default);
    }

    /**
     * 检查 Cookie 键是否存在
     */
    public function hasCookie(string $key): bool
    {
        return data_has($this->cookies, $key);
    }

    /**
     * 获取输入值（合并 body + query，body 优先）
     *
     * 支持 JSON body 自动解析和点号嵌套访问
     */
    public function input(?string $key = null, mixed $default = null): mixed
    {
        $input = $this->all();

        if ($key === null || $key === '') {
            return $input;
        }

        return data_get($input, $key, $default);
    }

    /**
     * 获取所有输入数据（合并 query + post/json + merged，高优先级覆盖低）
     */
    public function all(): array
    {
        return array_replace_recursive(
            $this->query,
            $this->post,
            $this->getJsonPayload(),
            $this->mergedInput,
        );
    }

    /**
     * 获取指定键的子集
     */
    public function only(array|string $keys): array
    {
        $keys = is_array($keys) ? $keys : func_get_args();
        return array_intersect_key($this->all(), array_flip($keys));
    }

    /**
     * 获取除指定键外的所有输入
     */
    public function except(array|string $keys): array
    {
        $keys = is_array($keys) ? $keys : func_get_args();
        return array_diff_key($this->all(), array_flip($keys));
    }

    // =========================================================================
    // 输入源判断
    // =========================================================================

    /**
     * 获取 JSON payload（懒加载）
     */
    protected function getJsonPayload(): array
    {
        if ($this->jsonPayload === null && $this->isJson()) {
            $this->jsonPayload = $this->parseJsonBody();
        }
        return $this->jsonPayload ?? [];
    }

    /**
     * 获取主输入源（JSON body 或 POST）
     */
    protected function getInputSource(): array
    {
        if ($this->isJson()) {
            return $this->jsonPayload ??= $this->parseJsonBody();
        }

        $method = $this->getRealMethod();
        return in_array($method, ['GET', 'HEAD'], true) ? [] : $this->post;
    }

    /**
     * 解析 JSON 请求体（懒加载）
     */
    private function parseJsonBody(): array
    {
        $content = file_get_contents('php://input');
        if (empty($content)) {
            return [];
        }

        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : [];
        } catch (\JsonException) {
            return [];
        }
    }

    /**
     * 获取原始请求方法（不考虑 _method 覆盖）
     */
    private function getRealMethod(): string
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    // =========================================================================
    // 输入检查
    // =========================================================================

    /**
     * 检查键是否存在
     */
    public function has(string $key): bool
    {
        $keys = func_get_args();
        $data = $this->all();

        foreach ($keys as $k) {
            if (!data_has($data, $k)) {
                return false;
            }
        }
        return true;
    }

    /**
     * 检查任一键是否存在
     */
    public function hasAny(string ...$keys): bool
    {
        $data = $this->all();
        foreach ($keys as $key) {
            if (data_has($data, $key)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 检查键存在且非空字符串
     */
    public function filled(string $key): bool
    {
        $keys = func_get_args();
        foreach ($keys as $k) {
            $value = $this->input($k);
            if ($value === null || (!is_bool($value) && !is_array($value) && trim((string) $value) === '')) {
                return false;
            }
        }
        return true;
    }

    /**
     * 检查键不存在
     */
    public function missing(string $key): bool
    {
        return !$this->has($key);
    }

    /**
     * 当键存在且非空时执行回调
     */
    public function whenFilled(string $key, callable $callback, ?callable $default = null): static
    {
        if ($this->filled($key)) {
            $callback($this->input($key));
        } elseif ($default) {
            $default();
        }
        return $this;
    }

    /**
     * 当键不存在时执行回调
     */
    public function whenMissing(string $key, callable $callback, ?callable $default = null): static
    {
        if ($this->missing($key)) {
            $callback();
        } elseif ($default) {
            $default();
        }
        return $this;
    }

    // =========================================================================
    // 类型转换
    // =========================================================================

    /**
     * 获取输入并转为字符串
     */
    public function str(string $key, string $default = ''): string
    {
        return (string) ($this->input($key) ?? $default);
    }

    /**
     * 获取输入并转为布尔值
     *
     * "1", "true", "on", "yes" → true; 其余 → false
     */
    public function boolean(string $key, bool $default = false): bool
    {
        return filter_var($this->input($key, $default), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * 获取输入并转为整数
     */
    public function integer(string $key, int $default = 0): int
    {
        return intval($this->input($key, $default));
    }

    /**
     * 获取输入并转为浮点数
     */
    public function float(string $key, float $default = 0.0): float
    {
        return floatval($this->input($key, $default));
    }

    /**
     * 获取输入并转为 DateTimeImmutable
     */
    public function date(string $key, ?string $format = null): ?\DateTimeImmutable
    {
        if (!$this->filled($key)) {
            return null;
        }

        $value = $this->str($key);

        try {
            if ($format !== null) {
                return \DateTimeImmutable::createFromFormat($format, $value) ?: null;
            }
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * 获取输入并转为 BackedEnum
     *
     * @template T of \BackedEnum
     * @param class-string<T> $enumClass
     * @return T|null
     */
    public function enum(string $key, string $enumClass): ?\BackedEnum
    {
        if (!$this->filled($key) || !enum_exists($enumClass)) {
            return null;
        }

        return $enumClass::tryFrom($this->input($key));
    }

    /**
     * 获取输入并转为 Collection
     */
    public function collect(?string $key = null): Collection
    {
        if ($key === null) {
            return new Collection($this->all());
        }
        return new Collection($this->input($key, []));
    }

    // =========================================================================
    // 输入操作
    // =========================================================================

    /**
     * 合并输入到请求数据
     */
    public function merge(array $input): static
    {
        $this->mergedInput = array_replace_recursive($this->mergedInput, $input);
        return $this;
    }

    /**
     * 替换所有输入数据
     */
    public function replace(array $input): static
    {
        $this->mergedInput = $input;
        $this->query = [];
        $this->post = [];
        $this->jsonPayload = [];
        return $this;
    }

    /**
     * 仅合并缺失的键
     */
    public function mergeIfMissing(array $input): static
    {
        foreach ($input as $key => $value) {
            if ($this->missing($key)) {
                $this->mergedInput[$key] = $value;
            }
        }
        return $this;
    }

    public function setUserResolver(?Closure $resolver): static
    {
        $this->userResolver = $resolver;

        return $this;
    }

    public function getUserResolver(): ?Closure
    {
        return $this->userResolver;
    }

    public function user(): ?object
    {
        if ($this->userResolver !== null) {
            return ($this->userResolver)();
        }

        return AuthManager::user();
    }

    public function copyRuntimeContextTo(Request $target): void
    {
        $target->routeParams = $this->routeParams;
        $target->mergedInput = $this->mergedInput;
        $target->jsonPayload = $this->jsonPayload;
        $target->userResolver = $this->userResolver;
    }

    // =========================================================================
    // URL / 路径
    // =========================================================================

    /**
     * 获取请求路径（无前后斜杠）
     */
    public function path(): string
    {
        $requestUri = $this->server['REQUEST_URI'] ?? '/';
        $path = parse_url($requestUri, PHP_URL_PATH);
        return trim($path ?? '/', '/');
    }

    /**
     * 获取完整 URL（无 query string）
     */
    public function url(): string
    {
        $scheme = $this->scheme();
        $host = $this->host();
        $requestUri = $this->server['REQUEST_URI'] ?? '/';
        $path = parse_url($requestUri, PHP_URL_PATH) ?? '/';
        return $scheme . '://' . $host . rtrim($path, '/');
    }

    /**
     * 获取完整 URL（含 query string）
     */
    public function fullUrl(): string
    {
        $query = $this->server['QUERY_STRING'] ?? '';
        if (empty($query)) {
            return $this->url();
        }
        return $this->url() . '?' . $query;
    }

    /**
     * 获取附加指定参数的完整 URL
     */
    public function fullUrlWithQuery(array $query): string
    {
        $existing = $this->query();
        if (is_array($existing)) {
            $merged = array_merge($existing, $query);
        } else {
            $merged = $query;
        }
        $queryString = http_build_query($merged);
        return $this->url() . ($queryString ? '?' . $queryString : '');
    }

    /**
     * 获取移除指定参数的完整 URL
     */
    public function fullUrlWithoutQuery(array $keys): string
    {
        $existing = $this->query();
        if (is_array($existing)) {
            foreach ($keys as $key) {
                unset($existing[$key]);
            }
        }
        $queryString = http_build_query($existing);
        return $this->url() . ($queryString ? '?' . $queryString : '');
    }

    public function hasValidSignature(): bool
    {
        return SignedUrl::hasValidSignature($this);
    }

    /**
     * 检查请求路径是否匹配通配符模式
     */
    public function is(string ...$patterns): bool
    {
        $path = $this->path();
        foreach ($patterns as $pattern) {
            if (fnmatch($pattern, $path) || fnmatch($pattern, '/' . $path)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 获取请求方法（支持 _method 覆盖）
     */
    public function method(): string
    {
        $method = $this->getRealMethod();
        if ($method === 'POST' && isset($this->post['_method'])) {
            $method = strtoupper($this->post['_method']);
        }
        return $method;
    }

    /**
     * 检查是否 HTTPS
     */
    public function isSecure(): bool
    {
        return isset($this->server['HTTPS'])
            && strtolower($this->server['HTTPS']) !== 'off';
    }

    /**
     * 获取协议方案
     */
    public function scheme(): string
    {
        return $this->isSecure() ? 'https' : 'http';
    }

    /**
     * 获取主机名
     */
    public function host(): string
    {
        return $this->server['SERVER_NAME'] ?? $this->server['HTTP_HOST'] ?? 'localhost';
    }

    // =========================================================================
    // 请求头
    // =========================================================================

    /**
     * 获取请求头信息
     */
    public function header(?string $key = null, ?string $default = null): array|string|null
    {
        if ($key === null) {
            return $this->headers;
        }

        $normalizedKey = str_replace('_', '-', strtoupper($key));
        return $this->headers[$normalizedKey] ?? $default;
    }

    /**
     * 从 $_SERVER 解析 HTTP 头
     */
    private function parseHeaders(array $server): array
    {
        $headers = [];
        foreach ($server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headerName = str_replace('_', '-', substr($key, 5));
                $headers[$headerName] = $value;
            }
        }

        // CONTENT_TYPE 和 CONTENT_LENGTH 不带 HTTP_ 前缀
        if (isset($server['CONTENT_TYPE'])) {
            $headers['CONTENT-TYPE'] = $server['CONTENT_TYPE'];
        }
        if (isset($server['CONTENT_LENGTH'])) {
            $headers['CONTENT-LENGTH'] = $server['CONTENT_LENGTH'];
        }

        return $headers;
    }

    // =========================================================================
    // 快捷检测
    // =========================================================================

    /**
     * 检查是否为 AJAX 请求
     */
    public function isAjax(): bool
    {
        return isset($this->server['HTTP_X_REQUESTED_WITH'])
            && strtolower($this->server['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * 检查是否为 JSON 请求
     */
    public function isJson(): bool
    {
        $contentType = $this->header('CONTENT-TYPE') ?? $this->server['CONTENT_TYPE'] ?? '';
        return str_contains($contentType, '/json') || str_contains($contentType, '+json');
    }

    /**
     * 检查是否期望 JSON 响应
     */
    public function expectsJson(): bool
    {
        return $this->wantsJson()
            || $this->isAjax() && !$this->hasExplicitAccept();
    }

    /**
     * 检查 Accept 头是否明确请求 JSON
     */
    public function wantsJson(): bool
    {
        $accept = $this->header('ACCEPT') ?? '';
        return str_contains($accept, 'application/json') || str_contains($accept, '+json');
    }

    /**
     * 检查 Accept 头是否匹配指定类型
     */
    public function accepts(string|array $types): bool
    {
        $accept = $this->header('ACCEPT') ?? '*/*';
        $types = is_array($types) ? $types : [$types];

        if ($accept === '*/*' || $accept === '*') {
            return true;
        }

        foreach ($types as $type) {
            if (str_contains($accept, $type)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 获取 Content-Type
     */
    public function getContentType(): string
    {
        return $this->server['CONTENT_TYPE'] ?? '';
    }

    /**
     * 检查 Accept 头是否被明确设置
     */
    private function hasExplicitAccept(): bool
    {
        $accept = $this->header('ACCEPT') ?? '';
        return $accept !== '' && !str_contains($accept, '*/*');
    }

    // =========================================================================
    // 客户端信息
    // =========================================================================

    /**
     * 获取客户端 IP
     */
    public function ip(): string
    {
        return $this->server['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * 获取 User-Agent
     */
    public function userAgent(): string
    {
        return $this->server['HTTP_USER_AGENT'] ?? '';
    }

    /**
     * 获取请求开始时间
     */
    public function getStartTime(string $format = 'Y-m-d H:i:s'): string
    {
        return date($format, START_TIME ?? time());
    }

    // =========================================================================
    // 文件处理
    // =========================================================================

    /**
     * 获取上传文件
     */
    public function file(?string $key = null): UploadedFile|array|null
    {
        if ($key === null) {
            return $this->allFiles();
        }

        if (!$this->hasFile($key)) {
            return null;
        }

        return UploadedFile::createFromGlobal($key);
    }

    /**
     * 检查是否有上传文件
     */
    public function hasFile(string $key): bool
    {
        return isset($_FILES[$key]) && $_FILES[$key]['error'] !== UPLOAD_ERR_NO_FILE;
    }

    /**
     * 获取所有上传文件
     */
    public function allFiles(): array
    {
        $files = [];
        foreach (array_keys($_FILES) as $key) {
            $uploadedFile = UploadedFile::createFromGlobal($key);
            if ($uploadedFile !== null) {
                $files[$key] = $uploadedFile;
            }
        }
        return $files;
    }

    // =========================================================================
    // Old Input（闪存输入）
    // =========================================================================

    /**
     * 获取上一次请求的闪存输入
     */
    public function old(?string $key = null, mixed $default = null): mixed
    {
        if (!function_exists('session_manager')) {
            return $default;
        }

        $oldInput = session_manager()->getOldInput();

        if ($key === null) {
            return $oldInput;
        }

        return data_get($oldInput, $key, $default);
    }

    /**
     * 闪存当前输入到 Session
     */
    public function flash(): void
    {
        session_manager()->flashInput($this->input());
    }

    /**
     * 仅闪存指定键
     */
    public function flashOnly(array $keys): void
    {
        session_manager()->flashInput($this->only($keys));
    }

    /**
     * 闪存除指定键外的所有输入
     */
    public function flashExcept(array $keys): void
    {
        session_manager()->flashInput($this->except($keys));
    }

    /**
     * 清除所有闪存输入
     */
    public function flush(): void
    {
        session_manager()->flashInput([]);
    }

    // =========================================================================
    // 路由参数（向后兼容）
    // =========================================================================

    /**
     * 获取路由参数
     */
    public function route(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->routeParams;
        }
        return $this->routeParams[$key] ?? $default;
    }

    /**
     * 设置路由参数
     */
    public function setRouteParams(array $params): static
    {
        $this->routeParams = $params;
        return $this;
    }

    // 向后兼容别名
    public function getUrlParam(): array
    {
        return $this->routeParams;
    }

    public function setUrlParam(array $params): static
    {
        $this->routeParams = $params;
        return $this;
    }

    // 向后兼容别名 — 路径和方法
    public function getPath(): string
    {
        return $this->path();
    }

    public function getMethod(): string
    {
        return $this->method();
    }

    // =========================================================================
    // Magic Methods
    // =========================================================================

    public function __get(string $key): mixed
    {
        return $this->input($key) ?? $this->route($key);
    }

    public function __set(string $key, mixed $value): void
    {
        $this->mergedInput[$key] = $value;
    }

    public function __isset(string $key): bool
    {
        return $this->input($key) !== null || $this->route($key) !== null;
    }

    public function __unset(string $key): void
    {
        unset($this->mergedInput[$key]);
    }

    // =========================================================================
    // ArrayAccess
    // =========================================================================

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->mergedInput[$offset] = $value;
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->input((string) $offset) !== null;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->mergedInput[(string) $offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->input((string) $offset);
    }

    // =========================================================================
    // Iterator
    // =========================================================================

    public function rewind(): void
    {
        $this->iterableData = $this->all();
        $this->iteratorPosition = 0;
    }

    public function current(): mixed
    {
        $keys = array_keys($this->iterableData);
        return $this->iterableData[$keys[$this->iteratorPosition]] ?? null;
    }

    public function key(): mixed
    {
        $keys = array_keys($this->iterableData);
        return $keys[$this->iteratorPosition] ?? null;
    }

    public function next(): void
    {
        $this->iteratorPosition++;
    }

    public function valid(): bool
    {
        $keys = array_keys($this->iterableData);
        return isset($keys[$this->iteratorPosition]);
    }
}
