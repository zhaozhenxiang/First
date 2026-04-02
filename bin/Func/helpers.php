<?php

declare(strict_types=1);

use Bin\App\App;
use Bin\Log\LogManager;
use Bin\Validation\Validator;

if (!function_exists('data_get')) {
    /**
     * 使用点号语法从嵌套数组中获取值
     *
     * @param array $data 数据数组
     * @param string|null $key 点号分隔的键名（如 'user.name'），null 返回整个数组
     * @param mixed $default 默认值
     */
    function data_get(array $data, ?string $key, mixed $default = null): mixed
    {
        if ($key === null || $key === '') {
            return $data;
        }

        $segments = explode('.', $key);
        $value = $data;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}

if (!function_exists('data_set')) {
    /**
     * 使用点号语法设置嵌套数组的值
     *
     * @param array $data 数据数组（引用传递）
     * @param string $key 点号分隔的键名
     * @param mixed $value 要设置的值
     */
    function data_set(array &$data, string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $current = &$data;

        foreach ($segments as $i => $segment) {
            if ($i === count($segments) - 1) {
                $current[$segment] = $value;
            } else {
                if (!isset($current[$segment]) || !is_array($current[$segment])) {
                    $current[$segment] = [];
                }
                $current = &$current[$segment];
            }
        }
    }
}

if (!function_exists('data_has')) {
    /**
     * 使用点号语法检查嵌套数组中键是否存在（区分"不存在"和"值为 null"）
     *
     * @param array $data 数据数组
     * @param string $key 点号分隔的键名
     */
    function data_has(array $data, string $key): bool
    {
        $segments = explode('.', $key);
        $current = $data;

        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return false;
            }
            $current = $current[$segment];
        }

        return true;
    }
}

if (!function_exists('getUrl')) {
    /**
     * 获取请求 URI
     */
    function getUrl(): string
    {
        return $_SERVER['REQUEST_URI'] ?? '/';
    }
}

if (!function_exists('getMethod')) {
    /**
     * 获取请求方法
     */
    function getMethod(): string
    {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }
}

if (!function_exists('basePath')) {
    /**
     * 获取项目根路径
     */
    function basePath(string $path = ''): string
    {
        return BASE_PATH . ($path !== '' ? DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : '');
    }
}

if (!function_exists('config')) {
    /**
     * 获取/设置配置值
     *
     * 获取配置: config('app:name', 'default')
     * 设置配置: config(['app:name' => 'value'])
     * 保存配置: config()->save('app')
     */
    function config(...$args): mixed
    {
        static $repository = null;

        if ($repository === null) {
            $repository = new \Bin\Config\ConfigRepository();
        }

        // 如果没有参数，返回仓库
        if (empty($args)) {
            return $repository;
        }

        // 如果参数是数组，批量设置
        if (is_array($args[0])) {
            foreach ($args[0] as $key => $value) {
                $repository->set($key, $value);
            }

            // 如果第二个参数是 true，立即保存
            if (isset($args[1]) && $args[1] === true) {
                $repository->saveAll();
            }

            return null;
        }

        // 如果有两个参数且不是 null，设置值
        if (count($args) >= 2 && $args[1] !== null) {
            $key = $args[0];
            $value = $args[1];
            $save = $args[2] ?? false;

            $repository->set($key, $value);

            if ($save) {
                [$file] = explode('.', $key);
                $repository->save($file);
            }

            return null;
        }

        // 获取值
        $key = $args[0];
        $default = $args[1] ?? null;

        return $repository->get($key, $default);
    }
}

if (!function_exists('app')) {
    /**
     * 从 IoC 容器解析实例
     */
    function app(string $class): object
    {
        return App::make($class);
    }
}

if (!function_exists('abort')) {
    /**
     * 中止请求并返回错误码
     */
    function abort(int $code, string $message = ''): never
    {
        http_response_code($code);
        if ($message !== '') {
            echo $message;
        } else {
            echo $code;
        }
        exit;
    }
}

if (!function_exists('response')) {
    /**
     * 创建响应
     */
    function response(mixed $data = '', int $status = 200): \Bin\Response\Response
    {
        $response = new \Bin\Response\Response($data);
        $response->setStatus($status);
        return $response;
    }
}

