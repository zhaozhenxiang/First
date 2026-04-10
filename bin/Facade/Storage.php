<?php

declare(strict_types=1);

namespace Bin\Facade;

use Bin\Filesystem\StorageManager;

/**
 * Storage Facade - 静态代理存储管理器
 *
 * @method static \Bin\Filesystem\Filesystem disk(?string $name = null)
 * @method static bool exists(string $path)
 * @method static string|null get(string $path)
 * @method static bool put(string $path, mixed $contents, mixed $options = [])
 * @method static bool delete(string|array $paths)
 * @method static bool copy(string $from, string $to)
 * @method static bool move(string $from, string $to)
 * @method static string url(string $path)
 * @method static string path(string $path)
 * @method static int size(string $path)
 * @method static int lastModified(string $path)
 * @method static array files(string $directory)
 * @method static array allFiles(string $directory)
 * @method static bool makeDirectory(string $path)
 * @method static bool deleteDirectory(string $path)
 * @method static bool append(string $path, string $data)
 * @method static bool prepend(string $path, string $data)
 */
class Storage extends Facade
{
    protected function getClassName(): string
    {
        return StorageManager::class;
    }

    protected static function getInstance(): object
    {
        return StorageManager::getInstance();
    }
}
