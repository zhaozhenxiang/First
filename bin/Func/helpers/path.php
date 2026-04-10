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

if (!function_exists('storage_path')) {
    /**
     * 获取 storage 目录路径
     */
    function storage_path(string $path = ''): string
    {
        return BASE_PATH . DIRECTORY_SEPARATOR . 'storage' . ($path !== '' ? DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : '');
    }
}

if (!function_exists('config_path')) {
    /**
     * 获取 config 目录路径
     */
    function config_path(string $path = ''): string
    {
        return BASE_PATH . DIRECTORY_SEPARATOR . 'config' . ($path !== '' ? DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : '');
    }
}
