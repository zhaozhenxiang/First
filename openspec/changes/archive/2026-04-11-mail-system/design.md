# Design: Mail System

## Architecture

```
MailManager (singleton, config-driven)
  └── mailer(?name) → Mailer
                        └── Transport (interface)
                              ├── SmtpTransport     — SMTP 发送
                              ├── SendmailTransport  — sendmail 命令
                              └── ArrayTransport     — 测试用（记录到数组）

Mailable (用户定义)
  ├── build() — 定义邮件内容
  ├── subject(), from(), to(), cc(), bcc(), replyTo()
  ├── view() / html() / text() / markdown()
  ├── attach() / attachFromStorage() / embed()
  └── implements ShouldQueue → 自动入队
```

## Components

### MailManager
- Singleton, reads config/mail.php
- `mailer(?name)` → Mailer instance (lazy, cached)
- `to(), cc(), bcc()` — global recipients override
- `flush()` — clear resolved mailers

### Mailer
- `to(address)` — set recipients
- `send(Mailable)` — send immediately
- `queue(Mailable)` — send via queue
- `later(delay, Mailable)` — delayed queue
- `failures()` — last failed recipients

### Mailable (Base Class)
- Content: `view()`, `html()`, `text()`
- Envelope: `from()`, `to()`, `cc()`, `bcc()`, `replyTo()`, `subject()`
- Attachments: `attach()`, `attachData()`, `embed()`, `embedData()`
- Build method: user overrides `build()` or `content()` to define email
- `render()` — render to HTML string
- `send(Mailer)` — send via mailer
- Serializable for queue

### Transport Interface
- `send(Mailable): array` — returns sent message IDs or empty on failure
- `getSentMessages(): array` — for ArrayTransport testing

### SmtpTransport
- Uses PHP socket/fsockopen for SMTP
- Supports TLS/STARTTLS
- Auth: LOGIN/PLAIN
- Connection pooling optional

### SendmailTransport
- Invokes sendmail binary via proc_open
- Default path: /usr/sbin/sendmail

### ArrayTransport
- Stores Mailables in array for testing
- `getSentMessages(): array` — retrieve all sent
- `assertSent(closure)` — test helper

### Config (config/mail.php)
- default driver
- drivers: smtp (host/port/encryption/username/password), sendmail (path), array
- from: default address and name
- markdown: paths to templates
