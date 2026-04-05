<?php

declare(strict_types=1);

namespace Bin\Config;

use Closure;
use RuntimeException;

/**
 * 配置仓库
 */
class ConfigRepository
{
    /**
     * 已加载的配置
     * @var array<string, array>
     */
    protected array $items = [];

    /**
     * 配置文件路径（源文件）
     */
    protected string $path;

    /**
     * 编译缓存路径
     */
    protected string $compiledPath;

    /**
     * 配置缓存
     * @var array<string, mixed>
     */
    protected array $cache = [];

    /**
     * 配置变更（尚未保存）
     * @var array<string, array>
     */
    protected array $changes = [];

    /**
     * 构造函数
     */
    public function __construct(?string $path = null)
    {
        $this->path = $path ?? basePath('/config');
        $this->compiledPath = basePath('/storage/config');
    }

    /**
     * 获取配置值
     */
    public function get(string $key, mixed $default = null): mixed
    {
        // 检查缓存
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        // 检查未保存的变更
        if ($this->hasPendingChange($key)) {
            return $this->getPendingChange($key);
        }

        // 解析键: "filename.key" 或 "filename.key.subkey"
        [$file, $fileKey] = $this->parseKey($key);

        // 加载配置文件
        $config = $this->load($file);

        // 获取嵌套值
        $value = $this->getNested($config, $fileKey, $default);

        // 缓存值
        $this->cache[$key] = $value;

        return $value;
    }

    /**
     * 设置配置值
     */
    public function set(string $key, mixed $value): void
    {
        // 清除缓存
        unset($this->cache[$key]);

        // 记录变更
        [$file, $fileKey] = $this->parseKey($key);
        $this->changes[$file][$fileKey] = $value;
    }

    /**
     * 检查配置是否存在
     */
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * 获取所有配置
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * 加载配置文件（优先编译缓存，fallback 源文件）
     */
    public function load(string $file): array
    {
        if (isset($this->items[$file])) {
            return $this->items[$file];
        }

        // 优先读取编译缓存
        $compiledFile = $this->compiledPath . '/' . $file . '.php';
        if (file_exists($compiledFile)) {
            $path = $compiledFile;
        } else {
            $path = $this->path . '/' . $file . '.php';
        }

        if (!file_exists($path)) {
            throw new RuntimeException("Configuration file not found: {$file}");
        }

        $config = require $path;

        if (!is_array($config)) {
            throw new RuntimeException("Configuration file must return an array: {$file}");
        }

        $this->items[$file] = $config;

        return $config;
    }

    /**
     * 预加载配置文件
     */
    public function preload(array $files): void
    {
        foreach ($files as $file) {
            $this->load($file);
        }
    }

    /**
     * 保存配置到文件
     */
    public function save(string $file, ?array $config = null): bool
    {
        $config = $config ?? $this->items[$file] ?? [];

        // 应用未保存的变更
        if (isset($this->changes[$file])) {
            $config = array_merge($config, $this->flattenChanges($this->changes[$file]));
            unset($this->changes[$file]);
        }

        $path = $this->path . '/' . $file . '.php';
        $content = $this->exportToPhp($config, $file);

        return file_put_contents($path, $content) !== false;
    }

