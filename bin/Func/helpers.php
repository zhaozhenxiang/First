<?php

declare(strict_types=1);

/**
 * 辅助函数加载器
 *
 * 按领域拆分为独立文件，通过 glob 自动加载
 */

foreach (glob(__DIR__ . '/helpers/*.php') as $file) {
    require_once $file;
}
