# Spec: Localization Core

## Translator

### Singleton
- `getInstance()` → static
- `resetInstance()` → clear (testing)

### Configuration
- Reads `config('app.locale')` and `config('app.fallback_locale')` at construction
- `setLocale(string)` / `getLocale(): string`
- `setFallback(string)` / `getFallback(): string`

### Translation Lookup
- `get(string $key, array $replace = [], ?string $locale = null): string`
  - Dot-notation: `messages.welcome` → nested array lookup
  - Falls back to fallback_locale if key missing
  - Returns key itself if no translation found
  - Parameter replacement: `:name` in string → value from `$replace`

### Pluralization
- `choice(string $key, int $number, array $replace = [], ?string $locale = null): string`
  - Gets translation line
  - Passes through MessageSelector
  - Applies parameter replacement

### Programmatic Translations
- `addLines(array $lines, string $locale = 'en'): void` — merge group lines
- `addJsonTranslations(string $locale, array $lines): void` — merge JSON translations

### Internal
- `loadLines(string $locale): array` — load all translations for locale
- `$loaded: array` — cache per locale

## MessageSelector

### Plural Form Syntax
- `"{0}no items|{1}one item|[2,*]many items"` — pipe-separated forms
- `{count}` — exact match
- `{min,max}` — range
- `{count,*}` — count and above
- Default (no braces) — fallback

### Methods
- `choose(string $line, int $number, string $locale = 'en'): string`

## LanguageLoader

### Methods
- `load(string $locale): array` — returns merged translations
- `loadGroup(string $locale, string $group): array` — load single PHP file
- `loadJson(string $locale): array` — load JSON file
- `addNamespace(string $namespace, string $path): void` — custom paths

### File Structure
```
lang/
  en/
    messages.php
    validation.php
  zh/
    messages.php
  en.json
  zh.json
```