if (!function_exists('redirect')) {
    /**
     * 重定向到指定 URL
     */
    function redirect(string $url, int $status = 302): never
    {
        http_response_code($status);
        header("Location: {$url}");
        exit;
    }
}

if (!function_exists('back')) {
    /**
     * 返回上一页
     */
    function back(): never
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        redirect($referer);
    }
}

if (!function_exists('old')) {
    /**
     * 获取旧的输入值（用于表单重新填充）
     */
    function old(string $key, mixed $default = ''): mixed
    {
        return $_SESSION['_old_input'][$key] ?? $default;
    }
}

if (!function_exists('session')) {
    /**
     * 获取/设置 Session 值
     */
    function session(string $key, mixed $default = null): mixed
    {
        if (func_num_args() === 2) {
            return $_SESSION[$key] ?? $default;
        }

        $_SESSION[$key] = $default;
        return null;
    }
}

if (!function_exists('csrf_token')) {
    /**
     * 获取 CSRF token
     */
    function csrf_token(): string
    {
        return \Bin\Middleware\CsrfMiddleware::generateToken();
    }
}

if (!function_exists('csrf_field')) {
    /**
     * 生成 CSRF 隐藏字段
     */
    function csrf_field(): string
    {
        return \Bin\Middleware\CsrfMiddleware::field();
    }
}

if (!function_exists('logger')) {
    /**
     * 记录日志
     */
    function logger(string $channel = null): \Bin\Log\Logger
    {
        return LogManager::channel($channel);
    }
}

if (!function_exists('info')) {
    /**
     * 记录 info 日志
     */
    function info(string $message, array $context = []): void
    {
        LogManager::channel()->info($message, $context);
    }
}

if (!function_exists('error')) {
    /**
     * 记录 error 日志
     */
    function error(string $message, array $context = []): void
    {
        LogManager::channel()->error($message, $context);
    }
}

if (!function_exists('validate')) {
    /**
     * 验证输入
     */
    function validate(array $data, array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;

            foreach (explode('|', $rule) as $r) {
                $r = trim($r);

                // required
                if ($r === 'required' && $value === null) {
                    $errors[$field] = "{$field} is required";
                    break;
                }

                // email
                if ($r === 'email' && $value !== null) {
                    try {
                        Validator::email($value);
                    } catch (\InvalidArgumentException $e) {
                        $errors[$field] = "{$field} must be a valid email";
                        break;
                    }
                }

                // min:n
                if (str_starts_with($r, 'min:')) {
                    $min = (int) substr($r, 4);
                    if (is_string($value) && strlen($value) < $min) {
                        $errors[$field] = "{$field} must be at least {$min} characters";
                        break;
                    }
                }

                // max:n
                if (str_starts_with($r, 'max:')) {
                    $max = (int) substr($r, 4);
                    if (is_string($value) && strlen($value) > $max) {
                        $errors[$field] = "{$field} must not exceed {$max} characters";
                        break;
                    }
                }
            }
        }

        if ($errors !== []) {
            throw new \Bin\Exception\ValidationException($errors);
        }

        return $data;
    }
}

if (!function_exists('escape')) {
    /**
     * 转义 HTML 特殊字符
     */
    function escape(string $value): string
    {
        return Validator::escape($value);
    }
}

if (!function_exists('now')) {
    /**
     * 获取当前时间
     */
    function now(): \DateTime
    {
        return new \DateTime();
    }
}

if (!function_exists('env')) {
    /**
     * 获取环境变量
     */
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? getenv($key);

        if ($value === false) {
            return $default;
        }

        return match (strtolower($value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'empty', '(empty)' => '',
            'null', '(null)' => null,
            default => $value
        };
    }
}

if (!function_exists('cache')) {
    /**
     * 缓存辅助函数
     *
     * 获取缓存: cache('key')
     * 设置缓存: cache('key', 'value', 3600)
     * 获取缓存管理器: cache()
     */
    function cache(?string $key = null, mixed $value = null, int $ttl = null): mixed
    {
        $manager = \Bin\Cache\CacheManager::class;

        if ($key === null) {
            return $manager;
        }

        if ($value === null) {
            return $manager::get($key);
        }

        return $manager::set($key, $value, $ttl);
    }
}

if (!function_exists('remember')) {
    /**
     * 记住缓存值
     */
    function remember(string $key, int $ttl, \Closure $callback): mixed
    {
        return \Bin\Cache\CacheManager::remember($key, $ttl, $callback);
    }
}

