<?php

declare(strict_types=1);

if (!function_exists('getUrl')) {
    /**
     * 获取请求 URI
     */
    function getUrl(): string
    {
        return $_SERVER['REQUEST_URI'] ?? '/';
    }
}

if (!function_exists('getMethod')) {
    /**
     * 获取请求方法
     */
    function getMethod(): string
    {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }
}

if (!function_exists('abort')) {
    /**
     * 中止请求并返回错误码
     *
     * 抛出 HttpException，由 ExceptionHandler 统一渲染。
     */
    function abort(int $code, string $message = ''): never
    {
        throw new \Bin\Exception\HttpException($code, $message ?: "Error {$code}");
    }
}

if (!function_exists('response')) {
    /**
     * 创建响应
     */
    function response(mixed $data = '', int $status = 200): \Bin\Response\Response
    {
        $app = \Bin\App\App::getInstance();

        return $app->make(\Bin\Response\ResponseFactory::class)->make($data, $status);
    }
}

if (!function_exists('redirect')) {
    /**
     * 重定向到指定 URL
     */
    function redirect(string $url, int $status = 302): \Bin\Response\Response
    {
        $app = \Bin\App\App::getInstance();

        return $app->make(\Bin\Response\ResponseFactory::class)->redirect($url, $status);
    }
}

if (!function_exists('back')) {
    /**
     * 返回上一页
     */
    function back(): \Bin\Response\Response
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        return redirect($referer);
    }
}

if (!function_exists('is_ajax')) {
    /**
     * 检查是否为 AJAX 请求
     */
    function is_ajax(): bool
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}
