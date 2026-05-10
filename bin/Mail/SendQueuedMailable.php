<?php

declare(strict_types=1);

namespace Bin\Mail;

use Bin\Queue\Job;

class SendQueuedMailable extends Job
{
    public function __construct(protected Mailable $mailable)
    {
    }

    public function handle(): void
    {
        Mailer::getInstance()->send($this->mailable);
    }
}
