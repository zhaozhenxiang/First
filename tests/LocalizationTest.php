<?php

declare(strict_types=1);

namespace Tests;

use Bin\Localization\LanguageLoader;
use Bin\Localization\MessageSelector;
use Bin\Localization\Translator;
use Bin\Testing\TestCase;

/**
 * 多语言系统测试
 */
class LocalizationTest extends TestCase
{
    private string $tempLangPath;

    protected function setUp(): void
    {
        parent::setUp();
        Translator::resetInstance();

        $this->tempLangPath = sys_get_temp_dir() . '/lang_test_' . uniqid();
        mkdir($this->tempLangPath . '/en', 0777, true);
        mkdir($this->tempLangPath . '/zh', 0777, true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Translator::resetInstance();
        $this->removeDirectory($this->tempLangPath);
    }

    private function writeLangFile(string $locale, string $group, string $content): void
    {
        file_put_contents($this->tempLangPath . "/{$locale}/{$group}.php", $content);
    }

    private function writeJsonFile(string $locale, string $content): void
    {
        file_put_contents($this->tempLangPath . "/{$locale}.json", $content);
    }

    // ─── MessageSelector 测试 ───

    public function testChooseExactMatch(): void
    {
        $selector = new MessageSelector();

        $result = $selector->choose('{0}no items|{1}one item|[2,*]:count items', 0);
        $this->assertEquals('no items', $result);

        $result = $selector->choose('{0}no items|{1}one item|[2,*]:count items', 1);
        $this->assertEquals('one item', $result);

        $result = $selector->choose('{0}no items|{1}one item|[2,*]:count items', 5);
        $this->assertEquals('5 items', $result);
    }

    public function testChooseRange(): void
    {
        $selector = new MessageSelector();

        $result = $selector->choose('{0,1}few|{2,10}some|[11,*]many', 1);
        $this->assertEquals('few', $result);

        $result = $selector->choose('{0,1}few|{2,10}some|[11,*]many', 7);
        $this->assertEquals('some', $result);

        $result = $selector->choose('{0,1}few|{2,10}some|[11,*]many', 20);
        $this->assertEquals('many', $result);
    }

    public function testChooseSingleForm(): void
    {
        $selector = new MessageSelector();

        $result = $selector->choose('just one form', 5);
        $this->assertEquals('just one form', $result);
    }

    public function testChooseCountReplacement(): void
    {
        $selector = new MessageSelector();

        $result = $selector->choose('{0}nothing|[1,*]:count things', 3);
        $this->assertEquals('3 things', $result);
    }

    // ─── Translator 基础测试 ───

    public function testTranslatorGetFromProgrammaticLines(): void
    {
        $translator = new Translator();
        $translator->addLines([
            'messages' => ['welcome' => 'Hello World'],
        ], 'en');

        $this->assertEquals('Hello World', $translator->get('messages.welcome'));
    }

    public function testTranslatorGetReturnsKeyWhenMissing(): void
    {
        $translator = new Translator();
        $translator->setLoader(new LanguageLoader($this->tempLangPath));

        $this->assertEquals('missing.key', $translator->get('missing.key'));
    }

    public function testTranslatorParameterReplacement(): void
    {
        $translator = new Translator();
        $translator->addLines([
            'messages' => ['greeting' => 'Hello, :name!'],
        ], 'en');

        $this->assertEquals('Hello, John!', $translator->get('messages.greeting', ['name' => 'John']));
    }

    public function testTranslatorMultipleParameters(): void
    {
        $translator = new Translator();
        $translator->addLines([
            'validation' => ['min' => 'The :attribute must be at least :min.'],
        ], 'en');

        $result = $translator->get('validation.min', ['attribute' => 'name', 'min' => 3]);
        $this->assertEquals('The name must be at least 3.', $result);
    }

    public function testTranslatorLocaleSwitch(): void
    {
        $translator = new Translator();
        $translator->addLines([
            'messages' => ['welcome' => 'Welcome'],
        ], 'en');
        $translator->addLines([
            'messages' => ['welcome' => '欢迎'],
        ], 'zh');

        $this->assertEquals('en', $translator->getLocale());

        $translator->setLocale('zh');
        $this->assertEquals('zh', $translator->getLocale());
        $this->assertEquals('欢迎', $translator->get('messages.welcome'));
    }

    public function testTranslatorFallback(): void
    {
        $translator = new Translator();
        $translator->addLines([
            'messages' => ['fallback_only' => 'Only in English'],
        ], 'en');
        $translator->addLines([
            'messages' => [],
        ], 'zh');

        $translator->setLocale('zh');
        $translator->setFallback('en');

        $this->assertEquals('Only in English', $translator->get('messages.fallback_only'));
    }

    public function testTranslatorChoice(): void
    {
        $translator = new Translator();
        $translator->addLines([
            'messages' => ['items' => '{0}no items|{1}one item|[2,*]:count items'],
        ], 'en');

        $this->assertEquals('no items', $translator->choice('messages.items', 0));
        $this->assertEquals('one item', $translator->choice('messages.items', 1));
        $this->assertEquals('5 items', $translator->choice('messages.items', 5));
    }

    public function testTranslatorJsonTranslations(): void
    {
        $translator = new Translator();
        $translator->addJsonTranslations('en', [
            'Welcome to our app' => 'Welcome to our app',
        ]);

        $this->assertEquals('Welcome to our app', $translator->get('Welcome to our app'));
    }

    public function testTranslatorSetLocaleAndGetLocale(): void
    {
        $translator = new Translator();

        $translator->setLocale('fr');
        $this->assertEquals('fr', $translator->getLocale());

        $translator->setFallback('de');
        $this->assertEquals('de', $translator->getFallback());
    }

    // ─── LanguageLoader 测试 ───

    public function testLanguageLoaderLoadsPhpFiles(): void
    {
        $this->writeLangFile('en', 'messages', "<?php return ['hello' => 'Hello'];");

        $loader = new LanguageLoader($this->tempLangPath);
        $lines = $loader->load('en');

        $this->assertEquals('Hello', $lines['messages']['hello']);
    }

    public function testLanguageLoaderLoadsJsonFiles(): void
    {
        $this->writeJsonFile('en', '{"Welcome":"Welcome to our app"}');

        $loader = new LanguageLoader($this->tempLangPath);
        $lines = $loader->load('en');

        $this->assertEquals('Welcome to our app', $lines['__json']['Welcome']);
    }

    public function testLanguageLoaderCachesLoaded(): void
    {
        $this->writeLangFile('en', 'messages', "<?php return ['test' => 'value'];");

        $loader = new LanguageLoader($this->tempLangPath);
        $first = $loader->load('en');
        $second = $loader->load('en');

        $this->assertSame($first, $second);
    }

    public function testLanguageLoaderFlush(): void
    {
        $this->writeLangFile('en', 'messages', "<?php return ['test' => 'value'];");

        $loader = new LanguageLoader($this->tempLangPath);
        $first = $loader->load('en');
        $this->assertEquals('value', $first['messages']['test']);

        $loader->flush();

        // flush 后应重新加载
        $second = $loader->load('en');
        $this->assertEquals('value', $second['messages']['test']);
    }

    public function testLanguageLoaderMissingLocaleReturnsEmpty(): void
    {
        $loader = new LanguageLoader($this->tempLangPath);
        $lines = $loader->load('xx');

        $this->assertEquals([], $lines);
    }

    // ─── Translator 单例测试 ───

    public function testTranslatorSingleton(): void
    {
        $a = Translator::getInstance();
        $b = Translator::getInstance();

        $this->assertSame($a, $b);
    }

    public function testTranslatorResetInstance(): void
    {
        $a = Translator::getInstance();
        Translator::resetInstance();
        $b = Translator::getInstance();

        $this->assertNotSame($a, $b);
    }

    // ─── 端到端测试 ───

    public function testEndToEndWithFileLoader(): void
    {
        $this->writeLangFile('en', 'messages', "<?php return ['welcome' => 'Welcome', 'bye' => 'Goodbye, :name'];");
        $this->writeLangFile('zh', 'messages', "<?php return ['welcome' => '欢迎', 'bye' => '再见, :name'];");

        $loader = new LanguageLoader($this->tempLangPath);
        $translator = new Translator($loader);

        // 英文
        $this->assertEquals('Welcome', $translator->get('messages.welcome'));
        $this->assertEquals('Goodbye, Alice', $translator->get('messages.bye', ['name' => 'Alice']));

        // 切换中文
        $translator->setLocale('zh');
        $this->assertEquals('欢迎', $translator->get('messages.welcome'));
        $this->assertEquals('再见, Bob', $translator->get('messages.bye', ['name' => 'Bob']));
    }

    public function testEndToEndJsonTranslation(): void
    {
        $this->writeJsonFile('en', '{"Welcome":"Hello!"}');

        $loader = new LanguageLoader($this->tempLangPath);
        $translator = new Translator($loader);

        $this->assertEquals('Hello!', $translator->get('Welcome'));
    }

    // ─── 辅助方法 ───

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
