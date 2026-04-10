# Spec: Localization Helpers

## Helper Functions

### __()
```php
function __(string $key, array $replace = [], ?string $locale = null): string
```
- Primary translation helper
- Delegates to Translator::getInstance()->get()
- Handles parameter replacement

### trans()
```php
function trans(string $key, array $replace = [], ?string $locale = null): string
```
- Alias for __()
- Laravel-compatible naming

### trans_choice()
```php
function trans_choice(string $key, int $number, array $replace = [], ?string $locale = null): string
```
- Pluralization helper
- Delegates to Translator::getInstance()->choice()

## Default Translation Files

### lang/en/messages.php
```php
return [
    'welcome' => 'Welcome',
    'goodbye' => 'Goodbye, :name',
];
```

### lang/zh/messages.php
```php
return [
    'welcome' => '欢迎',
    'goodbye' => '再见, :name',
];
```

### lang/en/validation.php
```php
return [
    'required' => 'The :attribute field is required.',
    'min' => 'The :attribute must be at least :min characters.',
];
```

### lang/en.json (optional flat translations)
```json
{
    "Welcome to our application": "Welcome to our application"
}
```
