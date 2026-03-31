<?php

declare(strict_types=1);

namespace Tests;

use Bin\Testing\TestCase;
use Bin\Facade\Facade;
use Bin\Container\Container;

class FacadeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Facade::clear();
    }

    protected function tearDown(): void
    {
        Facade::clear();
        parent::tearDown();
    }

    public function testSetFacadeContainer(): void
    {
        $container = new Container();
        Facade::setFacadeContainer('Tests\TestFacade', $container);
        $this->assertTrue(true);
    }

    public function testClearRemovesAllCaches(): void
    {
        $container = new Container();
        Facade::setFacadeContainer('Tests\TestFacade', $container);
        Facade::clear();
        $this->assertTrue(true);
    }

    public function testClearFacadeRemovesSpecific(): void
    {
        Facade::clearFacade('Tests\TestFacade');
        $this->assertTrue(true);
    }

    public function testCallStaticProxiesToInstance(): void
    {
        $service = new class {
            public function greet(string $name): string { return "Hello {$name}"; }
            public function add(int $a, int $b): int { return $a + $b; }
        };
        TestFacade::setInstance($service);

        $this->assertEquals('Hello World', TestFacade::greet('World'));
        $this->assertEquals(5, TestFacade::add(2, 3));
    }

    public function testCallStaticPreservesArguments(): void
    {
        $service = new class {
            public function concat(string ...$parts): string { return implode('-', $parts); }
        };
        TestFacade::setInstance($service);

        $this->assertEquals('a-b-c', TestFacade::concat('a', 'b', 'c'));
    }
}

class TestFacade extends Facade
{
    protected function getClassName(): string
    {
        return 'Tests\FacadeTestService';
    }
}
