<?php

declare(strict_types=1);

namespace Bin\Exception;

use Bin\App\App;
use Bin\Request\Request;
use Bin\Response\Response;
use Bin\View\View;
use Closure;
use Throwable;

/**
 * 全局异常处理器
 */
class ExceptionHandler
{
    /**
     * 应用调试模式
     */
    protected bool $debug = false;

    /**
     * 异常报告回调
     * @var array<string, Closure>
     */
    protected array $reportCallbacks = [];

    /**
     * 异常渲染回调
     * @var array<string, Closure>
     */
    protected array $renderCallbacks = [];

    /**
     * 不应报告的异常类型
     * @var array<string>
     */
    protected array $dontReport = [];

    /**
     * 构造函数
     */
    public function __construct(bool $debug = false)
    {
        $this->debug = $debug;

        // 默认不报告的异常
        $this->dontReport = [
            AuthenticationException::class,
            ValidationException::class,
            NotFoundHttpException::class,
        ];
    }

    /**
     * 注册异常处理器
     */
    public function register(): void
    {
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleShutdown']);
    }

    /**
     * 报告异常
     */
    public function report(Throwable $e): void
    {
        if ($this->shouldntReport($e)) {
            return;
        }

        // 执行自定义报告回调
        foreach ($this->reportCallbacks as $type => $callback) {
            if ($e instanceof $type) {
                $callback($e);
                return;
            }
        }

        // 默认记录到日志
        $this->logException($e);
    }

    /**
     * 渲染异常
     */
    public function render(Throwable $e): ?Response
    {
        // 执行自定义渲染回调
        foreach ($this->renderCallbacks as $type => $callback) {
            if ($e instanceof $type) {
                return $callback($e);
            }
        }

        // 默认渲染
        return $this->renderException($e);
    }

    public function renderForConsole(Throwable $e): string
    {
        if ($this->debug) {
            return sprintf(
                "%s: %s in %s:%d\n%s\n",
                get_class($e),
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                $e->getTraceAsString()
            );
        }

        return $e->getMessage() . PHP_EOL;
    }

    /**
     * 处理异常
     */
    public function handleException(Throwable $e): void
    {
        $this->report($e);

        $response = $this->render($e);

        if ($response !== null) {
            $response->send();
        }

        exit(1);
    }

    /**
     * 处理 PHP 错误
     */
    public function handleError(int $level, string $message, string $file = '', int $line = 0): bool
    {
        if (!(error_reporting() & $level)) {
            return false;
        }

        throw new \ErrorException($message, 0, $level, $file, $line);
    }

