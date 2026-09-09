<?php

declare(strict_types=1);

namespace Tests;

use Bin\Localization\LanguageLoader;
use Bin\Localization\Translator;
use Bin\Testing\TestCase;
use Bin\Validation\ValidationManager;

/**
 * 验证消息本地化测试 — Translator 接线
 */
class ValidationLocalizationTest extends TestCase
{
    protected function tearDown(): void
    {
        ValidationManager::setTranslator(null);
        parent::tearDown();
    }

    private function makeTranslator(string $locale): Translator
    {
        $translator = new Translator(new LanguageLoader(dirname(__DIR__) . '/lang'));
        $translator->setLocale($locale);

        return $translator;
    }

    public function testWithoutTranslatorMessagesUseBuiltInDefaults(): void
    {
        ValidationManager::setTranslator(null);

        $validator = ValidationManager::make(['name' => ''], ['name' => 'required']);
        $validator->validate();

        $this->assertEquals(['name 字段是必填的'], $validator->getErrors()->get('name'));
    }

    public function testTranslatorWithEnglishLocaleYieldsEnglishMessages(): void
    {
        ValidationManager::setTranslator($this->makeTranslator('en'));

        $validator = ValidationManager::make(['name' => ''], ['name' => 'required']);
        $validator->validate();

        $this->assertEquals(['The name field is required.'], $validator->getErrors()->get('name'));
    }

    public function testTranslatorWithChineseLocaleYieldsChineseMessages(): void
    {
        ValidationManager::setTranslator($this->makeTranslator('zh'));

        $validator = ValidationManager::make(['name' => ''], ['name' => 'required']);
        $validator->validate();

        $this->assertEquals(['name 字段是必填的'], $validator->getErrors()->get('name'));
    }

    public function testTranslatedMessagesReplacePlaceholders(): void
    {
        ValidationManager::setTranslator($this->makeTranslator('en'));

        $validator = ValidationManager::make(['name' => 'a'], ['name' => 'min:3']);
        $validator->validate();

        $this->assertEquals(['The name must be at least 3.'], $validator->getErrors()->get('name'));
    }

    public function testCustomMessagesTakePrecedenceOverTranslator(): void
    {
        ValidationManager::setTranslator($this->makeTranslator('en'));

        $validator = ValidationManager::make(['name' => ''], ['name' => 'required']);
        $validator->setCustomMessages(['required' => '自定义必填消息']);
        $validator->validate();

        $this->assertEquals(['自定义必填消息'], $validator->getErrors()->get('name'));
    }

    public function testMissingTranslationKeyFallsBackToBuiltInDefault(): void
    {
        // 空目录加载器 → validation.* 全部缺失 → 回退内置中文
        $emptyPath = sys_get_temp_dir() . '/first-validation-l10n-' . bin2hex(random_bytes(4));
        mkdir($emptyPath . '/en', 0777, true);

        try {
            $translator = new Translator(new LanguageLoader($emptyPath));
            $translator->setLocale('en');
            ValidationManager::setTranslator($translator);

            $validator = ValidationManager::make(['name' => ''], ['name' => 'required']);
            $validator->validate();

            $this->assertEquals(['name 字段是必填的'], $validator->getErrors()->get('name'));
        } finally {
            rmdir($emptyPath . '/en');
            rmdir($emptyPath);
        }
    }

    public function testNewTrackDRulesAreLocalized(): void
    {
        ValidationManager::setTranslator($this->makeTranslator('en'));

        $validator = ValidationManager::make(['terms' => 'no'], ['terms' => 'accepted']);
        $validator->validate();
        $this->assertEquals(['The terms must be accepted.'], $validator->getErrors()->get('terms'));

        $validator = ValidationManager::make(['email' => 'x'], ['email' => 'email']);
        $validator->validate();
        $this->assertEquals(['The email must be a valid email address.'], $validator->getErrors()->get('email'));
    }
}