    /**
     * 保存所有变更
     */
    public function saveAll(): bool
    {
        $success = true;

        foreach (array_keys($this->changes) as $file) {
            if (!$this->save($file)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * 设置配置值并立即保存
     */
    public function saveImmediately(string $key, mixed $value): bool
    {
        $this->set($key, $value);

        [$file, ] = $this->parseKey($key);

        return $this->save($file);
    }

    /**
     * 解析配置键
     */
    protected function parseKey(string $key): array
    {
        if (str_contains($key, '.')) {
            [$file, $fileKey] = explode('.', $key, 2);

            return [$file, $fileKey];
        }

        return [$key, null];
    }

    /**
     * 获取嵌套值
     */
    protected function getNested(array $array, ?string $key, mixed $default): mixed
    {
        return data_get($array, $key, $default);
    }

    /**
     * 检查是否有未保存的变更
     */
    protected function hasPendingChange(string $key): bool
    {
        [$file, $fileKey] = $this->parseKey($key);

        return isset($this->changes[$file]) && array_key_exists($fileKey, $this->changes[$file]);
    }

    /**
     * 获取未保存的变更
     */
    protected function getPendingChange(string $key): mixed
    {
        [$file, $fileKey] = $this->parseKey($key);

        return $this->changes[$file][$fileKey] ?? null;
    }

    /**
     * 展平变更数组
     */
    protected function flattenChanges(array $changes): array
    {
        $result = [];

        foreach ($changes as $key => $value) {
            $keys = explode('.', $key);

            $current = &$result;
            foreach ($keys as $i => $k) {
                if ($i === count($keys) - 1) {
                    $current[$k] = $value;
                } else {
                    if (!isset($current[$k]) || !is_array($current[$k])) {
                        $current[$k] = [];
                    }
                    $current = &$current[$k];
                }
            }
        }

        return $result;
    }

    /**
     * 导出为 PHP 配置文件格式
     */
    protected function exportToPhp(array $config, string $file): string
    {
        $content = "<?php\n\n";
        $content .= "declare(strict_types=1);\n\n";
        $content .= "return " . $this->varExport($config) . ";\n";

        return $content;
    }

    /**
     * 导出变量（美化格式）
     */
    protected function varExport(mixed $var, int $depth = 0): string
    {
        $indent = str_repeat('    ', $depth);
        $innerIndent = str_repeat('    ', $depth + 1);

        if (is_array($var)) {
            $indexed = array_keys($var) === range(0, count($var) - 1);
            $r = [];

            foreach ($var as $key => $value) {
                $value = $this->varExport($value, $depth + 1);

                if ($indexed) {
                    $r[] = $value;
                } else {
                    $r[] = "'" . $key . "' => " . $value;
                }
            }

            if (empty($r)) {
                return '[]';
            }

            return "[\n" . $innerIndent . implode(",\n" . $innerIndent, $r) . "\n" . $indent . ']';
        }

        if (is_bool($var)) {
            return $var ? 'true' : 'false';
        }

        if (is_null($var)) {
            return 'null';
        }

        if (is_string($var)) {
            return "'" . addslashes($var) . "'";
        }

        if (is_numeric($var)) {
            return (string) $var;
        }

        return var_export($var, true);
    }

    /**
     * 清除所有缓存
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }

    /**
     * 获取未保存的变更
     */
    public function getPendingChanges(): array
    {
        return $this->changes;
    }

    /**
     * 放弃所有未保存的变更
     */
    public function discardChanges(): void
    {
        $this->changes = [];
    }

    /**
     * 设置配置文件路径
     */
    public function setPath(string $path): void
    {
        $this->path = $path;
    }

    /**
     * 获取配置文件路径
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * 设置编译缓存路径
     */
    public function setCompiledPath(string $path): void
    {
        $this->compiledPath = $path;
    }

    /**
     * 获取编译缓存路径
     */
    public function getCompiledPath(): string
    {
        return $this->compiledPath;
    }

    /**
     * 宏注册
     * @var array<string, Closure>
     */
    protected static array $macros = [];

    /**
     * 注册宏
     */
    public static function macro(string $name, Closure $macro): void
    {
        static::$macros[$name] = $macro;
    }

    /**
     * 检查宏是否存在
     */
    public static function hasMacro(string $name): bool
    {
        return isset(static::$macros[$name]);
    }

    /**
     * 调用宏
     */
    public function __call(string $method, array $parameters): mixed
    {
        if (!static::hasMacro($method)) {
            throw new RuntimeException("Method {$method} does not exist.");
        }

        return static::$macros[$method]->call($this, $parameters);
    }
}
