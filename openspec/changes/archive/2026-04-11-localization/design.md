# Design: Localization

## Architecture

```
Translator (singleton, config-driven)
  └── addLines(locale, lines) / addJsonTranslations(locale, lines)
  ├── get(key, replace, locale) — 点号路径查找
  ├── choice(key, number, replace, locale) — 复数形式
  └── locale / fallbackLocale — 当前/回退语言

LanguageLoader
  └── load(locale) → array — 加载 lang/{locale}/*.php + lang/{locale}.json

MessageSelector
  └── choose(line, number) — 解析 "{0}no items|{1}one item|[2,*]many"

Helper Functions
  ├── __($key, $replace, $locale)
  ├── trans($key, $replace, $locale)
  └── trans_choice($key, $number, $replace, $locale)
```

## Components

### Translator
- Singleton, reads config('app.locale') and config('app.fallback_locale')
- `$locale` — current locale (settable)
- `$fallbackLocale` — fallback when key missing in current locale
- Nested key lookup: `messages.welcome` → `$lines['messages']['welcome']`
- Parameter replacement: `:name` → `$replace['name']`
- `addLines(array, locale)` — programmatic translations
- `addJsonTranslations(locale, array)` — JSON flat translations
- `get(key, replace, locale)` — lookup with fallback
- `choice(key, number, replace, locale)` — pluralization

### LanguageLoader
- Scans `lang/{locale}/*.php` for group translations
- Loads `lang/{locale}.json` for flat JSON translations
- Caches loaded groups
- Returns merged translations

### MessageSelector
- Parses plural forms: `{0}...|{1}...|[2,*]...|...`
- Selects form based on number
- Supports range: `{1,10}` and wildcard: `{*}`

### Translation Files
- `lang/en/messages.php` → returns `['welcome' => 'Welcome', ...]`
- `lang/en.json` → `{"Welcome to our app": "Welcome to our app"}`
- Nested: `lang/en/validation.php` → `['required' => 'The :attribute field is required.']`

### Helper Functions (bin/Func/helpers/localization.php)
- `__($key, $replace = [], $locale = null)` → string
- `trans($key, $replace = [], $locale = null)` → string (alias)
- `trans_choice($key, $number, $replace = [], $locale = null)` → string