if (!function_exists('cache_forever')) {
    /**
     * 永久缓存
     */
    function cache_forever(string $key, mixed $value): bool
    {
        return \Bin\Cache\CacheManager::forever($key, $value);
    }
}

if (!function_exists('cache_forget')) {
    /**
     * 获取并删除缓存
     */
    function cache_forget(string $key, mixed $default = null): mixed
    {
        return \Bin\Cache\CacheManager::store()->pull($key, $default);
    }
}

if (!function_exists('session_manager')) {
    /**
     * 获取 Session 管理器实例
     */
    function session_manager(): \Bin\Session\SessionManager
    {
        static $manager = null;

        if ($manager === null) {
            $manager = new \Bin\Session\SessionManager();
        }

        return $manager;
    }
}

if (!function_exists('session_get')) {
    /**
     * 获取 Session 值
     */
    function session_get(string $key, mixed $default = null): mixed
    {
        return session_manager()->get($key, $default);
    }
}

if (!function_exists('session_set')) {
    /**
     * 设置 Session 值
     */
    function session_set(string $key, mixed $value): void
    {
        session_manager()->set($key, $value);
    }
}

if (!function_exists('session_has')) {
    /**
     * 检查 Session key 是否存在
     */
    function session_has(string $key): bool
    {
        return session_manager()->has($key);
    }
}

if (!function_exists('session_forget')) {
    /**
     * 删除 Session 值
     */
    function session_forget(string $key): void
    {
        session_manager()->remove($key);
    }
}

if (!function_exists('session_pull')) {
    /**
     * 获取并删除 Session 值
     */
    function session_pull(string $key, mixed $default = null): mixed
    {
        return session_manager()->pull($key, $default);
    }
}

if (!function_exists('session_clear')) {
    /**
     * 清空所有 Session 数据
     */
    function session_clear(): void
    {
        session_manager()->clear();
    }
}

if (!function_exists('flash')) {
    /**
     * 设置 Flash 消息
     */
    function flash(string $key, mixed $value = null): mixed
    {
        $manager = session_manager();

        if (func_num_args() === 1) {
            return $manager->getFlash($key);
        }

        $manager->flash($key, $value);
        return null;
    }
}

if (!function_exists('flash_get')) {
    /**
     * 获取 Flash 消息
     */
    function flash_get(string $key, mixed $default = null): mixed
    {
        return session_manager()->getFlash($key, $default);
    }
}

if (!function_exists('flash_pull')) {
    /**
     * 获取并删除 Flash 消息
     */
    function flash_pull(string $key, mixed $default = null): mixed
    {
        return session_manager()->pullFlash($key, $default);
    }
}

if (!function_exists('flash_has')) {
    /**
     * 检查 Flash 消息是否存在
     */
    function flash_has(string $key): bool
    {
        return session_manager()->hasFlash($key);
    }
}

if (!function_exists('flash_all')) {
    /**
     * 获取所有 Flash 消息
     */
    function flash_all(): array
    {
        return session_manager()->getAllFlash();
    }
}

if (!function_exists('flash_clear')) {
    /**
     * 清除所有 Flash 消息
     */
    function flash_clear(): void
    {
        session_manager()->clearFlash();
    }
}

if (!function_exists('flash_reflash')) {
    /**
     * 重新 Flash 数据（保留到下次请求）
     */
    function flash_reflash(array|string $keys = []): void
    {
        session_manager()->reflash($keys);
    }
}

if (!function_exists('session_id')) {
    /**
     * 获取/设置 Session ID
     */
    function session_id(?string $id = null): string
    {
        $manager = session_manager();

        if ($id === null) {
            return $manager->getId();
        }

        $manager->setId($id);
        return $id;
    }
}

if (!function_exists('session_regenerate')) {
    /**
     * 重新生成 Session ID
     */
    function session_regenerate(bool $destroy = false): string
    {
        return session_manager()->regenerate($destroy);
    }
}

if (!function_exists('session_destroy')) {
    /**
     * 销毁 Session
     */
    function session_destroy(): void
    {
        session_manager()->destroy();
    }
}

if (!function_exists('with_old_input')) {
    /**
     * 保存当前输入值到 Flash 数据
     */
    function with_old_input(array $input): void
    {
        session_manager()->flashInput($input);
    }
}

