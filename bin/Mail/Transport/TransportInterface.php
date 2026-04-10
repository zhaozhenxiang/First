<?php

declare(strict_types=1);

namespace Bin\Mail\Transport;

use Bin\Mail\Mailable;

/**
 * 邮件传输接口
 */
interface TransportInterface
{
    /**
     * 发送邮件
     *
     * @return array<string> 已发送的 message ID 列表
     */
    public function send(Mailable $mailable): array;

    /**
     * 获取已发送消息（测试用）
     */
    public function getSentMessages(): array;
}
