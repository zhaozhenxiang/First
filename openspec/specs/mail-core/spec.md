# Spec: Mail Core

## MailManager

### Singleton
- `getInstance()` → static
- `resetInstance()` → clear (testing)

### Configuration
- Reads `config('mail.mailers')` and `config('mail.default')` at construction
- `setConfig(array)` and `setDefaultMailer(string)` for programmatic config

### Mailer Resolution
- `mailer(?name)` → Mailer (lazy, cached)
- `flush()` → clear resolved mailers
- Unconfigured → RuntimeException
- Unsupported driver → RuntimeException

### Proxy Methods
- `to($address)` → set recipients on default mailer
- `send(Mailable)` → send via default mailer
- `queue(Mailable)` → queue via default mailer
- `failures()` → get last failures

## Mailer

### Methods
- `to(string|array $address): static` — set recipients
- `cc(string|array $address): static` — set CC
- `bcc(string|array $address): static` — set BCC
- `send(Mailable $mailable): array` — send immediately
- `queue(Mailable $mailable): mixed` — send via queue (if ShouldQueue)
- `later(int $delay, Mailable $mailable): mixed` — delayed send
- `getTransport(): Transport` — get underlying transport
- `failures(): array` — failed recipients from last send

## Mailable (Base Class)

### Properties
- `$subject: string`
- `$from: array` — [address, name]
- `$to: array`
- `$cc: array`
- `$bcc: array`
- `$replyTo: array`
- `$attachments: array`
- `$htmlContent: string`
- `$textContent: string`
- `$view: string` — template name
- `$viewData: array`

### Methods (chainable in build())
- `subject(string): static`
- `from(string $address, ?string $name = null): static`
- `to(string|array $address, ?string $name = null): static`
- `cc(string|array $address, ?string $name = null): static`
- `bcc(string|array $address, ?string $name = null): static`
- `replyTo(string|array $address, ?string $name = null): static`
- `view(string $name, array $data = []): static`
- `html(string $content): static`
- `text(string $content): static`
- `attach(string $path, array $options = []): static`
- `attachData(string $data, string $name, array $options = []): static`

### Lifecycle
- `build()` — user overrides to configure email
- `render(): string` — render to HTML
- `send(?Mailer = null): void` — dispatch to mailer

## Transport Interface

### Methods
- `send(Mailable $mailable): array` — send, return message IDs
- `getSentMessages(): array` — retrieve (ArrayTransport)

## Mail Facade
- `Bin\Facade\Mail` → static proxy to MailManager::getInstance()