// ============================================================================
// Auth Helper Functions
// ============================================================================

if (!function_exists('auth')) {
    /**
     * 获取认证管理器或当前用户
     */
    function auth(): ?object
    {
        return \Bin\Auth\AuthManager::user();
    }
}

if (!function_exists('auth_check')) {
    /**
     * 检查用户是否已认证
     */
    function auth_check(): bool
    {
        return \Bin\Auth\AuthManager::check();
    }
}

if (!function_exists('auth_guest')) {
    /**
     * 检查用户是否是访客
     */
    function auth_guest(): bool
    {
        return \Bin\Auth\AuthManager::guest();
    }
}

if (!function_exists('auth_id')) {
    /**
     * 获取当前用户 ID
     */
    function auth_id(): mixed
    {
        return \Bin\Auth\AuthManager::id();
    }
}

if (!function_exists('auth_attempt')) {
    /**
     * 尝试登录用户
     */
    function auth_attempt(array $credentials, bool $remember = false): bool
    {
        return \Bin\Auth\AuthManager::attempt($credentials, $remember);
    }
}

if (!function_exists('auth_login')) {
    /**
     * 登录用户
     */
    function auth_login(object $user, bool $remember = false): void
    {
        \Bin\Auth\AuthManager::login($user, $remember);
    }
}

if (!function_exists('auth_logout')) {
    /**
     * 登出用户
     */
    function auth_logout(): void
    {
        \Bin\Auth\AuthManager::logout();
    }
}

if (!function_exists('hash_make')) {
    /**
     * 哈希密码
     */
    function hash_make(string $value, array $options = []): string
    {
        return \Bin\Auth\HashManager::make($value, $options);
    }
}

if (!function_exists('hash_check')) {
    /**
     * 验证密码
     */
    function hash_check(string $value, string $hashedValue): bool
    {
        return \Bin\Auth\HashManager::check($value, $hashedValue);
    }
}

if (!function_exists('hash_bcrypt')) {
    /**
     * 使用 Bcrypt 算法哈希
     */
    function hash_bcrypt(string $value, int $rounds = 10): string
    {
        return \Bin\Auth\HashManager::bcrypt($value, $rounds);
    }
}

// ============================================================================
// Authorization Helper Functions
// ============================================================================

if (!function_exists('gate')) {
    /**
     * 获取 Gate 实例
     */
    function gate(): \Bin\Auth\Gate
    {
        static $gate = null;

        if ($gate === null) {
            $gate = new \Bin\Auth\Gate();
        }

        return $gate;
    }
}

if (!function_exists('can')) {
    /**
     * 检查当前用户是否有权限
     */
    function can(string $ability, mixed $arguments = []): bool
    {
        return \Bin\Auth\Gate::check($ability, $arguments);
    }
}

if (!function_exists('cannot')) {
    /**
     * 检查当前用户是否无权限
     */
    function cannot(string $ability, mixed $arguments = []): bool
    {
        return \Bin\Auth\Gate::denies($ability, $arguments);
    }
}

if (!function_exists('allows')) {
    /**
     * 检查当前用户是否有权限
     */
    function allows(string $ability, mixed $arguments = []): bool
    {
        return \Bin\Auth\Gate::allows($ability, $arguments);
    }
}

if (!function_exists('denies')) {
    /**
     * 检查当前用户是否无权限
     */
    function denies(string $ability, mixed $arguments = []): bool
    {
        return \Bin\Auth\Gate::denies($ability, $arguments);
    }
}

if (!function_exists('has_permission')) {
    /**
     * 检查用户是否有指定权限（RBAC）
     */
    function has_permission(string $permission, $userId = null): bool
    {
        $userId = $userId ?? auth_id();

        if ($userId === null) {
            return false;
        }

        return \Bin\Auth\Rbac::hasPermission($userId, $permission);
    }
}

if (!function_exists('has_role')) {
    /**
     * 检查用户是否有指定角色（RBAC）
     */
    function has_role(string $role, $userId = null): bool
    {
        $userId = $userId ?? auth_id();

        if ($userId === null) {
            return false;
        }

        return \Bin\Auth\Rbac::hasRole($userId, $role);
    }
}

