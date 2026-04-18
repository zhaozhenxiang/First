<?php

declare(strict_types=1);

namespace Tests;

use Bin\Testing\TestCase;
use Bin\View\View;

class ViewTest extends TestCase
{
    // View 使用 BASE_PATH 常量和 require_once
    // 静态属性在进程内共享，需要注意清理

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    public function testViewMakeAppendsPhpSuffix(): void
    {
        $view = View::make('layout');
        $path = $view->getView();
        $this->assertStringEndsWith('.php', $path);
    }

    public function testViewMakeKeepsExistingPhpSuffix(): void
    {
        $view = View::make('layout.php');
        $path = $view->getView();
        $this->assertFalse(str_ends_with($path, '.php.php'));
    }

    public function testViewWithSetsData(): void
    {
        $view = View::make('layout');
        $result = $view->with('key', 'value');

        $this->assertSame($view, $result);
    }

    public function testViewGetData(): void
    {
        $view = View::make('layout');
        $view->with('name', 'Alice');
        $view->with('age', 30);

        $data = $view->getData();
        $this->assertEquals('Alice', $data['name']);
        $this->assertEquals(30, $data['age']);
    }

    public function testViewGetViewContainsViewsPath(): void
    {
        $view = View::make('pages/home');
        $path = $view->getView();
        $this->assertStringContainsString('pages/home', $path);
    }

    public function testViewGetViewContainsBasePath(): void
    {
        $view = View::make('layout');
        $path = $view->getView();
        $this->assertStringContainsString('/views/', $path);
    }
}
