<?php

declare(strict_types=1);

namespace Bin\Filesystem;

use Bin\Filesystem\Drivers\FtpDriver;
use Bin\Filesystem\Drivers\LocalDriver;
use RuntimeException;

/**
 * 存储管理器
 *
 * 管理多个磁盘（disk）实例，通过配置文件定义磁盘驱动。
 *
 * 用法：
 *   $storage = StorageManager::getInstance();
 *   $storage->disk('local')->put('file.txt', 'content');
 *   $storage->disk('local')->get('file.txt');
 */
class StorageManager
{
    /** @var array<string, Filesystem> 已解析的磁盘实例 */
    protected array $disks = [];

    /** @var string 默认磁盘名 */
    protected string $defaultDisk = '';

    /** @var array 磁盘配置 */
    protected array $config = [];

    /** @var self|null 单例 */
    private static ?self $instance = null;

    public function __construct()
    {
        if ($this->config === [] && function_exists('config')) {
            $this->config = config('filesystems.disks') ?? [];
            $this->defaultDisk = config('filesystems.default') ?? 'local';
        }
        if ($this->defaultDisk === '') {
            $this->defaultDisk = 'local';
        }
    }

    /**
     * 获取单例
     */
    public static function getInstance(): static
    {
        return self::$instance ??= new static();
    }

    /**
     * 重置单例（测试用）
     */
    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    /**
     * 设置配置
     */
    public function setConfig(array $config): static
    {
        $this->config = $config;
        return $this;
    }

    /**
     * 设置默认磁盘
     */
    public function setDefaultDisk(string $name): static
    {
        $this->defaultDisk = $name;
        return $this;
    }

    /**
     * 获取磁盘实例
     */
    public function disk(?string $name = null): Filesystem
    {
        $name = $name ?? $this->defaultDisk;

        if (!isset($this->disks[$name])) {
            $this->disks[$name] = $this->resolveDisk($name);
        }

        return $this->disks[$name];
    }

    /**
     * 解析磁盘配置为 Filesystem 实例
     */
    protected function resolveDisk(string $name): Filesystem
    {
        $config = $this->config[$name] ?? [];

        if ($config === []) {
            throw new RuntimeException("Disk [{$name}] is not configured.");
        }

        $driver = $config['driver'] ?? 'local';

        $adapter = match ($driver) {
            'local' => $this->createLocalAdapter($config),
            'ftp' => $this->createFtpAdapter($config),
            default => throw new RuntimeException("Unsupported filesystem driver: {$driver}"),
        };

        return new Filesystem($adapter);
    }

    /**
     * 创建本地适配器
     */
    protected function createLocalAdapter(array $config): LocalDriver
    {
        $root = $config['root'] ?? storage_path('app');
        $url = $config['url'] ?? '';

        return new LocalDriver($root, $url);
    }

    /**
     * 创建 FTP 适配器（基于内置 ftp:// 流包装器，无需扩展）
     */
    protected function createFtpAdapter(array $config): FtpDriver
    {
        return new FtpDriver(
            (string) ($config['host'] ?? '127.0.0.1'),
            (string) ($config['username'] ?? 'anonymous'),
            (string) ($config['password'] ?? ''),
            (int) ($config['port'] ?? 21),
            (string) ($config['root'] ?? '/'),
            (bool) ($config['ssl'] ?? false),
            (string) ($config['url'] ?? '')
        );
    }

    /**
     * 清除已解析的磁盘实例
     */
    public function flush(): void
    {
        $this->disks = [];
    }

    // ─── 代理方法（直接操作默认磁盘）───

    public function exists(string $path): bool
    {
        return $this->disk()->exists($path);
    }

    public function get(string $path): ?string
    {
        return $this->disk()->get($path);
    }

    public function put(string $path, mixed $contents, mixed $options = []): bool
    {
        return $this->disk()->put($path, $contents, $options);
    }

    public function delete(string|array $paths): bool
    {
        return $this->disk()->delete($paths);
    }

    public function copy(string $from, string $to): bool
    {
        return $this->disk()->copy($from, $to);
    }

    public function move(string $from, string $to): bool
    {
        return $this->disk()->move($from, $to);
    }

    public function url(string $path): string
    {
        return $this->disk()->url($path);
    }

    public function path(string $path): string
    {
        return $this->disk()->path($path);
    }

    public function size(string $path): int
    {
        return $this->disk()->size($path);
    }

    public function lastModified(string $path): int
    {
        return $this->disk()->lastModified($path);
    }

    public function files(string $directory): array
    {
        return $this->disk()->files($directory);
    }

    public function allFiles(string $directory): array
    {
        return $this->disk()->allFiles($directory);
    }

    public function makeDirectory(string $path): bool
    {
        return $this->disk()->makeDirectory($path);
    }

    public function deleteDirectory(string $path): bool
    {
        return $this->disk()->deleteDirectory($path);
    }

    public function append(string $path, string $data): bool
    {
        return $this->disk()->append($path, $data);
    }

    public function prepend(string $path, string $data): bool
    {
        return $this->disk()->prepend($path, $data);
    }
}