if (!function_exists('has_any_role')) {
    /**
     * 检查用户是否有任一角色（RBAC）
     */
    function has_any_role(array $roles, $userId = null): bool
    {
        $userId = $userId ?? auth_id();

        if ($userId === null) {
            return false;
        }

        return \Bin\Auth\Rbac::hasAnyRole($userId, $roles);
    }
}

// ============================================================================
// Cookie Helper Functions
// ============================================================================

if (!function_exists('cookie')) {
    /**
     * 获取或设置 Cookie
     */
    function cookie(?string $name = null, mixed $value = null, int $minutes = 0): mixed
    {
        if ($name === null) {
            return \Bin\Cookie\CookieManager::all();
        }

        if ($value === null) {
            return \Bin\Cookie\CookieManager::get($name);
        }

        return \Bin\Cookie\CookieManager::set($name, $value, $minutes);
    }
}

if (!function_exists('cookie_has')) {
    /**
     * 检查 Cookie 是否存在
     */
    function cookie_has(string $name): bool
    {
        return \Bin\Cookie\CookieManager::has($name);
    }
}

if (!function_exists('cookie_forget')) {
    /**
     * 删除 Cookie
     */
    function cookie_forget(string $name): bool
    {
        return \Bin\Cookie\CookieManager::forget($name);
    }
}

if (!function_exists('cookie_forever')) {
    /**
     * 设置永久 Cookie（5 年）
     */
    function cookie_forever(string $name, string $value): bool
    {
        return \Bin\Cookie\CookieManager::forever($name, $value);
    }
}

// ============================================================================
// File Upload Helper Functions
// ============================================================================

if (!function_exists('uploaded_file')) {
    /**
     * 获取上传文件实例
     */
    function uploaded_file(string $key): ?\Bin\Http\UploadedFile
    {
        return \Bin\Http\UploadedFile::createFromGlobal($key);
    }
}

if (!function_exists('file_upload_validate')) {
    /**
     * 验证上传文件
     */
    function file_upload_validate(\Bin\Http\UploadedFile $file, array $rules): array
    {
        return $file->validate($rules);
    }
}


// ============================================================================
// Rate Limiting Helper Functions
// ============================================================================

if (!function_exists('rate_limiter')) {
    /**
     * 获取速率限制器实例
     */
    function rate_limiter(): \Bin\Auth\RateLimiter
    {
        static $limiter = null;

        if ($limiter === null) {
            $limiter = new \Bin\Auth\RateLimiter();
        }

        return $limiter;
    }
}

if (!function_exists('throttle')) {
    /**
     * 检查速率限制
     */
    function throttle(string $key, int $maxAttempts, int $decaySeconds): bool
    {
        return \Bin\Auth\RateLimiter::attempt($key, $maxAttempts, $decaySeconds);
    }
}

if (!function_exists('rate_limit_remaining')) {
    /**
     * 获取剩余尝试次数
     */
    function rate_limit_remaining(string $key, int $maxAttempts, int $decaySeconds): int
    {
        return \Bin\Auth\RateLimiter::remaining($key, $maxAttempts, $decaySeconds);
    }
}

if (!function_exists('rate_limit_clear')) {
    /**
     * 清除速率限制
     */
    function rate_limit_clear(string $key): void
    {
        \Bin\Auth\RateLimiter::clear($key);
    }
}

// ============================================================================
// API Resource Helper Functions
// ============================================================================

if (!function_exists('resource')) {
    /**
     * 创建 JSON 资源
     */
    function resource(mixed $data): mixed
    {
        return $data;
    }
}

if (!function_exists('resource_collection')) {
    /**
     * 创建资源集合
     */
    function resource_collection(mixed $data, ?string $resourceClass = null): \Bin\Resource\ResourceCollection
    {
        if ($resourceClass !== null) {
            return \Bin\Resource\ResourceCollection::make($data, $resourceClass);
        }

        return \Bin\Resource\AnonymousResourceCollection::make($data);
    }
}

if (!function_exists('json_resource')) {
    /**
     * 创建 JSON 资源并返回响应
     */
    function json_resource(mixed $data, int $status = 200): \Bin\Response\Response
    {
        if ($data instanceof \Bin\Resource\JsonResource) {
            return $data->toResponse($status);
        }

        return response($data, $status);
    }
}

