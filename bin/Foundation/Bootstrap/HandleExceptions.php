<?php

declare(strict_types=1);

namespace Bin\Foundation\Bootstrap;

use Bin\App\App;
use Bin\Foundation\Contracts\Bootstrapper as BootstrapperContract;

/**
 * 设置异常处理
 *
 * 注册全局异常和错误处理器
 */
class HandleExceptions implements BootstrapperContract
{
    public function bootstrap(App $app): void
    {
        error_reporting(E_ALL);

        set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }

            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler(function (\Throwable $e) use ($app): void {
            if ($app->bound('log')) {
                try {
                    $app->make('log')->error($e->getMessage(), [
                        'exception' => get_class($e),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ]);
                } catch (\Throwable) {
                    // 日志记录失败时不影响异常处理
                }
            }

            // 委托给 ExceptionHandler
            if ($app->bound(\Bin\Exception\ExceptionHandler::class)) {
                $app->make(\Bin\Exception\ExceptionHandler::class)->report($e);
                $app->make(\Bin\Exception\ExceptionHandler::class)->render($e);
                return;
            }

            // 降级：直接输出
            if (PHP_SAPI === 'cli') {
                fwrite(STDERR, "Error: {$e->getMessage()} in {$e->getFile()}:{$e->getLine()}\n");
            } else {
                http_response_code(500);
                echo "Internal Server Error";
            }
        });
    }
}
