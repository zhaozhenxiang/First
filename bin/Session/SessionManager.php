<?php

declare(strict_types=1);

namespace Bin\Session;

/**
 * Session 管理器
 */
class SessionManager implements SessionInterface
{
    /** @var string Flash 数据键名 */
    private string $flashKey = '_flash';

    /** @var string 旧 Flash 数据键名 */
    private string $oldFlashKey = '_flash_old';

    /** @var int Session 生命周期（秒） */
    private int $lifetime = 7200;

    /** @var bool Session 是否已启动 */
    private bool $started = false;

    /** @var \SessionHandlerInterface|null 自定义存储处理器 */
    private ?\SessionHandlerInterface $handler = null;

    /** @var string|null 临时 Session ID（用于测试环境） */
    private ?string $tempId = null;

    /**
     * 构造函数
     */
    public function __construct()
    {
        // 从 config 读取 lifetime
        if (function_exists('config')) {
            $this->lifetime = (int) config('session.lifetime', 7200);
        }
        $this->configure();
        $this->configureDefaultHandler();
    }

    /**
     * 配置 Session
     */
    private function configure(): void
    {
        $cookieConfig = function_exists('config') ? config('session.cookie', []) : [];

        @session_name((string) ($cookieConfig['name'] ?? 'first_session'));
        @ini_set('session.cookie_path', (string) ($cookieConfig['path'] ?? '/'));

        if (($domain = $cookieConfig['domain'] ?? null) !== null) {
            @ini_set('session.cookie_domain', (string) $domain);
        }

        // 静默设置 Session 参数（如果 headers 已发送，忽略警告）
        $options = [
            'session.use_cookies' => '1',
            'session.use_only_cookies' => '1',
            'session.cookie_httponly' => ($cookieConfig['http_only'] ?? true) ? '1' : '0',
            'session.use_strict_mode' => '1',
            'session.cookie_lifetime' => (string) $this->lifetime,
        ];

        foreach ($options as $key => $value) {
            @ini_set($key, $value);
        }

        // SameSite
        @ini_set('session.cookie_samesite', $cookieConfig['same_site'] ?? 'Lax');

        // Secure 标志
        $secure = $cookieConfig['secure'] ?? false;
        if ($secure || (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')) {
            @ini_set('session.cookie_secure', '1');
        }
    }

    /**
     * 根据 session 配置注册默认文件处理器
     */
    private function configureDefaultHandler(): void
    {
        if ($this->handler !== null || !function_exists('config')) {
            return;
        }

        if ((string) config('session.driver', 'file') !== 'file') {
            return;
        }

        $path = (string) config('session.files', storage_path('sessions'));
        $minutes = (int) ceil($this->lifetime / 60);

        $this->handler = new FileSessionHandler($path, $minutes);
    }

    /**
     * 启动 Session
     */
    public function start(): bool
    {
        if ($this->started) {
            return true;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            $this->started = true;
            return true;
        }

        // 设置自定义处理器
        if ($this->handler !== null) {
            @session_set_save_handler($this->handler, true);
        }

        // 设置生命周期
        @ini_set('session.gc_maxlifetime', (string) $this->lifetime);

        // 启动 Session
        $result = @session_start();

        // 在某些环境中（如 CLI），session_start() 返回 true 但状态可能不是 ACTIVE
        // 只要有 $_SESSION 数组可用，就认为已启动
        if ($result || isset($_SESSION)) {
            $this->started = true;
            $this->ageFlashData();
            return true;
        }

        return false;
    }

    /**
     * 确保 Session 已启动
     */
    private function ensureStarted(): void
    {
        if (!$this->started) {
            $this->start();
        }
    }

    /**
     * 获取 session 值
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->ensureStarted();

        return data_get($_SESSION, $key, $default);
    }

    /**
     * 设置 session 值
     */
    public function set(string $key, mixed $value): void
    {
        $this->ensureStarted();

        data_set($_SESSION, $key, $value);
    }

    /**
     * 检查 key 是否存在
     */
    public function has(string $key): bool
    {
        $this->ensureStarted();

        return data_has($_SESSION, $key);
    }

    /**
     * 删除 session 值
     */
    public function remove(string $key): void
    {
        $this->ensureStarted();

        $segments = explode('.', $key);
        $session = &$_SESSION;

        foreach ($segments as $i => $segment) {
            if (!isset($session[$segment])) {
                return;
            }

            if ($i === count($segments) - 1) {
                unset($session[$segment]);
            } else {
                $session = &$session[$segment];
            }
        }
    }

    /**
     * 获取并删除 session 值
     */
    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->remove($key);
        return $value;
    }

