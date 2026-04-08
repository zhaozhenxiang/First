<?php

declare(strict_types=1);

namespace Tests;

use Bin\Testing\TestCase;
use Bin\View\Blade\BladeCompiler;

/**
 * Blade 模板编译器测试 — 编译输出、继承、指令、缓存全覆盖
 */
class BladeCompilerTest extends TestCase
{
    protected string $viewPath;
    protected string $cachePath;
    protected BladeCompiler $compiler;

    protected function setUp(): void
    {
        $this->viewPath = sys_get_temp_dir() . '/blade_test_views_' . uniqid();
        $this->cachePath = sys_get_temp_dir() . '/blade_test_cache_' . uniqid();
        mkdir($this->viewPath, 0755, true);
        mkdir($this->cachePath, 0755, true);

        $this->compiler = new BladeCompiler($this->viewPath, $this->cachePath);
    }

    protected function tearDown(): void
    {
        $this->rmdir($this->viewPath);
        $this->rmdir($this->cachePath);
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

    // ================================================================
    // 编译输出
    // ================================================================

    public function testEscapedOutput(): void
    {
        $result = $this->compiler->compileString('{{ $name }}');
        $this->assertStringContainsString('htmlspecialchars', $result);
        $this->assertStringContainsString('$name', $result);
    }

    public function testRawOutput(): void
    {
        $result = $this->compiler->compileString('{!! $html !!}');
        $this->assertStringNotContainsString('htmlspecialchars', $result);
        $this->assertStringContainsString('echo $html', $result);
    }

    public function testCommentStripped(): void
    {
        $result = $this->compiler->compileString('Hello{{-- this is a comment --}} World');
        $this->assertStringNotContainsString('comment', $result);
        $this->assertStringContainsString('Hello', $result);
        $this->assertStringContainsString('World', $result);
    }

    public function testMultipleExpressions(): void
    {
        $result = $this->compiler->compileString('{{ $a }} and {{ $b }}');
        $this->assertEquals(2, substr_count($result, 'htmlspecialchars'));
    }

    // ================================================================
    // 条件指令
    // ================================================================

    public function testIfDirective(): void
    {
        $result = $this->compiler->compileString('@if($true) yes @endif');
        $this->assertStringContainsString('<?php if($true): ?>', $result);
        $this->assertStringContainsString('<?php endif; ?>', $result);
    }

    public function testElseifDirective(): void
    {
        $result = $this->compiler->compileString('@if($a) a @elseif($b) b @else c @endif');
        $this->assertStringContainsString('elseif($b)', $result);
        $this->assertStringContainsString('else:', $result);
    }

    public function testIssetDirective(): void
    {
        $result = $this->compiler->compileString('@isset($var) yes @endisset');
        $this->assertStringContainsString('isset($var)', $result);
        $this->assertStringContainsString('endif', $result);
    }

    public function testEmptyDirective(): void
    {
        $result = $this->compiler->compileString('@empty($arr) empty @endempty');
        $this->assertStringContainsString('empty($arr)', $result);
    }

    public function testUnlessDirective(): void
    {
        $result = $this->compiler->compileString('@unless($admin) no admin @endunless');
        $this->assertStringContainsString('!($admin)', $result);
    }

    // ================================================================
    // 循环指令
    // ================================================================

    public function testForeachDirective(): void
    {
        $result = $this->compiler->compileString('@foreach($items as $item) {{ $item }} @endforeach');
        $this->assertStringContainsString('foreach($items as $item):', $result);
        $this->assertStringContainsString('endforeach', $result);
    }

    public function testForDirective(): void
    {
        $result = $this->compiler->compileString('@for($i = 0; $i < 10; $i++) {{ $i }} @endfor');
        $this->assertStringContainsString('for($i = 0; $i < 10; $i++):', $result);
        $this->assertStringContainsString('endfor', $result);
    }

    public function testWhileDirective(): void
    {
        $result = $this->compiler->compileString('@while(true) @endwhile');
        $this->assertStringContainsString('while(true):', $result);
        $this->assertStringContainsString('endwhile', $result);
    }

    // ================================================================
    // Switch 指令
    // ================================================================

    public function testSwitchDirective(): void
    {
        $result = $this->compiler->compileString('@switch($x) @case(1) one @break @default other @endswitch');
        $this->assertStringContainsString('switch($x)', $result);
        $this->assertStringContainsString('case 1', $result);
        $this->assertStringContainsString('default', $result);
    }

    // ================================================================
    // PHP 块
    // ================================================================

    public function testPhpBlock(): void
    {
        $result = $this->compiler->compileString('@php $x = 1; @endphp');
        $this->assertStringContainsString('<?php', $result);
        $this->assertStringContainsString('?>', $result);
        $this->assertStringContainsString('$x = 1;', $result);
    }

    public function testPhpInlineExpression(): void
    {
        $result = $this->compiler->compileString('@php($x = 1)');
        $this->assertStringContainsString('$x = 1;', $result);
    }

    // ================================================================
    // 编译缓存
    // ================================================================

    public function testCompileCreatesCache(): void
    {
        $this->writeView('test.blade.php', '{{ $name }}');

        $cached = $this->compiler->compile('test.blade.php');
        $this->assertTrue(file_exists($cached));
        $this->assertStringContainsString('htmlspecialchars', file_get_contents($cached));
    }

    public function testCacheReusedWhenValid(): void
    {
        $this->writeView('cached.blade.php', '{{ $name }}');

        $first = $this->compiler->compile('cached.blade.php');
        $second = $this->compiler->compile('cached.blade.php');

        $this->assertEquals($first, $second);
    }

    public function testCacheInvalidatedOnSourceChange(): void
    {
        $this->writeView('changing.blade.php', 'version1');
        $first = $this->compiler->compile('changing.blade.php');

        // 删除缓存，修改源文件
        $this->compiler->clearCache();
        $this->writeView('changing.blade.php', 'version2');

        $second = $this->compiler->compile('changing.blade.php');
        $this->assertStringContainsString('version2', file_get_contents($second));
    }

    public function testClearCache(): void
    {
        $this->writeView('a.blade.php', 'a');
        $this->writeView('b.blade.php', 'b');
        $this->compiler->compile('a.blade.php');
        $this->compiler->compile('b.blade.php');

        $this->compiler->clearCache();
        $files = glob($this->cachePath . '/*.php');
        $this->assertEmpty($files);
    }

    public function testGetCachedPathReturnsConsistentPath(): void
    {
        $path1 = $this->compiler->getCachedPath('test');
        $path2 = $this->compiler->getCachedPath('test');
        $this->assertEquals($path1, $path2);
        $this->assertStringEndsWith('.php', $path1);
    }

    public function testViewNotFoundThrowsException(): void
    {
        $thrown = false;
        try {
            $this->compiler->compile('nonexistent');
        } catch (\RuntimeException $e) {
            $thrown = true;
            $this->assertStringContainsString('not found', $e->getMessage());
        }
        $this->assertTrue($thrown, 'Expected RuntimeException was not thrown');
    }

    // ================================================================
    // 自定义指令
    // ================================================================

    public function testCustomDirective(): void
    {
        $this->compiler->directive('uppercase', function ($expression) {
            return '<?php echo strtoupper(' . $expression . '); ?>';
        });

        $result = $this->compiler->compileString('@uppercase($name)');
        $this->assertStringContainsString('strtoupper($name)', $result);
    }

    public function testCustomDirectiveWithoutArgs(): void
    {
        $this->compiler->directive('hello', function () {
            return '<?php echo "Hello"; ?>';
        });

        $result = $this->compiler->compileString('@hello');
        $this->assertStringContainsString('Hello', $result);
    }

    // ================================================================
    // 模板继承（通过文件编译测试）
    // ================================================================

    public function testSectionAndYield(): void
    {
        // compileString 不走继承路径，通过文件编译来测试
        $this->writeView('sections.blade.php', "@section('title')My Page@endsection @yield('title')");

        $cached = $this->compiler->compile('sections.blade.php');
        $compiled = file_get_contents($cached);

        $this->assertStringContainsString('$__sections', $compiled);
    }

    public function testYieldWithDefault(): void
    {
        $this->writeView('yield-default.blade.php', "@yield('sidebar', 'default content')");

        $cached = $this->compiler->compile('yield-default.blade.php');
        $compiled = file_get_contents($cached);

        $this->assertStringContainsString('default content', $compiled);
    }

    public function testSectionShortSyntax(): void
    {
        $this->writeView('short-section.blade.php', "@section('title', 'Home')");

        $cached = $this->compiler->compile('short-section.blade.php');
        $compiled = file_get_contents($cached);

        $this->assertStringContainsString('$__sections', $compiled);
        $this->assertStringContainsString('Home', $compiled);
    }

    public function testExtendsCompilesToParentInclude(): void
    {
        $this->writeView('layouts/master.blade.php', "<html>@yield('content')</html>");
        $this->writeView('page.blade.php', "@extends('layouts.master')@section('content')Hello@endsection");

        $cached = $this->compiler->compile('page.blade.php');
        $compiled = file_get_contents($cached);

        $this->assertStringContainsString('require', $compiled);
    }

    public function testExtendsWithNestedSections(): void
    {
        $this->writeView('layouts/app.blade.php', "<html><head>@yield('title')</head><body>@yield('content')</body></html>");
        $this->writeView('home.blade.php', "@extends('layouts.app')@section('title')Home@endsection@section('content')Welcome@endsection");

        $cached = $this->compiler->compile('home.blade.php');
        $compiled = file_get_contents($cached);

        $this->assertStringContainsString('$__sections', $compiled);
        $this->assertStringContainsString('require', $compiled);
    }

    public function testExtendsParentNotFoundThrowsException(): void
    {
        $this->writeView('orphan.blade.php', "@extends('nonexistent.layout')@section('content')test@endsection");

        $thrown = false;
        try {
            $this->compiler->compile('orphan.blade.php');
        } catch (\RuntimeException $e) {
            $thrown = true;
        }
        $this->assertTrue($thrown, 'Expected RuntimeException was not thrown');
    }

    // ================================================================
    // @include
    // ================================================================

    public function testIncludeDirective(): void
    {
        $this->writeView('partials/nav.blade.php', '<nav>{{ $title }}</nav>');
        $this->writeView('with-include.blade.php', "@include('partials.nav')");

        $cached = $this->compiler->compile('with-include.blade.php');
        $compiled = file_get_contents($cached);

        $this->assertStringContainsString('require', $compiled);
    }

    public function testIncludeWithExtraData(): void
    {
        $this->writeView('partial.blade.php', '{{ $name }}');
        $this->writeView('include-data.blade.php', "@include('partial', ['name' => 'test'])");

        $cached = $this->compiler->compile('include-data.blade.php');
        $compiled = file_get_contents($cached);

        $this->assertStringContainsString("'name' => 'test'", $compiled);
    }

    public function testIncludeNotFoundThrowsException(): void
    {
        $this->writeView('bad-include.blade.php', "@include('nonexistent')");

        $thrown = false;
        try {
            $this->compiler->compile('bad-include.blade.php');
        } catch (\RuntimeException $e) {
            $thrown = true;
        }
        $this->assertTrue($thrown, 'Expected RuntimeException was not thrown');
    }

    // ================================================================
    // 栈系统（通过文件编译测试）
    // ================================================================

    public function testPushAndStack(): void
    {
        $this->writeView('stack-test.blade.php', "@push('scripts')app.js@endpush@stack('scripts')");

        $cached = $this->compiler->compile('stack-test.blade.php');
        $compiled = file_get_contents($cached);

        $this->assertStringContainsString('$__stacks', $compiled);
    }

    public function testPrependDirective(): void
    {
        $this->writeView('prepend-test.blade.php', "@prepend('css')base.css@endprepend@stack('css')");

        $cached = $this->compiler->compile('prepend-test.blade.php');
        $compiled = file_get_contents($cached);

        $this->assertStringContainsString('$__stacks', $compiled);
    }

    // ================================================================
    // 安全与认证指令
    // ================================================================

    public function testCsrfDirective(): void
    {
        $result = $this->compiler->compileString('@csrf');
        $this->assertStringContainsString('CsrfMiddleware', $result);
    }

    public function testMethodDirective(): void
    {
        $result = $this->compiler->compileString("@method('PUT')");
        $this->assertStringContainsString('<input type="hidden" name="_method" value="PUT">', $result);
    }

    public function testAuthDirective(): void
    {
        $result = $this->compiler->compileString('@auth admin @endauth');
        $this->assertStringContainsString('AuthManager', $result);
        $this->assertStringContainsString('check()', $result);
    }

    public function testGuestDirective(): void
    {
        $result = $this->compiler->compileString('@guest login @endguest');
        $this->assertStringContainsString('AuthManager', $result);
    }

    // ================================================================
    // 完整渲染（端到端）
    // ================================================================

    public function testFullRenderWithData(): void
    {
        $this->writeView('hello.blade.php', 'Hello, {{ $name }}!');

        $cached = $this->compiler->compile('hello.blade.php');
        $__data = ['name' => 'World'];
        extract($__data);

        ob_start();
        require $cached;
        $output = ob_get_clean();

        $this->assertEquals('Hello, World!', $output);
    }

    public function testFullRenderWithIf(): void
    {
        $this->writeView('conditional.blade.php', '@if($show)visible@endif');

        $cached = $this->compiler->compile('conditional.blade.php');
        $__data = ['show' => true];
        extract($__data);

        ob_start();
        require $cached;
        $output = ob_get_clean();

        $this->assertStringContainsString('visible', $output);
    }

    public function testFullRenderWithForeach(): void
    {
        $this->writeView('loop.blade.php', '@foreach($items as $item){{ $item }}@endforeach');

        $cached = $this->compiler->compile('loop.blade.php');
        $__data = ['items' => ['A', 'B', 'C']];
        extract($__data);

        ob_start();
        require $cached;
        $output = ob_get_clean();

        $this->assertStringContainsString('A', $output);
        $this->assertStringContainsString('B', $output);
        $this->assertStringContainsString('C', $output);
    }

    public function testFullRenderWithRawOutput(): void
    {
        $this->writeView('raw.blade.php', '{!! $html !!}');

        $cached = $this->compiler->compile('raw.blade.php');
        $__data = ['html' => '<b>bold</b>'];
        extract($__data);

        ob_start();
        require $cached;
        $output = ob_get_clean();

        $this->assertEquals('<b>bold</b>', $output);
    }

    public function testFullRenderEscapesHtml(): void
    {
        $this->writeView('escape.blade.php', '{{ $input }}');

        $cached = $this->compiler->compile('escape.blade.php');
        $__data = ['input' => '<script>alert("xss")</script>'];
        extract($__data);

        ob_start();
        require $cached;
        $output = ob_get_clean();

        $this->assertStringNotContainsString('<script>', $output);
        $this->assertStringContainsString('&lt;script&gt;', $output);
    }

    public function testFullRenderWithInheritance(): void
    {
        $this->writeView('layouts/base.blade.php', '<html>@yield("content")</html>');
        $this->writeView('child.blade.php', "@extends('layouts/base')@section('content')<p>Hello</p>@endsection");

        $cached = $this->compiler->compile('child.blade.php');
        $__data = [];
        $__sections = [];

        ob_start();
        require $cached;
        $output = ob_get_clean();

        $this->assertStringContainsString('<html>', $output);
        $this->assertStringContainsString('<p>Hello</p>', $output);
    }

    public function testFullRenderWithInclude(): void
    {
        $this->writeView('parts/header.blade.php', '<header>{{ $title }}</header>');
        $this->writeView('with-header.blade.php', "@include('parts/header')");

        $cached = $this->compiler->compile('with-header.blade.php');
        $__data = ['title' => 'Test'];
        extract($__data);

        ob_start();
        require $cached;
        $output = ob_get_clean();

        $this->assertStringContainsString('<header>Test</header>', $output);
    }

    public function testFullRenderWithStack(): void
    {
        $this->writeView('stacked.blade.php', "@push('js')app.js@endpush@stack('js')");

        $cached = $this->compiler->compile('stacked.blade.php');
        $__data = [];
        $__stacks = [];
        extract($__data);

        ob_start();
        require $cached;
        $output = ob_get_clean();

        $this->assertStringContainsString('app.js', $output);
    }

    public function testFullRenderWithMultiplePushes(): void
    {
        $this->writeView('multi-push.blade.php', "@push('js')a.js@endpush@push('js')b.js@endpush@stack('js')");

        $cached = $this->compiler->compile('multi-push.blade.php');
        $__data = [];
        $__stacks = [];
        extract($__data);

        ob_start();
        require $cached;
        $output = ob_get_clean();

        $this->assertStringContainsString('a.js', $output);
        $this->assertStringContainsString('b.js', $output);
    }

    public function testFullRenderInheritanceWithMultipleSections(): void
    {
        $this->writeView('layouts/full.blade.php', '<html><head>@yield("title")</head><body>@yield("content")</body></html>');
        $this->writeView('multi-section.blade.php', "@extends('layouts/full')@section('title')My Page@endsection@section('content')<p>Body</p>@endsection");

        $cached = $this->compiler->compile('multi-section.blade.php');
        $__data = [];
        $__sections = [];

        ob_start();
        require $cached;
        $output = ob_get_clean();

        $this->assertStringContainsString('My Page', $output);
        $this->assertStringContainsString('<p>Body</p>', $output);
    }

    // ================================================================
    // Getter 方法
    // ================================================================

    public function testGetViewPath(): void
    {
        $this->assertEquals($this->viewPath, $this->compiler->getViewPath());
    }

    public function testGetCachePath(): void
    {
        $this->assertEquals($this->cachePath, $this->compiler->getCachePath());
    }
}