if (!function_exists('paginate')) {
    /**
     * 创建分页资源集合
     */
    function paginate(
        mixed $data,
        int $total,
        int $perPage,
        int $currentPage,
        ?string $resourceClass = null
    ): \Bin\Resource\ResourceCollection {
        $collection = $resourceClass !== null
            ? \Bin\Resource\ResourceCollection::make($data, $resourceClass)
            : \Bin\Resource\AnonymousResourceCollection::make($data);

        $lastPage = (int) ceil($total / $perPage);
        $from = ($currentPage - 1) * $perPage + 1;
        $to = min($currentPage * $perPage, $total);

        return $collection->pagination([
            'total' => $total,
            'count' => is_countable($data) ? count($data) : 0,
            'per_page' => $perPage,
            'current_page' => $currentPage,
            'total_pages' => $lastPage,
            'from' => $total > 0 ? $from : 0,
            'to' => $to,
        ]);
    }
}

// ============================================================================
// Database Debug Helper Functions
// ============================================================================

if (!function_exists('db_debug')) {
    /**
     * 获取数据库调试器实例
     */
    function db_debug(): \Bin\Database\Debug\DatabaseDebugger
    {
        static $debugger = null;

        if ($debugger === null) {
            $debugger = new \Bin\Database\Debug\DatabaseDebugger();
        }

        return $debugger;
    }
}

if (!function_exists('db_debug_enable')) {
    /**
     * 启用数据库调试
     */
    function db_debug_enable(): void
    {
        \Bin\Database\Debug\DatabaseDebugger::enable();
    }
}

if (!function_exists('db_debug_disable')) {
    /**
     * 禁用数据库调试
     */
    function db_debug_disable(): void
    {
        \Bin\Database\Debug\DatabaseDebugger::disable();
    }
}

if (!function_exists('db_queries')) {
    /**
     * 获取所有查询日志
     */
    function db_queries(): array
    {
        return \Bin\Database\Debug\DatabaseDebugger::getQueries();
    }
}

if (!function_exists('db_query_log')) {
    /**
     * 记录数据库查询
     */
    function db_query_log(
        string $sql,
        array $bindings = [],
        float $time = 0,
        string $connection = 'default'
    ): void {
        \Bin\Database\Debug\DatabaseDebugger::logQuery($sql, $bindings, $time, $connection);
    }
}

if (!function_exists('db_query_count')) {
    /**
     * 获取查询数量
     */
    function db_query_count(): int
    {
        return \Bin\Database\Debug\DatabaseDebugger::getCount();
    }
}

if (!function_exists('db_query_time')) {
    /**
     * 获取总查询时间
     */
    function db_query_time(): float
    {
        return \Bin\Database\Debug\DatabaseDebugger::getTotalTime();
    }
}

if (!function_exists('db_slow_queries')) {
    /**
     * 获取慢查询列表
     */
    function db_slow_queries(?float $threshold = null): array
    {
        return \Bin\Database\Debug\DatabaseDebugger::getSlowQueries($threshold);
    }
}

if (!function_exists('db_query_report')) {
    /**
     * 获取查询报告
     */
    function db_query_report(): string
    {
        return \Bin\Database\Debug\DatabaseDebugger::getReport();
    }
}

if (!function_exists('db_query_summary')) {
    /**
     * 打印查询摘要
     */
    function db_query_summary(): void
    {
        \Bin\Database\Debug\DatabaseDebugger::printSummary();
    }
}

// ============================================================================
// Profiler Helper Functions
// ============================================================================

if (!function_exists('profiler')) {
    /**
     * 获取性能分析器实例
     */
    function profiler(): \Bin\Profiler\Profiler
    {
        static $profiler = null;

        if ($profiler === null) {
            $profiler = new \Bin\Profiler\Profiler();
        }

        return $profiler;
    }
}

if (!function_exists('profiler_enable')) {
    /**
     * 启用性能分析
     */
    function profiler_enable(): void
    {
        \Bin\Profiler\Profiler::enable();
    }
}

if (!function_exists('profiler_disable')) {
    /**
     * 禁用性能分析
     */
    function profiler_disable(): void
    {
        \Bin\Profiler\Profiler::disable();
    }
}

if (!function_exists('profiler_checkpoint')) {
    /**
     * 记录性能测量点
     */
    function profiler_checkpoint(string $name): void
    {
        \Bin\Profiler\Profiler::checkpoint($name);
    }
}

