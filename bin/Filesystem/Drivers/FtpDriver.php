<?php

declare(strict_types=1);

namespace Bin\Filesystem\Drivers;

use Bin\Filesystem\FilesystemAdapter;

/**
 * FTP 文件系统驱动
 *
 * 基于 PHP 内置 ftp:// / ftps:// 流包装器实现，无需额外扩展。
 *
 * 配置：
 *   'ftp' => [
 *       'driver' => 'ftp',
 *       'host' => 'ftp.example.com',
 *       'username' => 'user',
 *       'password' => 'pass',
 *       'port' => 21,
 *       'root' => '/pub',          // 远端根前缀
 *       'ssl' => false,            // true 时使用 ftps://
 *       'passive' => true,         // 流包装器默认被动模式
 *       'url' => 'https://cdn...', // url() 前缀（可选）
 *   ],
 */
class FtpDriver implements FilesystemAdapter
{
    protected string $scheme;

    public function __construct(
        protected string $host,
        protected string $username = 'anonymous',
        protected string $password = '',
        protected int $port = 21,
        protected string $root = '/',
        protected bool $ssl = false,
        protected string $urlPrefix = ''
    ) {
        $this->scheme = $ssl ? 'ftps' : 'ftp';
        $this->root = '/' . trim($root, '/');
    }

    // ================================================================
    // 路径映射
    // ================================================================

    /**
     * 远端 URL（ftp://user:pass@host:port/root/path）
     */
    protected function remoteUrl(string $path): string
    {
        $path = '/' . ltrim(str_replace('\\', '/', $path), '/');
        $rooted = rtrim($this->root, '/') . $path;

        return sprintf(
            '%s://%s:%s@%s:%d%s',
            $this->scheme,
            rawurlencode($this->username),
            rawurlencode($this->password),
            $this->host,
            $this->port,
            $rooted
        );
    }

    /**
     * 相对 root 的远端路径
     */
    protected function remotePath(string $path): string
    {
        $path = '/' . ltrim(str_replace('\\', '/', $path), '/');

        return rtrim($this->root, '/') . $path;
    }

    public function url(string $path): string
    {
        if ($this->urlPrefix !== '') {
            return rtrim($this->urlPrefix, '/') . '/' . ltrim($path, '/');
        }

        // 无自定义前缀时给出（不含凭据的）公共 FTP 地址
        return sprintf('%s://%s:%d%s', $this->scheme, $this->host, $this->port, $this->remotePath($path));
    }

    public function path(string $path): string
    {
        return $this->remotePath($path);
    }

    // ================================================================
    // 文件操作（流包装器）
    // ================================================================

    public function exists(string $path): bool
    {
        $url = $this->remoteUrl($path);

        // file_exists 对 ftp:// 包装器不可靠，用 size 探测
        return @$this->size($path) > 0 || $this->directoryExists($path);
    }

    public function get(string $path): ?string
    {
        $content = @file_get_contents($this->remoteUrl($path));

        return $content === false ? null : $content;
    }

    public function put(string $path, mixed $contents, mixed $options = []): bool
    {
        $bytes = @file_put_contents($this->remoteUrl($path), $contents);

        return $bytes !== false;
    }

    public function append(string $path, string $data): bool
    {
        $existing = $this->get($path) ?? '';

        return $this->put($path, $existing . $data);
    }

    public function delete(string $path): bool
    {
        return @unlink($this->remoteUrl($path));
    }

    public function copy(string $from, string $to): bool
    {
        $content = $this->get($from);

        if ($content === null) {
            return false;
        }

        return $this->put($to, $content);
    }

    public function move(string $from, string $to): bool
    {
        if (!$this->copy($from, $to)) {
            return false;
        }

        return $this->delete($from);
    }

    public function size(string $path): int
    {
        $size = @filesize($this->remoteUrl($path));

        return $size === false ? 0 : (int) $size;
    }

    public function lastModified(string $path): int
    {
        $mtime = @filemtime($this->remoteUrl($path));

        return $mtime === false ? 0 : (int) $mtime;
    }

    public function makeDirectory(string $path): bool
    {
        return @ftp_mkdir($this->rawConnection(), $this->remotePath($path)) !== false;
    }

    public function deleteDirectory(string $path): bool
    {
        // 递归删除目录内容后移除目录本身
        foreach ($this->allFiles($path) as $file) {
            $this->delete($file);
        }

        foreach (array_reverse($this->allDirectories($path)) as $directory) {
            @ftp_rmdir($this->rawConnection(), $this->remotePath($directory));
        }

        return @ftp_rmdir($this->rawConnection(), $this->remotePath($path));
    }

    public function files(string $directory): array
    {
        return $this->listDirectory($directory, false);
    }

    public function allFiles(string $directory): array
    {
        $result = $this->listDirectory($directory, false);

        foreach ($this->allDirectories($directory) as $sub) {
            $result = array_merge($result, $this->allFiles($sub));
        }

        return $result;
    }

    public function directories(string $directory): array
    {
        return $this->listDirectory($directory, true);
    }

    public function allDirectories(string $directory): array
    {
        $result = [];
        $this->collectDirectories($directory, $result);

        return $result;
    }

    // ================================================================
    // 内部
    // ================================================================

    protected function directoryExists(string $path): bool
    {
        return @is_dir($this->remoteUrl($path));
    }

    /**
     * @param array<string> $result
     */
    protected function collectDirectories(string $directory, array &$result): void
    {
        foreach ($this->directories($directory) as $sub) {
            $result[] = $sub;
            $this->collectDirectories($sub, $result);
        }
    }

    /**
     * 通过 ftp:// 目录列表读取（每行一个条目；目录以 / 结尾不可靠，用 is_dir 复核）
     *
     * @return array<string>
     */
    protected function listDirectory(string $directory, bool $dirsOnly): array
    {
        $entries = @file($this->remoteUrl(rtrim($directory, '/') . '/'));

        if ($entries === false) {
            return [];
        }

        $result = [];
        $base = trim($directory, '/');

        foreach ($entries as $entry) {
            $name = trim($entry);

            if ($name === '' || str_ends_with($name, ':') || $name === '.' || $name === '..') {
                continue;
            }

            $relative = ($base !== '' ? $base . '/' : '') . $name;
            $isDir = $this->directoryExists($relative);

            if ($dirsOnly === $isDir) {
                $result[] = $relative;
            }
        }

        return $result;
    }

    /**
     * 建立 FTP 原生连接（mkdir/rmdir 需要，流包装器不支持建目录）
     */
    protected function rawConnection(): \FTP\Connection|false
    {
        if (!function_exists('ftp_connect')) {
            return false;
        }

        $connection = $this->ssl ? @ftp_ssl_connect($this->host, $this->port) : @ftp_connect($this->host, $this->port);

        if ($connection === false) {
            return false;
        }

        if (!@ftp_login($connection, $this->username, $this->password)) {
            ftp_close($connection);

            return false;
        }

        @ftp_pasv($connection, true);

        return $connection;
    }
}
