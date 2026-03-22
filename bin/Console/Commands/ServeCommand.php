<?php

declare(strict_types=1);

namespace Bin\Console\Commands;

use Bin\Console\Command;

/**
 * Serve 命令 - 启动开发服务器
 */
class ServeCommand extends Command
{
    protected string $signature = 'serve {--host=127.0.0.1} {--port=8000}';

    protected string $description = 'Start the development server';

    public function execute(): int
    {
        $host = $this->option('host', '127.0.0.1');
        $port = $this->option('port', '8000');

        $this->info("Starting development server on http://{$host}:{$port}");
        $this->comment('Press Ctrl+C to stop the server.');

        $publicPath = basePath('public');

        if (!is_dir($publicPath)) {
            $this->error("Public directory not found: {$publicPath}");
            return 1;
        }

        $command = sprintf(
            'php -S %s:%d -t %s',
            $host,
            $port,
            $publicPath
        );

        passthru($command);

        return 0;
    }
}
