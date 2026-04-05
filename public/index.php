<?php

declare(strict_types=1);

define('START_TIME', time());

require __DIR__ . '/../bin/autoload.php';

$response = Bin\Route\RouteAction::action();

echo (string)$response;