if (!function_exists('profiler_record')) {
    /**
     * 记录性能数据
     */
    function profiler_record(string $key, float $value, ?int $memory = null): void
    {
        \Bin\Profiler\Profiler::record($key, $value, $memory);
    }
}

if (!function_exists('profiler_get_elapsed')) {
    /**
     * 获取经过时间（毫秒）
     */
    function profiler_get_elapsed(): float
    {
        return \Bin\Profiler\Profiler::getElapsed();
    }
}

if (!function_exists('profiler_get_memory')) {
    /**
     * 获取内存使用（字节）
     */
    function profiler_get_memory(): int
    {
        return \Bin\Profiler\Profiler::getMemoryUsage();
    }
}

if (!function_exists('profiler_get_memory_peak')) {
    /**
     * 获取内存峰值（字节）
     */
    function profiler_get_memory_peak(): int
    {
        return \Bin\Profiler\Profiler::getMemoryPeak();
    }
}

if (!function_exists('profiler_report')) {
    /**
     * 获取性能报告
     */
    function profiler_report(): array
    {
        return \Bin\Profiler\Profiler::getReport();
    }
}

if (!function_exists('profiler_print')) {
    /**
     * 打印性能摘要
     */
    function profiler_print(): void
    {
        \Bin\Profiler\Profiler::printSummary();
    }
}

// ============================================================================
// 分页辅助函数
// ============================================================================

if (!function_exists('create_paginator')) {
    /**
     * 创建分页器
     *
     * @param array $items 数据项
     * @param int $total 总数
     * @param int $perPage 每页数量
     * @param int $currentPage 当前页
     * @param array $options 选项
     * @return \Bin\Database\LengthAwarePaginator
     */
    function create_paginator(array $items, int $total, int $perPage = 15, int $currentPage = 1, array $options = []): \Bin\Database\LengthAwarePaginator
    {
        return new \Bin\Database\LengthAwarePaginator($items, $total, $perPage, $currentPage, $options);
    }
}

if (!function_exists('simple_paginator')) {
    /**
     * 创建简单分页器
     *
     * @param array $items 数据项
     * @param int $perPage 每页数量
     * @param int $currentPage 当前页
     * @param array $options 选项
     * @param bool $hasMore 是否有更多
     * @return \Bin\Database\Paginator
     */
    function simple_paginator(array $items, int $perPage = 15, int $currentPage = 1, array $options = [], bool $hasMore = false): \Bin\Database\Paginator
    {
        return new \Bin\Database\Paginator($items, $perPage, $currentPage, $options, $hasMore);
    }
}

if (!function_exists('cursor_paginator')) {
    /**
     * 创建游标分页器
     *
     * @param array $items 数据项
     * @param int $perPage 每页数量
     * @param string|null $cursor 当前游标
     * @param string|null $nextCursor 下一页游标
     * @param array $options 选项
     * @return \Bin\Database\CursorPaginator
     */
    function cursor_paginator(array $items, int $perPage = 15, ?string $cursor = null, ?string $nextCursor = null, array $options = []): \Bin\Database\CursorPaginator
    {
        return new \Bin\Database\CursorPaginator($items, $perPage, $cursor, $nextCursor, $options);
    }
}

if (!function_exists('current_page')) {
    /**
     * 获取当前页码
     *
     * @param string $pageName 页码参数名
     * @param int $default 默认值
     * @return int
     */
    function current_page(string $pageName = 'page', int $default = 1): int
    {
        $page = $_GET[$pageName] ?? $default;

        if (filter_var($page, FILTER_VALIDATE_INT) !== false && (int) $page >= 1) {
            return (int) $page;
        }

        return $default;
    }
}

if (!function_exists('per_page')) {
    /**
     * 获取每页数量
     *
     * @param string $paramName 参数名
     * @param int $default 默认值
     * @param int $max 最大值
     * @return int
     */
    function per_page(string $paramName = 'per_page', int $default = 15, int $max = 100): int
    {
        $perPage = $_GET[$paramName] ?? $default;

        if (filter_var($perPage, FILTER_VALIDATE_INT) !== false) {
            $perPage = (int) $perPage;
            return min(max($perPage, 1), $max);
        }

        return $default;
    }
}

