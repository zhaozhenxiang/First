<?php

declare(strict_types=1);

namespace Bin\Mail;

use Bin\Queue\Job;

class SendQueuedMailable extends Job
{
    public function __construct(
        protected Mailable $mailable,
        protected ?string $mailer = null
    )
    {
    }

    public function handle(): void
    {
        if ($this->mailer !== null) {
            MailManager::getInstance()->mailer($this->mailer)->send($this->mailable);
            return;
        }

        Mailer::getInstance()->send($this->mailable);
    }
}
