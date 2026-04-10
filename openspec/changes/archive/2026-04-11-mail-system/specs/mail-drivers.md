# Spec: Mail Drivers

## SmtpTransport

### Configuration
- host, port, encryption (tls/ssl/none)
- username, password
- timeout, auth_mode (login/plain)

### SMTP Protocol Flow
1. Connect via fsockopen
2. Read greeting (220)
3. EHLO
4. STARTTLS if encryption=tls
5. AUTH LOGIN/PLAIN
6. MAIL FROM
7. RCPT TO (for each recipient)
8. DATA + message content
9. QUIT

### Response Handling
- Parse SMTP response codes
- 250 = OK, 354 = Start mail input, 220 = Ready
- Throw on 4xx/5xx

## SendmailTransport

### Configuration
- path: /usr/sbin/sendmail -bs (default)

### Execution
- proc_open with sendmail binary
- Pipe raw email to stdin
- Read stdout/stderr

## ArrayTransport

### Behavior
- Stores each sent Mailable in internal array
- `getSentMessages(): array` — return all stored Mailables
- `assertSent(string $mailableClass, ?callable $callback = null): bool`
- `assertNotSent(string $mailableClass): bool`
- `reset(): void` — clear stored messages
- Returns empty array from send() (no real delivery)

## Config (config/mail.php)

```php
'default' => env('MAIL_MAILER', 'smtp'),
'mailers' => [
    'smtp' => [
        'driver' => 'smtp',
        'host' => env('MAIL_HOST', 'localhost'),
        'port' => env('MAIL_PORT', 587),
        'encryption' => env('MAIL_ENCRYPTION', 'tls'),
        'username' => env('MAIL_USERNAME'),
        'password' => env('MAIL_PASSWORD'),
    ],
    'sendmail' => [
        'driver' => 'sendmail',
        'path' => '/usr/sbin/sendmail -bs',
    ],
    'array' => [
        'driver' => 'array',
    ],
],
'from' => [
    'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
    'name' => env('MAIL_FROM_NAME', 'Example'),
],
```
