<?php

declare(strict_types=1);

namespace Bin\Filesystem\Drivers;

use Bin\Filesystem\FilesystemAdapter;

/**
 * 本地文件系统驱动
 */
class LocalDriver implements FilesystemAdapter
{
    public function __construct(
        protected string $root,
        protected string $urlPrefix = ''
    ) {
        $this->root = rtrim($root, '/\\');
    }

    /**
     * 解析完整路径
     */
    protected function fullPath(string $path): string
    {
        if ($path === '' || $path === '/' || $path === '\\') {
            return $this->root;
        }
        $path = ltrim($path, '/\\');
        return $this->root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }

    /**
     * 获取相对路径
     */
    protected function relativePath(string $fullPath): string
    {
        $relative = substr($fullPath, strlen($this->root) + 1);
        return str_replace(DIRECTORY_SEPARATOR, '/', $relative);
    }

    public function exists(string $path): bool
    {
        return file_exists($this->fullPath($path));
    }

    public function get(string $path): ?string
    {
        $fullPath = $this->fullPath($path);

        if (!file_exists($fullPath)) {
            return null;
        }

        $content = file_get_contents($fullPath);

        return $content === false ? null : $content;
    }

    public function put(string $path, mixed $contents, mixed $options = []): bool
    {
        $fullPath = $this->fullPath($path);

        // 确保目录存在
        $directory = dirname($fullPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $result = file_put_contents($fullPath, $contents);

        return $result !== false;
    }

    public function append(string $path, string $data): bool
    {
        $fullPath = $this->fullPath($path);

        $directory = dirname($fullPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $result = file_put_contents($fullPath, $data, FILE_APPEND);

        return $result !== false;
    }

    public function delete(string $path): bool
    {
        $fullPath = $this->fullPath($path);

        if (!file_exists($fullPath)) {
            return true;
        }

        if (is_dir($fullPath)) {
            return false; // 使用 deleteDirectory
        }

        return unlink($fullPath);
    }

    public function copy(string $from, string $to): bool
    {
        $fromPath = $this->fullPath($from);
        $toPath = $this->fullPath($to);

        if (!file_exists($fromPath)) {
            return false;
        }

        $directory = dirname($toPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return copy($fromPath, $toPath);
    }

    public function move(string $from, string $to): bool
    {
        $fromPath = $this->fullPath($from);
        $toPath = $this->fullPath($to);

        if (!file_exists($fromPath)) {
            return false;
        }

        $directory = dirname($toPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return rename($fromPath, $toPath);
    }

    public function size(string $path): int
    {
        $fullPath = $this->fullPath($path);

        if (!file_exists($fullPath)) {
            return 0;
        }

        return (int) filesize($fullPath);
    }

    public function lastModified(string $path): int
    {
        $fullPath = $this->fullPath($path);

        if (!file_exists($fullPath)) {
            return 0;
        }

        return (int) filemtime($fullPath);
    }

    public function url(string $path): string
    {
        if ($this->urlPrefix !== '') {
            return rtrim($this->urlPrefix, '/') . '/' . ltrim($path, '/');
        }

        return '/' . ltrim($path, '/');
    }

    public function path(string $path): string
    {
        return $this->fullPath($path);
    }

    public function makeDirectory(string $path): bool
    {
        $fullPath = $this->fullPath($path);

        if (is_dir($fullPath)) {
            return true;
        }

        return mkdir($fullPath, 0755, true);
    }

    public function deleteDirectory(string $path): bool
    {
        $fullPath = $this->fullPath($path);

        if (!is_dir($fullPath)) {
            return true;
        }

        return $this->removeDirectory($fullPath);
    }

    public function files(string $directory): array
    {
        $fullPath = $this->fullPath($directory);

        if (!is_dir($fullPath)) {
            return [];
        }

        $result = [];
        $items = scandir($fullPath);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $itemPath = $fullPath . DIRECTORY_SEPARATOR . $item;
            if (is_file($itemPath)) {
                $result[] = $this->relativePath($itemPath);
            }
        }

        return $result;
    }

    public function allFiles(string $directory): array
    {
        $fullPath = $this->fullPath($directory);

        if (!is_dir($fullPath)) {
            return [];
        }

        $result = [];
        $this->collectAllFiles($fullPath, $result);

        return $result;
    }

    public function directories(string $directory): array
    {
        $fullPath = $this->fullPath($directory);

        if (!is_dir($fullPath)) {
            return [];
        }

        $result = [];
        $items = scandir($fullPath);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $itemPath = $fullPath . DIRECTORY_SEPARATOR . $item;
            if (is_dir($itemPath)) {
                $result[] = $this->relativePath($itemPath);
            }
        }

        return $result;
    }

    public function allDirectories(string $directory): array
    {
        $fullPath = $this->fullPath($directory);

        if (!is_dir($fullPath)) {
            return [];
        }

        $result = [];
        $this->collectAllDirectories($fullPath, $result);

        return $result;
    }

    /**
     * 递归收集所有文件
     */
    protected function collectAllFiles(string $dir, array &$result): void
    {
        $items = scandir($dir);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $itemPath = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($itemPath)) {
                $this->collectAllFiles($itemPath, $result);
            } else {
                $result[] = $this->relativePath($itemPath);
            }
        }
    }

    /**
     * 递归收集所有目录
     */
    protected function collectAllDirectories(string $dir, array &$result): void
    {
        $items = scandir($dir);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $itemPath = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($itemPath)) {
                $result[] = $this->relativePath($itemPath);
                $this->collectAllDirectories($itemPath, $result);
            }
        }
    }

    /**
     * 递归删除目录
     */
    protected function removeDirectory(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }

        $items = scandir($dir);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $itemPath = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($itemPath)) {
                $this->removeDirectory($itemPath);
            } else {
                unlink($itemPath);
            }
        }

        return rmdir($dir);
    }
}
