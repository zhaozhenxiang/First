<?php

declare(strict_types=1);

namespace Bin\Database\Seeders;

/**
 * Seeder 管理器
 *
 * 管理和执行数据库填充
 */
class SeederRepository
{
    /** @var array<string> 已注册的 Seeder */
    private static array $seeders = [];

    /** @var string Seeder 搜索路径 */
    private static array $paths = [];

    /** @var string 应用命名空间 */
    private static string $appNamespace = 'App\\Seeders\\';

    /**
     * 注册 Seeder
     */
    public static function register(string $name, string $class): void
    {
        self::$seeders[$name] = $class;
    }

    /**
     * 批量注册 Seeder
     */
    public static function registerMany(array $seeders): void
    {
        foreach ($seeders as $name => $class) {
            self::register($name, $class);
        }
    }

    /**
     * 添加搜索路径
     */
    public static function addPath(string $path): void
    {
        self::$paths[] = $path;
    }

    /**
     * 设置应用命名空间
     */
    public static function setAppNamespace(string $namespace): void
    {
        self::$appNamespace = $namespace;
    }

    /**
     * 发现所有 Seeder
     */
    public static function discover(): array
    {
        $paths = array_merge([
            basePath('database/seeders'),
        ], self::$paths);

        $seeders = self::$seeders;

        foreach ($paths as $path) {
            if (!is_dir($path)) {
                continue;
            }

            $files = glob($path . '/*Seeder.php');

            foreach ($files as $file) {
                $className = self::pathToClassName($file);
                $name = self::classToName($className);

                if (!isset($seeders[$name])) {
                    $seeders[$name] = $className;
                }
            }
        }

        return $seeders;
    }

    /**
     * 路径转类名
     *
     * Seeder 文件由 SeederCreator 生成，命名空间固定为 Database\Seeders
     * （与 database/seeders/ 下现有文件一致），直接按文件名推导。
     */
    private static function pathToClassName(string $path): string
    {
        return 'Database\\Seeders\\' . basename($path, '.php');
    }

    /**
     * 类名转 Seeder 名称
     */
    private static function classToName(string $className): string
    {
        $name = basename(str_replace('\\', '/', $className));
        return strtolower(str_replace('Seeder', '', $name));
    }

    /**
     * 获取所有 Seeder
     */
    public static function all(): array
    {
        return self::discover();
    }

    /**
     * 获取 Seeder 实例
     */
    public static function get(string $name): ?Seeder
    {
        $seeders = self::all();

        if (!isset($seeders[$name])) {
            return null;
        }

        $class = $seeders[$name];

        if (!class_exists($class)) {
            // 尝试加载文件
            $file = basePath('database/seeders/' . basename(str_replace('\\', '/', $class)) . '.php');
            if (file_exists($file)) {
                require_once $file;
            }
        }

        if (class_exists($class)) {
            return new $class();
        }

        return null;
    }

    /**
     * 运行指定 Seeder
     */
    public static function run(string $name): void
    {
        $seeder = self::get($name);

        if ($seeder === null) {
            throw new \RuntimeException("Seeder not found: {$name}");
        }

        $seeder->run();
    }

    /**
     * 运行所有 Seeder
     */
    public static function runAll(): void
    {
        $seeders = self::all();

        foreach ($seeders as $name => $class) {
            self::run($name);
        }
    }

    /**
     * 清除所有注册
     */
    public static function clear(): void
    {
        self::$seeders = [];
        self::$paths = [];
    }

    /**
     * 获取应用命名空间
     */
    public static function getAppNamespace(): string
    {
        return self::$appNamespace;
    }
}
