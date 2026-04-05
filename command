<?php

declare(strict_types=1);

/**
 * CLI 命令行入口
 */

require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/../bin/autoload.php';

use Bin\Console\Kernel;

// 注册内置命令
Kernel::register('list', \Bin\Console\Commands\ListCommand::class);
Kernel::register('help', \Bin\Console\Commands\HelpCommand::class);
Kernel::register('cache:clear', \Bin\Console\Commands\ClearCacheCommand::class);
Kernel::register('config:cache', \Bin\Console\Commands\ConfigCacheCommand::class);
Kernel::register('config:clear', \Bin\Console\Commands\ConfigClearCommand::class);
Kernel::register('serve', \Bin\Console\Commands\ServeCommand::class);
Kernel::register('migrate', \Bin\Console\Commands\MigrateCommand::class);
Kernel::register('test', \Bin\Console\Commands\TestCommand::class);
Kernel::register('db:seed', \Bin\Console\Commands\SeedCommand::class);
Kernel::register('seed', \Bin\Console\Commands\SeedCommand::class);
Kernel::register('make:seeder', \Bin\Console\Commands\MakeSeederCommand::class);

// 处理命令
$exitCode = Kernel::handle();

exit($exitCode);
