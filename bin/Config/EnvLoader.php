<?php

declare(strict_types=1);

namespace Bin\Config;

/**
 * .env 文件加载器
 */
class EnvLoader
{
    /**
     * 加载 .env 文件到 $_ENV 和 putenv()
     */
    public static function load(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);

            // 跳过注释
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // 去除 export 前缀
            if (str_starts_with($line, 'export ')) {
                $line = substr($line, 7);
            }

            // 必须包含 =
            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);

            $key = trim($key);
            $value = trim($value);

            // 跳过空键名
            if ($key === '') {
                continue;
            }

            // 去除引号
            if (preg_match('/^["\'](.*)["\']\s*$/', $value, $matches)) {
                $value = $matches[1];
            }

            // 不覆盖已有环境变量
            if (isset($_ENV[$key]) || getenv($key) !== false) {
                continue;
            }

            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }
    }
}
