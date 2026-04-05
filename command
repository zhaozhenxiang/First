#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * CLI 命令行入口
 */

require __DIR__ . '/bin/autoload.php';

exit(Bin\Console\Kernel::handle());
