<?php

declare(strict_types=1);

namespace Bin\Facade;

use Bin\Config\ConfigRepository;

/**
 * Config Facade - 静态代理配置仓库
 *
 * @method static mixed get(string $key, mixed $default = null)
 * @method static void set(string $key, mixed $value)
 * @method static bool has(string $key)
 * @method static array all()
 * @method static void load(string $file)
 * @method static void preload(array $files)
 * @method static bool save(string $file, ?array $config = null)
 */
class Config extends Facade
{
    protected function getClassName(): string
    {
        return ConfigRepository::class;
    }
}
