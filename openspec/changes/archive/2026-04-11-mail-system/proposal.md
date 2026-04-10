## Why

框架没有邮件发送能力。Laravel Mail 提供 Markdown Mailable、多驱动支持、队列集成等。邮件是用户注册、密码重置、通知等场景的基础设施。

## What Changes

- 新增 Mail 系统：Mailable + Mailer + Transport
- 支持 3 种驱动：smtp / sendmail / array (测试用)
- Mailable 类支持 HTML/纯文本/Markdown 邮件
- 支持附件、内嵌图片、自定义 header
- 集成 Queue 系统 (`ShouldQueue` trait)
- 新增 `config/mail.php` 配置文件
- 新增 `make:mail` 命令

## Capabilities

### New Capabilities
- `mail-core`: Mailer + Mailable 基类 + Transport 抽象
- `mail-drivers`: smtp/sendmail/array 驱动
- `mail-markdown`: Markdown 邮件渲染
- `mail-config`: mail 配置文件

### Modified Capabilities

## Impact

- `bin/Mail/Mailer.php` — 新增
- `bin/Mail/Mailable.php` — 新增，Mailable 基类
- `bin/Mail/Transport/SmtpTransport.php` — 新增
- `bin/Mail/Transport/SendmailTransport.php` — 新增
- `bin/Mail/Transport/ArrayTransport.php` — 新增
- `bin/Mail/MailManager.php` — 新增
- `config/mail.php` — 新增
- `bin/Console/Commands/MakeMailCommand.php` — 新增
- `tests/MailTest.php` — 新增测试
