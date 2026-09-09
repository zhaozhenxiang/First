<?php

declare(strict_types=1);

namespace Tests;

use Bin\Testing\TestCase;
use Bin\View\Blade\BladeCompiler;
use Bin\View\ComponentAttributeBag;
use Bin\View\ComponentFactory;

/**
 * Blade 组件子系统测试 — @component/@slot、匿名组件、属性包、缓存失效
 */
class BladeComponentTest extends TestCase
{
    protected string $viewPath;
    protected string $cachePath;
    protected BladeCompiler $compiler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->viewPath = sys_get_temp_dir() . '/blade_component_views_' . uniqid();
        $this->cachePath = sys_get_temp_dir() . '/blade_component_cache_' . uniqid();
        mkdir($this->viewPath, 0755, true);
        mkdir($this->cachePath, 0755, true);

        $this->compiler = new BladeCompiler($this->viewPath, $this->cachePath);
    }

    protected function tearDown(): void
    {
        $this->rmdir($this->viewPath);
        $this->rmdir($this->cachePath);
        ComponentFactory::reset();
        parent::tearDown();
    }

    private function rmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (glob($dir . '/*') ?: [] as $file) {
            if (is_dir($file)) {
                $this->rmdir($file);
            } else {
                unlink($file);
            }
        }
        rmdir($dir);
    }

    private function writeView(string $name, string $content): void
    {
        $dir = dirname($this->viewPath . '/' . $name);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($this->viewPath . '/' . $name, $content);
    }

    private function render(string $name, array $data = []): string
    {
        $compiled = $this->compiler->compile($name . '.blade.php');
        extract($data);
        ob_start();
        require $compiled;

        return (string) ob_get_clean();
    }

    // ================================================================
    // @component / @slot
    // ================================================================

    public function testComponentRendersWithDefaultSlot(): void
    {
        $this->writeView('components/panel.blade.php', '<div class="panel">{{ $slot }}</div>');
        $this->writeView('panel-page.blade.php', <<<'BLADE'
@component('components.panel')
  Panel Body
@endcomponent
BLADE);

        $output = $this->render('panel-page');

        $this->assertStringContainsString('class="panel"', $output);
        $this->assertStringContainsString('Panel Body', $output);
    }

    public function testComponentWithNamedSlots(): void
    {
        $this->writeView('components/card.blade.php', '<h1>{{ $slots["title"] ?? "" }}</h1><body>{{ $slot }}</body>');
        $this->writeView('cards.blade.php', <<<'BLADE'
@component('components.card')
  @slot('title')
    Hello Title
  @endslot
  Body Content
@endcomponent
BLADE);

        $output = $this->render('cards');

        $this->assertStringContainsString('Hello Title', $output);
        $this->assertStringContainsString('Body Content', $output);
    }

    public function testComponentWithDataArray(): void
    {
        $this->writeView('components/greet.blade.php', 'Hello {{ $name }}, level={{ $level }}');
        $this->writeView('greet-page.blade.php', <<<'BLADE'
@component('components.greet', ['name' => 'Ada', 'level' => 5])
@endcomponent
BLADE);

        $output = $this->render('greet-page');

        $this->assertStringContainsString('Hello Ada', $output);
        $this->assertStringContainsString('level=5', $output);
    }

    public function testComponentDataCanReferenceVariables(): void
    {
        $this->writeView('components/greet.blade.php', 'Hello {{ $name }}');
        $this->writeView('greet-var.blade.php', <<<'BLADE'
@component('components.greet', ['name' => $user])
@endcomponent
BLADE);

        $output = $this->render('greet-var', ['user' => 'Grace']);

        $this->assertStringContainsString('Hello Grace', $output);
    }

    public function testNestedComponents(): void
    {
        $this->writeView('components/outer.blade.php', '[{!! $slot !!}]');
        $this->writeView('components/inner.blade.php', '<{!! $slot !!}>');
        $this->writeView('nested.blade.php', <<<'BLADE'
@component('components.outer')
  @component('components.inner')
    core
  @endcomponent
@endcomponent
BLADE);

        $output = $this->render('nested');

        $this->assertEquals('[<core>]', $output);
    }

    // ================================================================
    // 匿名组件 <x-*>
    // ================================================================

    public function testAnonymousComponentWithStaticAttributes(): void
    {
        $this->writeView('components/alert.blade.php', '<div class="alert {{ $type }}">{{ $slot }}</div>');
        $this->writeView('alert-page.blade.php', '<x-alert type="error">Something broke</x-alert>');

        $output = $this->render('alert-page');

        $this->assertStringContainsString('class="alert error"', $output);
        $this->assertStringContainsString('Something broke', $output);
    }

    public function testAnonymousComponentWithBoundAttribute(): void
    {
        $this->writeView('components/message.blade.php', '<p>{{ $message }}</p>');
        $this->writeView('bound-page.blade.php', '<x-message :message="$text"/>');

        $output = $this->render('bound-page', ['text' => 'dynamic value']);

        $this->assertStringContainsString('dynamic value', $output);
    }

    public function testAnonymousComponentSelfClosing(): void
    {
        $this->writeView('components/icon.blade.php', '<span class="icon icon-{{ $name }}"></span>');
        $this->writeView('icon-page.blade.php', '<x-icon name="check"/>');

        $output = $this->render('icon-page');

        $this->assertStringContainsString('icon icon-check', $output);
    }

    public function testAnonymousComponentDottedNameMapsToSubdirectory(): void
    {
        $this->writeView('components/input/text.blade.php', '<input type="{{ $type }}">');
        $this->writeView('input-page.blade.php', '<x-input.text type="email"></x-input.text>');

        $output = $this->render('input-page');

        $this->assertStringContainsString('type="email"', $output);
    }

    public function testAnonymousComponentAttributesBag(): void
    {
        // 属性包含引号，须用 {!! !!} 原样输出（{{ }} 会转义）
        $this->writeView('components/btn.blade.php', <<<'BLADE'
<button {!! $attributes->merge(['class' => 'btn']) !!}>{{ $slot }}</button>
BLADE);
        $this->writeView('btn-page.blade.php', '<x-btn class="btn-primary" id="save">Save</x-btn>');

        $output = $this->render('btn-page');

        // class 合并：组件默认 btn + 标签 btn-primary
        $this->assertStringContainsString('class="btn-primary btn"', $output);
        $this->assertStringContainsString('id="save"', $output);
        $this->assertStringContainsString('>Save<', $output);
    }

    public function testAnonymousComponentBareAttribute(): void
    {
        $this->writeView('components/opt.blade.php', '<opt disabled={{ $attributes->get("disabled") === true ? "yes" : "no" }}>');
        $this->writeView('opt-page.blade.php', '<x-opt disabled></x-opt>');

        $output = $this->render('opt-page');

        $this->assertStringContainsString('disabled=yes', $output);
    }

    // ================================================================
    // 缓存失效
    // ================================================================

    public function testComponentChangeInvalidatesCache(): void
    {
        $this->writeView('components/stamp.blade.php', 'v1');
        $this->writeView('stamp-page.blade.php', '<x-stamp/>');

        $first = $this->render('stamp-page');
        $this->assertStringContainsString('v1', $first);

        // 组件文件更新 → 页面缓存必须失效重编译
        touch($this->viewPath . '/stamp-page.blade.php', time() - 10);
        $this->writeView('components/stamp.blade.php', 'v2');
        touch($this->viewPath . '/components/stamp.blade.php', time() + 2);

        $second = $this->render('stamp-page');
        $this->assertStringContainsString('v2', $second);
    }

    // ================================================================
    // ComponentAttributeBag
    // ================================================================

    public function testAttributeBagMergeDeduplicatesClasses(): void
    {
        $bag = new ComponentAttributeBag(['class' => 'btn btn-primary']);

        $merged = $bag->merge(['class' => 'btn mt-2']);

        $this->assertEquals('btn btn-primary mt-2', $merged->get('class'));
    }

    public function testAttributeBagMergeOverridesNonClassDefaults(): void
    {
        $bag = new ComponentAttributeBag(['id' => 'real']);

        $merged = $bag->merge(['id' => 'default', 'data-x' => '1']);

        $this->assertEquals('real', $merged->get('id'));
        $this->assertEquals('1', $merged->get('data-x'));
    }

    public function testAttributeBagToStringEscapesAndHandlesBooleans(): void
    {
        $bag = new ComponentAttributeBag(['href' => '/a?b=1&c=2', 'disabled' => true, 'skip' => null]);

        $this->assertEquals(
            'href="/a?b=1&amp;c=2" disabled',
            (string) $bag
        );
    }

    public function testAttributeBagOnlyAndExcept(): void
    {
        $bag = new ComponentAttributeBag(['a' => 1, 'b' => 2, 'c' => 3]);

        $this->assertEquals(['a' => 1], $bag->only(['a'])->all());
        $this->assertEquals(['b' => 2, 'c' => 3], $bag->except(['a'])->all());
    }

    public function testAttributeBagArrayAccess(): void
    {
        $bag = new ComponentAttributeBag(['x' => '1']);

        $this->assertTrue(isset($bag['x']));
        $this->assertEquals('1', $bag['x']);
        $bag['y'] = '2';
        $this->assertEquals('2', $bag->get('y'));
        unset($bag['y']);
        $this->assertFalse($bag->has('y'));
    }
}
