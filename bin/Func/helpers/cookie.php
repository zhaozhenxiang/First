<?php

declare(strict_types=1);

if (!function_exists('cookie')) {
    /**
     * 获取或设置 Cookie
     */
    function cookie(?string $name = null, mixed $value = null, int $minutes = 0): mixed
    {
        if ($name === null) {
            return \Bin\Cookie\CookieManager::all();
        }

        if ($value === null) {
            return \Bin\Cookie\CookieManager::get($name);
        }

        return \Bin\Cookie\CookieManager::set($name, $value, $minutes);
    }
}

if (!function_exists('cookie_has')) {
    /**
     * 检查 Cookie 是否存在
     */
    function cookie_has(string $name): bool
    {
        return \Bin\Cookie\CookieManager::has($name);
    }
}

if (!function_exists('cookie_forget')) {
    /**
     * 删除 Cookie
     */
    function cookie_forget(string $name): bool
    {
        return \Bin\Cookie\CookieManager::forget($name);
    }
}

if (!function_exists('cookie_forever')) {
    /**
     * 设置永久 Cookie（5 年）
     */
    function cookie_forever(string $name, string $value): bool
    {
        return \Bin\Cookie\CookieManager::forever($name, $value);
    }
}
