<?php

declare(strict_types=1);

namespace Bin\Queue;

/**
 * 任务超时异常
 *
 * Worker 通过 SIGALRM 异步信号在任务超时时抛出，
 * 沿常规失败路径处理（计入尝试次数、按 backoff 释放）。
 */
class QueueTimeoutException extends \RuntimeException
{
}
