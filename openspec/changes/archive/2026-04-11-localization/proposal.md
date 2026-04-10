## Why

框架没有多语言支持。Laravel Localization 提供 `__()` 函数、`@lang` Blade 指令、JSON 翻译文件、复数形式处理等。多语言是面向国际用户的应用的必需功能。

## What Changes

- 新增 Localization 系统：Translator + Language Loader
- 支持 PHP 翻译文件 (`lang/en/messages.php`) 和 JSON 翻译文件 (`lang/en.json`)
- 支持 `__()` / `trans()` / `trans_choice()` 辅助函数
- 支持 Blade `@lang` / `@choice` 指令
- 支持嵌套键 (`messages.welcome`) 和参数替换 (`:name`)
- 支持复数形式 (pluralization)
- 支持回退语言 (`fallback_locale`)
- 新增 `config/app.php` 中 locale/fallback_locale 配置

## Capabilities

### New Capabilities
- `localization-core`: Translator + Language Loader + 翻译文件管理
- `localization-helpers`: __() / trans() / trans_choice() 辅助函数
- `localization-blade`: @lang / @choice Blade 指令

### Modified Capabilities

## Impact

- `bin/Localization/Translator.php` — 新增
- `bin/Localization/LanguageLoader.php` — 新增
- `bin/Localization/MessageSelector.php` — 新增 (复数形式)
- `lang/en/` — 新增目录，默认英语翻译文件
- `lang/zh/` — 新增目录，中文翻译文件
- `config/app.php` — 补充 locale/fallback_locale
- `bin/Func/helpers/` — 新增 localization.php 辅助函数
- `tests/LocalizationTest.php` — 新增测试
