<?php

declare(strict_types=1);

namespace Tests;

use Bin\Mail\Mailable;
use Bin\Mail\MailManager;
use Bin\Mail\Mailer;
use Bin\Mail\Transport\ArrayTransport;
use Bin\Mail\Transport\SmtpTransport;
use Bin\Testing\TestCase;

/**
 * 邮件系统测试
 */
class MailTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        MailManager::resetInstance();
        Mailer::resetInstance();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        MailManager::resetInstance();
        Mailer::resetInstance();
    }

    // ─── Mailable 测试 ───

    public function testMailableBuild(): void
    {
        $mailable = new class extends Mailable {
            public function build(): void
            {
                $this->subject('Test Subject')
                    ->from('sender@example.com', 'Sender')
                    ->to('recipient@example.com')
                    ->cc('cc@example.com')
                    ->bcc('bcc@example.com')
                    ->replyTo('reply@example.com')
                    ->html('<h1>Hello</h1>')
                    ->text('Hello');
            }
        };

        $mailable->build();

        $this->assertEquals('Test Subject', $mailable->getSubject());
        $this->assertEquals(['sender@example.com', 'Sender'], $mailable->getFrom());
        $this->assertEquals('sender@example.com', $mailable->getFromAddress());
        $this->assertEquals(['recipient@example.com'], $mailable->getTo());
        $this->assertEquals(['cc@example.com'], $mailable->getCc());
        $this->assertEquals(['bcc@example.com'], $mailable->getBcc());
        $this->assertEquals(['reply@example.com'], $mailable->getReplyTo());
        $this->assertEquals('<h1>Hello</h1>', $mailable->getHtmlContent());
        $this->assertEquals('Hello', $mailable->getTextContent());
    }

    public function testMailableMultipleRecipients(): void
    {
        $mailable = new class extends Mailable {
            public function build(): void
            {
                $this->to(['a@example.com', 'b@example.com']);
            }
        };

        $mailable->build();

        $this->assertEquals(['a@example.com', 'b@example.com'], $mailable->getTo());
    }

    public function testMailableRender(): void
    {
        $mailable = new class extends Mailable {
            public function build(): void
            {
                $this->html('<p>Rendered</p>');
            }
        };

        $this->assertEquals('<p>Rendered</p>', $mailable->render());
    }

    public function testMailableAttach(): void
    {
        $mailable = new class extends Mailable {
            public function build(): void
            {
                $this->attach('/tmp/file.pdf', ['as' => 'document.pdf']);
            }
        };

        $mailable->build();

        $attachments = $mailable->getAttachments();
        $this->assertCount(1, $attachments);
        $this->assertEquals('/tmp/file.pdf', $attachments[0]['path']);
    }

    public function testMailableSerialization(): void
    {
        $mailable = new class extends Mailable {
            public function build(): void
            {
                $this->subject('Serialize Test')
                    ->to('test@example.com')
                    ->html('<p>Test</p>');
            }
        };

        $mailable->build();

        // 直接测试 __serialize / __unserialize
        $data = $mailable->__serialize();
        $this->assertEquals('Serialize Test', $data['subject']);
        $this->assertEquals(['test@example.com'], $data['to']);

        $restored = new class extends Mailable {
            public function build(): void {}
        };
        $restored->__unserialize($data);

        $this->assertEquals('Serialize Test', $restored->getSubject());
        $this->assertEquals(['test@example.com'], $restored->getTo());
    }

    // ─── ArrayTransport 测试 ───

    public function testArrayTransportSend(): void
    {
        $transport = new ArrayTransport();
        $mailable = new class extends Mailable {
            public function build(): void
            {
                $this->to('test@example.com')->html('test');
            }
        };
        $mailable->build();

        $result = $transport->send($mailable);

        $this->assertEquals([], $result); // array transport returns empty
        $this->assertEquals(1, $transport->count());
    }

    public function testArrayTransportAssertSent(): void
    {
        $transport = new ArrayTransport();
        $mailable = new class extends Mailable {
            public function build(): void
            {
                $this->to('test@example.com')->html('test');
            }
        };
        $mailable->build();

        $transport->send($mailable);

        $sent = $transport->getSentMessages();
        $this->assertCount(1, $sent);
    }

    public function testArrayTransportReset(): void
    {
        $transport = new ArrayTransport();
        $mailable = new class extends Mailable {
            public function build(): void
            {
                $this->to('test@example.com')->html('test');
            }
        };
        $mailable->build();

        $transport->send($mailable);
        $this->assertEquals(1, $transport->count());

        $transport->reset();
        $this->assertEquals(0, $transport->count());
    }

    public function testArrayTransportAssertSentByClass(): void
    {
        $transport = new ArrayTransport();
        $mailable = new class extends Mailable {
            public function build(): void
            {
                $this->to('test@example.com')->html('test');
            }
        };
        $mailable->build();

        $transport->send($mailable);

        // 匿名类无法通过类名断言，用通用方式
        $messages = $transport->getSentMessages();
        $this->assertCount(1, $messages);
        $this->assertInstanceOf(Mailable::class, $messages[0]);
    }

    // ─── Mailer 测试 ───

    public function testMailerSend(): void
    {
        $transport = new ArrayTransport();
        $mailer = new Mailer($transport);

        $mailable = new class extends Mailable {
            public function build(): void
            {
                $this->to('test@example.com')->subject('Hi')->html('<p>Hello</p>');
            }
        };

        $result = $mailer->send($mailable);

        $this->assertEquals(1, $transport->count());
    }

    public function testMailerToOverride(): void
    {
        $transport = new ArrayTransport();
        $mailer = new Mailer($transport);

        $mailable = new class extends Mailable {
            public function build(): void
            {
                $this->to('original@example.com')->html('test');
            }
        };

        $mailer->to('override@example.com');
        $mailer->send($mailable);

        // Mailable 应包含覆盖和原始收件人
        $to = $mailable->getTo();
        $this->assertContains('override@example.com', $to);
        $this->assertContains('original@example.com', $to);
    }

    public function testMailerSingleton(): void
    {
        $a = Mailer::getInstance();
        $b = Mailer::getInstance();

        $this->assertSame($a, $b);
    }

    public function testMailerResetInstance(): void
    {
        $a = Mailer::getInstance();
        Mailer::resetInstance();
        $b = Mailer::getInstance();

        $this->assertNotSame($a, $b);
    }

    // ─── MailManager 测试 ───

    public function testMailManagerSingleton(): void
    {
        $a = MailManager::getInstance();
        $b = MailManager::getInstance();

        $this->assertSame($a, $b);
    }

    public function testMailManagerResetInstance(): void
    {
        $a = MailManager::getInstance();
        MailManager::resetInstance();
        $b = MailManager::getInstance();

        $this->assertNotSame($a, $b);
    }

    public function testMailManagerArrayDriver(): void
    {
        $manager = new MailManager();
        $manager->setConfig([
            'array' => ['driver' => 'array'],
        ]);

        $mailer = $manager->mailer('array');
        $this->assertInstanceOf(Mailer::class, $mailer);
        $this->assertInstanceOf(ArrayTransport::class, $mailer->getTransport());
    }

    public function testMailManagerCached(): void
    {
        $manager = new MailManager();
        $manager->setConfig([
            'array' => ['driver' => 'array'],
        ]);

        $a = $manager->mailer('array');
        $b = $manager->mailer('array');

        $this->assertSame($a, $b);
    }

    public function testMailManagerFlush(): void
    {
        $manager = new MailManager();
        $manager->setConfig([
            'array' => ['driver' => 'array'],
        ]);

        $a = $manager->mailer('array');
        $manager->flush();
        $b = $manager->mailer('array');

        $this->assertNotSame($a, $b);
    }

    public function testMailManagerUnconfiguredThrows(): void
    {
        $manager = new MailManager();
        $manager->setConfig([]);

        $thrown = false;
        try {
            $manager->mailer('missing');
        } catch (\RuntimeException $e) {
            $thrown = true;
            $this->assertStringContainsString('not configured', $e->getMessage());
        }
        $this->assertTrue($thrown, 'Expected RuntimeException');
    }

    public function testMailManagerUnsupportedDriverThrows(): void
    {
        $manager = new MailManager();
        $manager->setConfig([
            'ses' => ['driver' => 'ses'],
        ]);

        $thrown = false;
        try {
            $manager->mailer('ses');
        } catch (\RuntimeException $e) {
            $thrown = true;
            $this->assertStringContainsString('Unsupported', $e->getMessage());
        }
        $this->assertTrue($thrown, 'Expected RuntimeException');
    }

    public function testMailManagerProxySend(): void
    {
        $manager = new MailManager();
        $manager->setConfig([
            'array' => ['driver' => 'array'],
        ]);

        $mailable = new class extends Mailable {
            public function build(): void
            {
                $this->to('test@example.com')->subject('Proxy')->html('test');
            }
        };

        $manager->send($mailable);

        $transport = $manager->getArrayTransport();
        $this->assertNotNull($transport);
        $this->assertEquals(1, $transport->count());
    }

    public function testMailManagerGetArrayTransport(): void
    {
        $manager = new MailManager();
        $manager->setConfig([
            'array' => ['driver' => 'array'],
        ]);

        $transport = $manager->getArrayTransport();

        $this->assertInstanceOf(ArrayTransport::class, $transport);
    }

    // ─── SmtpTransport 构造测试（不连接） ───

    public function testSmtpTransportCreation(): void
    {
        $transport = new SmtpTransport([
            'host' => 'smtp.example.com',
            'port' => 465,
            'encryption' => 'ssl',
            'username' => 'user',
            'password' => 'pass',
        ]);

        $this->assertInstanceOf(SmtpTransport::class, $transport);
    }
}