    /**
     * 处理脚本关闭
     */
    public function handleShutdown(): void
    {
        $error = error_get_last();

        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $this->handleException(
                new \ErrorException(
                    $error['message'],
                    0,
                    $error['type'],
                    $error['file'],
                    $error['line']
                )
            );
        }
    }

    /**
     * 渲染异常为响应
     */
    protected function renderException(Throwable $e): Response
    {
        $status = $this->getStatus($e);
        $expectsJson = $this->expectsJsonResponse();

        if ($expectsJson) {
            return $this->renderJson($e, $status);
        }

        // Web 请求的 ValidationException：重定向回上一页并闪存错误
        if ($e instanceof ValidationException) {
            return $this->renderValidationRedirect($e);
        }

        // 如果调试模式开启，显示详细错误页
        if ($this->debug) {
            return $this->renderDebugPage($e, $status);
        }

        // 否则显示通用错误页
        return $this->renderErrorPage($status);
    }

    /**
     * 渲染验证失败重定向
     */
    protected function renderValidationRedirect(ValidationException $e): Response
    {
        $errors = $e->getErrors();
        $referer = $this->sanitizeRedirectUrl($_SERVER['HTTP_REFERER'] ?? '/');

        // 闪存错误到 session
        if (function_exists('session_manager')) {
            try {
                $manager = session_manager();
                if ($manager !== null && method_exists($manager, 'flash')) {
                    $manager->flash('_errors', $errors);
                    // 闪存旧输入
                    if (isset($_POST)) {
                        $manager->flash('_old_input', $_POST);
                    }
                }
            } catch (\Throwable) {
                // session 不可用时静默降级
            }
        }

        return $this->responseFactory()->redirect($referer, 302);
    }

    /**
     * 净化重定向 URL，防止响应拆分攻击
     */
    protected function sanitizeRedirectUrl(string $url): string
    {
        // 移除 CRLF 字符防止 header 注入
        $url = str_replace(["\r", "\n", "\t"], '', $url);

        // 确保是相对路径或合法 HTTP(S) URL
        if (!str_starts_with($url, '/') && !preg_match('#^https?://#i', $url)) {
            return '/';
        }

        return $url;
    }

    /**
     * 渲染 JSON 响应
     */
    protected function renderJson(Throwable $e, int $status): Response
    {
        $data = [
            'error' => [
                'message' => $this->debug ? $e->getMessage() : $this->getGenericMessage($status),
                'status' => $status,
            ],
        ];

        // ValidationException 附加 errors 字段
        if ($e instanceof ValidationException) {
            $data['error']['errors'] = $e->getErrors();
        }

        // RateLimitExceededException 附加 retry_after
        if ($e instanceof RateLimitExceededException) {
            $headers = $e->getHeaders();
            if (isset($headers['Retry-After'])) {
                $data['error']['retry_after'] = $headers['Retry-After'];
            }
        }

        if ($this->debug) {
            $data['error']['exception'] = get_class($e);
            $data['error']['file'] = $e->getFile();
            $data['error']['line'] = $e->getLine();
            $data['error']['trace'] = $e->getTraceAsString();
        }

        return $this->responseFactory()->json($data, $status);
    }

    protected function responseFactory(): \Bin\Response\ResponseFactory
    {
        try {
            return \Bin\App\App::getInstance()->make(\Bin\Response\ResponseFactory::class);
        } catch (\Throwable) {
            return new \Bin\Response\ResponseFactory();
        }
    }

    /**
     * 渲染调试页面
     */
    protected function renderDebugPage(Throwable $e, int $status): Response
    {
        $data = [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'status' => $status,
        ];

        // 尝试使用视图渲染
        try {
            $content = View::make('errors/debug')->with($data)->render();
            return new Response($content, $status);
        } catch (\Throwable) {
            // 视图渲染失败，返回简单 HTML
            $content = $this->generateDebugHtml($data);
            return new Response($content, $status, ['Content-Type' => 'text/html']);
        }
    }

    /**
     * 渲染错误页面
     */
    protected function renderErrorPage(int $status): Response
    {
        $view = "errors/{$status}";

        try {
            $content = View::make($view)->render();
            return new Response($content, $status);
        } catch (\Throwable) {
            // 视图不存在，返回默认错误页
            $content = $this->getGenericErrorContent($status);
            return new Response($content, $status, ['Content-Type' => 'text/html']);
        }
    }

    /**
     * 生成调试 HTML
     */
    protected function generateDebugHtml(array $data): string
    {
        extract($data);

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>Error {$status}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); overflow: hidden; }
        .header { background: #e74c3c; color: white; padding: 20px; }
        .header h1 { margin: 0; font-size: 24px; }
        .content { padding: 20px; }
        .section { margin-bottom: 20px; }
        .section h3 { margin: 0 0 10px 0; color: #333; border-bottom: 1px solid #eee; padding-bottom: 5px; }
        .code { background: #f8f8f8; padding: 10px; border-radius: 4px; font-family: 'Monaco', 'Menlo', monospace; font-size: 13px; overflow-x: auto; }
        .trace { font-size: 12px; color: #666; }
        .trace li { margin: 5px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{$exception} - Error {$status}</h1>
        </div>
        <div class="content">
            <div class="section">
                <h3>Message</h3>
                <div class="code">{$message}</div>
            </div>
            <div class="section">
                <h3>Location</h3>
                <div class="code">{$file}:{$line}</div>
            </div>
            <div class="section">
                <h3>Stack Trace</h3>
                <ul class="trace"><li>{$trace}</li></ul>
            </div>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * 获取通用错误内容
     */
    protected function getGenericErrorContent(int $status): string
    {
        $message = $this->getGenericMessage($status);

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>Error {$status}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin: 0; padding: 0; display: flex; align-items: center; justify-content: center; min-height: 100vh; background: #f5f5f5; }
        .error-page { text-align: center; padding: 40px; }
        .error-code { font-size: 72px; font-weight: bold; color: #e74c3c; margin: 0; }
        .error-message { font-size: 24px; color: #333; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="error-page">
        <h1 class="error-code">{$status}</h1>
        <p class="error-message">{$message}</p>
    </div>
</body>
</html>
HTML;
    }

    /**
     * 获取异常状态码
     */
    protected function getStatus(Throwable $e): int
    {
        if ($e instanceof NotFoundHttpException) {
            return 404;
        }

        if ($e instanceof AuthenticationException) {
            return 401;
        }

        if ($e instanceof AuthorizationException) {
            return 403;
        }

        if ($e instanceof ValidationException) {
            return 422;
        }

        if ($e instanceof MethodNotAllowedHttpException) {
            return 405;
        }

        if ($e instanceof RateLimitExceededException) {
            return 429;
        }

        if ($e instanceof HttpException) {
            return $e->getStatusCode();
        }

        return 500;
    }

    /**
     * 获取通用错误消息
     */
    protected function getGenericMessage(int $status): string
    {
        return match ($status) {
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            422 => 'Unprocessable Entity',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            503 => 'Service Unavailable',
            default => 'An error occurred',
        };
    }

    /**
     * 将错误级别转换为字符串
     */
    protected function getErrorFromLevel(int $level): string
    {
        return match ($level) {
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
            default => 'Unknown error',
        };
    }

    /**
     * 检查异常是否不应报告
     */
    protected function shouldntReport(Throwable $e): bool
    {
        foreach ($this->dontReport as $type) {
            if ($e instanceof $type) {
                return true;
            }
        }

        return false;
    }

    /**
     * 记录异常到日志
     */
    protected function logException(Throwable $e): void
    {
        $message = sprintf(
            "%s: %s in %s:%d\nStack trace:\n%s",
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        );

        error_log($message);
    }

    /**
     * 检查是否是 AJAX 请求
     */
    protected function isAjax(): bool
    {
        return is_ajax();
    }

    protected function expectsJsonResponse(): bool
    {
        try {
            $app = App::getInstance();
            if ($app->bound(Request::class)) {
                $request = $app->make(Request::class);
                if ($request instanceof Request) {
                    return $request->expectsJson();
                }
            }
        } catch (\Throwable) {
            return $this->isAjax();
        }

        return $this->isAjax();
    }

    /**
     * 注册可报告异常
     */
    public function reportable(string $type, Closure $callback): void
    {
        $this->reportCallbacks[$type] = $callback;
    }

    /**
     * 注册可渲染异常
     */
    public function renderable(string $type, Closure $callback): void
    {
        $this->renderCallbacks[$type] = $callback;
    }

    /**
     * 设置不应报告的异常
     */
    public function dontReport(array $exceptions): void
    {
        $this->dontReport = array_merge($this->dontReport, $exceptions);
    }

    /**
     * 设置调试模式
     */
    public function setDebug(bool $debug): void
    {
        $this->debug = $debug;
    }

    /**
     * 获取调试模式
     */
    public function isDebug(): bool
    {
        return $this->debug;
    }
}