    /**
     * 清空所有 session 数据
     */
    public function clear(): void
    {
        $this->ensureStarted();
        $_SESSION = [];
    }

    /**
     * 获取所有 session 数据
     */
    public function all(): array
    {
        $this->ensureStarted();
        return $_SESSION;
    }

    /**
     * 设置 Flash 消息
     */
    public function flash(string $key, mixed $value): void
    {
        $this->ensureStarted();

        if (!isset($_SESSION[$this->flashKey])) {
            $_SESSION[$this->flashKey] = [];
        }

        $_SESSION[$this->flashKey][$key] = $value;
    }

    /**
     * 获取 Flash 消息
     */
    public function getFlash(string $key, mixed $default = null): mixed
    {
        $this->ensureStarted();

        $oldFlash = $_SESSION[$this->oldFlashKey] ?? [];
        $newFlash = $_SESSION[$this->flashKey] ?? [];

        return $newFlash[$key] ?? $oldFlash[$key] ?? $default;
    }

    /**
     * 获取并删除 Flash 消息
     */
    public function pullFlash(string $key, mixed $default = null): mixed
    {
        $value = $this->getFlash($key, $default);

        // 从新旧 Flash 数据中删除
        if (isset($_SESSION[$this->oldFlashKey][$key])) {
            unset($_SESSION[$this->oldFlashKey][$key]);
        }
        if (isset($_SESSION[$this->flashKey][$key])) {
            unset($_SESSION[$this->flashKey][$key]);
        }

        return $value;
    }

    /**
     * 检查 Flash 消息是否存在
     */
    public function hasFlash(string $key): bool
    {
        $this->ensureStarted();

        $oldFlash = $_SESSION[$this->oldFlashKey] ?? [];
        $newFlash = $_SESSION[$this->flashKey] ?? [];

        return isset($oldFlash[$key]) || isset($newFlash[$key]);
    }

    /**
     * 获取所有 Flash 消息
     */
    public function getAllFlash(): array
    {
        $this->ensureStarted();

        $oldFlash = $_SESSION[$this->oldFlashKey] ?? [];
        $newFlash = $_SESSION[$this->flashKey] ?? [];

        return array_merge($oldFlash, $newFlash);
    }

    /**
     * 重新 Flash 数据（保留到下次请求）
     */
    public function reflash(array|string $keys = []): void
    {
        $this->ensureStarted();

        if (empty($keys)) {
            // 重新所有 Flash 数据
            if (isset($_SESSION[$this->oldFlashKey])) {
                foreach ($_SESSION[$this->oldFlashKey] as $key => $value) {
                    $_SESSION[$this->flashKey][$key] = $value;
                }
            }
        } else {
            // 重新指定键的 Flash 数据
            foreach ((array) $keys as $key) {
                if (isset($_SESSION[$this->oldFlashKey][$key])) {
                    $_SESSION[$this->flashKey][$key] = $_SESSION[$this->oldFlashKey][$key];
                }
            }
        }
    }

    /**
     * 清除 Flash 消息
     */
    public function clearFlash(): void
    {
        $this->ensureStarted();

        unset($_SESSION[$this->flashKey], $_SESSION[$this->oldFlashKey]);
    }

    /**
     * 老化 Flash 数据（将新 Flash 数据移到旧 Flash）
     */
    private function ageFlashData(): void
    {
        if (isset($_SESSION[$this->oldFlashKey])) {
            unset($_SESSION[$this->oldFlashKey]);
        }

        if (isset($_SESSION[$this->flashKey])) {
            $_SESSION[$this->oldFlashKey] = $_SESSION[$this->flashKey];
            unset($_SESSION[$this->flashKey]);
        }
    }

