<?php

declare(strict_types=1);

namespace Bin\Exception;

use Exception;
use Bin\Response\Response;
use Throwable;

/**
 * 全局异常处理器
 */
class Handler
{
    /** @var array<string, callable> 自定义异常处理器 */
    private static array $handlers = [];

    /**
     * 注册全局异常处理器
     */
    public static function register(): void
    {
        set_exception_handler([self::class, 'handle']);
        set_error_handler([self::class, 'handleError']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    /**
     * 处理未捕获的异常
     */
    public static function handle(Throwable $e): void
    {
        // 检查是否有自定义处理器
        $type = get_class($e);
        if (isset(self::$handlers[$type])) {
            $result = call_user_func(self::$handlers[$type], $e);
            if ($result instanceof Response) {
                $result->send();
            }
            return;
        }

        // 根据异常类型处理
        $code = self::getStatusCode($e);

        // 清空输出缓冲区
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $response = self::isDebug()
            ? self::renderDebugResponse($e)
            : self::renderProductionResponse($code);

        $response->send();

        // 记录错误日志
        self::log($e);
    }

    /**
     * 处理 PHP 错误
     */
    public static function handleError(int $errno, string $errstr, string $errfile = '', int $errline = 0): bool
    {
        if (!(error_reporting() & $errno)) {
            return false;
        }

        $errorTypes = [
            E_ERROR => 'E_ERROR',
            E_WARNING => 'E_WARNING',
            E_PARSE => 'E_PARSE',
            E_NOTICE => 'E_NOTICE',
            E_CORE_ERROR => 'E_CORE_ERROR',
            E_CORE_WARNING => 'E_CORE_WARNING',
            E_COMPILE_ERROR => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING => 'E_COMPILE_WARNING',
            E_USER_ERROR => 'E_USER_ERROR',
            E_USER_WARNING => 'E_USER_WARNING',
            E_USER_NOTICE => 'E_USER_NOTICE',
            E_STRICT => 'E_STRICT',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED => 'E_DEPRECATED',
            E_USER_DEPRECATED => 'E_USER_DEPRECATED',
        ];

        $errorType = $errorTypes[$errno] ?? 'UNKNOWN';

        $exception = new \ErrorException(
            "{$errorType}: {$errstr}",
            0,
            $errno,
            $errfile,
            $errline
        );

        self::handle($exception);

        return true;
    }

    /**
     * 处理脚本关闭（捕获致命错误）
     */
    public static function handleShutdown(): void
    {
        $error = error_get_last();

        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            self::handleError(
                $error['type'],
                $error['message'],
                $error['file'],
                $error['line']
            );
        }
    }

    /**
     * 获取 HTTP 状态码
     */
    private static function getStatusCode(Throwable $e): int
    {
        $code = $e->getCode();

        if ($code >= 100 && $code < 600) {
            return $code;
        }

        return match (get_class($e)) {
            'InvalidArgumentException' => 400,
            'RuntimeException' => 500,
            default => 500
        };
    }

    /**
     * 渲染调试页面
     */
    private static function renderDebugResponse(Throwable $e): Response
    {
        $trace = self::formatTrace($e->getTrace());

        $content = "<!DOCTYPE html>
<html>
<head>
    <title>Error - {$e->getMessage()}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #1a1a2e; color: #eee; }
        .container { max-width: 1200px; margin: 0 auto; padding: 40px 20px; }
        .error-header { background: #e94560; padding: 30px; border-radius: 10px; margin-bottom: 30px; }
        .error-type { font-size: 14px; opacity: 0.8; text-transform: uppercase; letter-spacing: 2px; }
        .error-message { font-size: 28px; font-weight: bold; margin-top: 10px; }
        .error-details { background: #16213e; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
        .detail-row { display: flex; padding: 10px 0; border-bottom: 1px solid #2a2a4a; }
        .detail-label { width: 120px; color: #888; }
        .detail-value { color: #4ecca3; font-family: monospace; }
        .trace { background: #16213e; padding: 20px; border-radius: 10px; }
        .trace-title { color: #888; margin-bottom: 15px; }
        .trace-item { padding: 10px; margin-bottom: 5px; background: #1a1a2e; border-radius: 5px; font-family: monospace; font-size: 13px; }
        .trace-line { color: #e94560; }
        .trace-file { color: #4ecca3; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='error-header'>
            <div class='error-type'>{$e->getCode()}</div>
            <div class='error-message'>" . htmlspecialchars($e->getMessage()) . "</div>
        </div>
        <div class='error-details'>
            <div class='detail-row'>
                <span class='detail-label'>Type:</span>
                <span class='detail-value'>" . get_class($e) . "</span>
            </div>
            <div class='detail-row'>
                <span class='detail-label'>File:</span>
                <span class='detail-value'>{$e->getFile()}</span>
            </div>
            <div class='detail-row'>
                <span class='detail-label'>Line:</span>
                <span class='detail-value'>{$e->getLine()}</span>
            </div>
        </div>
        <div class='trace'>
            <div class='trace-title'>Stack Trace</div>
            {$trace}
        </div>
    </div>
</body>
</html>";

        return new Response($content, self::getStatusCode($e), [
            'Content-Type' => 'text/html',
        ]);
    }

    /**
     * 格式化堆栈跟踪
     */
    private static function formatTrace(array $trace): string
    {
        $output = '';

        foreach ($trace as $index => $item) {
            $file = $item['file'] ?? 'unknown';
            $line = $item['line'] ?? '?';
            $class = $item['class'] ?? '';
            $type = $item['type'] ?? '';
            $function = $item['function'] ?? 'unknown';

            $output .= "<div class='trace-item'>
                <span class='trace-line'>#{$index}</span>
                <span class='trace-file'>{$file}:{$line}</span>
                <br>
                <span>{$class}{$type}{$function}()</span>
            </div>";
        }

        return $output;
    }

    /**
     * 渲染生产环境错误页面
     */
    private static function renderProductionResponse(int $code): Response
    {
        $messages = [
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            500 => 'Internal Server Error',
            503 => 'Service Unavailable',
        ];

        $message = $messages[$code] ?? 'Error';

        $content = "<!DOCTYPE html>
<html>
<head>
    <title>{$code} - {$message}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .error-box { text-align: center; padding: 40px; }
        .error-code { font-size: 72px; font-weight: bold; color: #333; margin-bottom: 10px; }
        .error-message { font-size: 24px; color: #666; }
    </style>
</head>
<body>
    <div class='error-box'>
        <div class='error-code'>{$code}</div>
        <div class='error-message'>{$message}</div>
    </div>
</body>
</html>";

        return new Response($content, $code, [
            'Content-Type' => 'text/html',
        ]);
    }

    /**
     * 记录异常日志
     */
    private static function log(Throwable $e): void
    {
        $message = sprintf(
            "[%s] %s: %s in %s:%d\nStack trace:\n%s",
            date('Y-m-d H:i:s'),
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        );

        error_log($message, 3, BASE_PATH . '/storage/logs/error.log');
    }

    /**
     * 检查是否为调试模式
     */
    private static function isDebug(): bool
    {
        return config('app.debug', false) === true;
    }

    /**
     * 注册自定义异常处理器
     */
    public static function extend(string $exceptionClass, callable $handler): void
    {
        self::$handlers[$exceptionClass] = $handler;
    }
}
