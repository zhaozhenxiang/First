<?php

declare(strict_types=1);

namespace Bin\Filesystem;

/**
 * 文件系统适配器接口
 *
 * 所有驱动必须实现此接口。
 */
interface FilesystemAdapter
{
    public function exists(string $path): bool;
    public function get(string $path): ?string;
    public function put(string $path, mixed $contents, mixed $options = []): bool;
    public function append(string $path, string $data): bool;
    public function delete(string $path): bool;
    public function copy(string $from, string $to): bool;
    public function move(string $from, string $to): bool;
    public function size(string $path): int;
    public function lastModified(string $path): int;
    public function url(string $path): string;
    public function path(string $path): string;
    public function makeDirectory(string $path): bool;
    public function deleteDirectory(string $path): bool;

    /**
     * @return array<string>
     */
    public function files(string $directory): array;

    /**
     * @return array<string>
     */
    public function allFiles(string $directory): array;

    /**
     * @return array<string>
     */
    public function directories(string $directory): array;

    /**
     * @return array<string>
     */
    public function allDirectories(string $directory): array;
}