    /**
     * 获取 Session ID
     */
    public function getId(): string
    {
        $this->ensureStarted();

        $id = session_id();

        // 在测试环境中，session_id() 可能返回空字符串
        if ($id === '' || $id === '0') {
            if ($this->tempId === null) {
                $this->tempId = bin2hex(random_bytes(13));
            }
            return $this->tempId;
        }

        $this->tempId = $id;
        return $id;
    }

    /**
     * 设置 Session ID
     */
    public function setId(string $id): void
    {
        if ($this->started && $this->tempId !== null) {
            throw new \RuntimeException('Cannot set session ID after session has started.');
        }

        $this->tempId = $id;
        @session_id($id);
    }

    /**
     * 获取 Session 名称
     */
    public function getName(): string
    {
        return session_name();
    }

    /**
     * 保存 Session 数据
     */
    public function save(): void
    {
        if ($this->started) {
            session_write_close();
            $this->started = false;
        }
    }

    /**
     * 销毁 Session
     */
    public function destroy(): void
    {
        if (!$this->started) {
            $this->start();
        }

        $_SESSION = [];

        // 在非 CLI 环境中删除 cookie
        if (ini_get('session.use_cookies') && PHP_SAPI !== 'cli') {
            $params = @session_get_cookie_params();
            if ($params) {
                @setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params['path'] ?? '/',
                    $params['domain'] ?? '',
                    $params['secure'] ?? false,
                    $params['httponly'] ?? true
                );
            }
        }

        @session_destroy();
        $this->started = false;
        $this->tempId = null;
    }

    /**
     * 检查 Session 是否已启动
     */
    public function isStarted(): bool
    {
        return $this->started || session_status() === PHP_SESSION_ACTIVE || isset($_SESSION);
    }

    /**
     * 设置过期时间（分钟）
     */
    public function setLifetime(int $minutes): void
    {
        $this->lifetime = $minutes * 60;
        @ini_set('session.gc_maxlifetime', (string) $this->lifetime);
        @ini_set('session.cookie_lifetime', (string) $this->lifetime);
    }

    /**
     * 获取过期时间（秒）
     */
    public function getLifetime(): int
    {
        return $this->lifetime;
    }

    /**
     * 设置闪存数据键名
     */
    public function setFlashKey(string $key): void
    {
        $this->flashKey = $key;
        $this->oldFlashKey = $key . '_old';
    }

    /**
     * 获取闪存数据键名
     */
    public function getFlashKey(): string
    {
        return $this->flashKey;
    }

    /**
     * 注册自定义存储处理器
     */
    public function setHandler(\SessionHandlerInterface $handler): void
    {
        if ($this->started) {
            throw new \RuntimeException('Cannot set session handler after session has started.');
        }

        $this->handler = $handler;
    }

    /**
     * 生成新的 Session ID
     */
    public function regenerate(bool $destroy = false): string
    {
        $this->ensureStarted();

        if ($destroy) {
            $oldData = $_SESSION;
        }

        // 在测试环境中，session_regenerate_id 可能不工作
        if (@session_regenerate_id($destroy)) {
            $this->tempId = null; // 重置临时 ID
        } else {
            // 手动生成新 ID
            $this->tempId = bin2hex(random_bytes(13));
        }

        if ($destroy && isset($oldData)) {
            $_SESSION = $oldData;
        }

        return $this->getId();
    }

    /**
     * 设置 CSRF Token
     */
    public function putCsrfToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->set('_csrf_token', $token);
        return $token;
    }

    /**
     * 获取 CSRF Token
     */
    public function getCsrfToken(): ?string
    {
        return $this->get('_csrf_token');
    }

    /**
     * 验证 CSRF Token
     */
    public function verifyCsrfToken(string $token): bool
    {
        $storedToken = $this->getCsrfToken();

        if ($storedToken === null) {
            return false;
        }

        return hash_equals($storedToken, $token);
    }

    /**
     * 获取上一个请求的输入值
     */
    public function getOldInput(string $key = null, mixed $default = null): mixed
    {
        // 从 Flash 数据中获取旧输入
        $oldInput = $this->getFlash('_old_input', []);

        if ($key === null) {
            return $oldInput;
        }

        return $oldInput[$key] ?? $default;
    }

    /**
     * 保存当前输入值
     */
    public function flashInput(array $input): void
    {
        $this->flash('_old_input', $input);
    }
}
