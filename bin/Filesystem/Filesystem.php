<?php

declare(strict_types=1);

namespace Bin\Filesystem;

/**
 * 文件系统核心类
 *
 * 提供统一的文件操作 API，通过驱动适配不同的存储后端。
 */
class Filesystem
{
    public function __construct(
        protected FilesystemAdapter $adapter
    ) {
    }

    /**
     * 获取适配器
     */
    public function getAdapter(): FilesystemAdapter
    {
        return $this->adapter;
    }

    /**
     * 文件是否存在
     */
    public function exists(string $path): bool
    {
        return $this->adapter->exists($path);
    }

    /**
     * 文件是否缺失
     */
    public function missing(string $path): bool
    {
        return !$this->exists($path);
    }

    /**
     * 获取文件内容
     */
    public function get(string $path): ?string
    {
        return $this->adapter->get($path);
    }

    /**
     * 获取文件内容（找不到时返回默认值）
     */
    public function getOrElse(string $path, string $default = ''): string
    {
        return $this->adapter->get($path) ?? $default;
    }

    /**
     * 写入文件内容
     */
    public function put(string $path, mixed $contents, mixed $options = []): bool
    {
        return $this->adapter->put($path, $contents, $options);
    }

    /**
     * 追加内容到文件
     */
    public function append(string $path, string $data): bool
    {
        return $this->adapter->append($path, $data);
    }

    /**
     * 前置内容到文件
     */
    public function prepend(string $path, string $data): bool
    {
        if ($this->exists($path)) {
            return $this->put($path, $data . $this->get($path));
        }
        return $this->put($path, $data);
    }

    /**
     * 删除文件
     */
    public function delete(string|array $paths): bool
    {
        $paths = is_array($paths) ? $paths : [$paths];
        $allDeleted = true;

        foreach ($paths as $path) {
            if (!$this->adapter->delete($path)) {
                $allDeleted = false;
            }
        }

        return $allDeleted;
    }

    /**
     * 复制文件
     */
    public function copy(string $from, string $to): bool
    {
        return $this->adapter->copy($from, $to);
    }

    /**
     * 移动文件
     */
    public function move(string $from, string $to): bool
    {
        return $this->adapter->move($from, $to);
    }

    /**
     * 获取文件大小（字节）
     */
    public function size(string $path): int
    {
        return $this->adapter->size($path);
    }

    /**
     * 获取最后修改时间（Unix timestamp）
     */
    public function lastModified(string $path): int
    {
        return $this->adapter->lastModified($path);
    }

    /**
     * 获取文件 URL
     */
    public function url(string $path): string
    {
        return $this->adapter->url($path);
    }

    /**
     * 获取文件的完整路径
     */
    public function path(string $path): string
    {
        return $this->adapter->path($path);
    }

    /**
     * 创建目录
     */
    public function makeDirectory(string $path): bool
    {
        return $this->adapter->makeDirectory($path);
    }

    /**
     * 删除目录
     */
    public function deleteDirectory(string $path): bool
    {
        return $this->adapter->deleteDirectory($path);
    }

    /**
     * 列出目录下的文件
     *
     * @return array<string>
     */
    public function files(string $directory): array
    {
        return $this->adapter->files($directory);
    }

    /**
     * 列出目录下的所有文件（递归）
     *
     * @return array<string>
     */
    public function allFiles(string $directory): array
    {
        return $this->adapter->allFiles($directory);
    }

    /**
     * 列出目录下的子目录
     *
     * @return array<string>
     */
    public function directories(string $directory): array
    {
        return $this->adapter->directories($directory);
    }

    /**
     * 列出目录下的所有子目录（递归）
     *
     * @return array<string>
     */
    public function allDirectories(string $directory): array
    {
        return $this->adapter->allDirectories($directory);
    }
}
