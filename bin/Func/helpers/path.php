<?php

declare(strict_types=1);

if (!function_exists('basePath')) {
    /**
     * 获取项目根路径
     */
    function basePath(string $path = ''): string
    {
        return BASE_PATH . ($path !== '' ? DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : '');
    }
}
