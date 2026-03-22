<?php

declare(strict_types=1);

namespace Tests;

use Bin\Testing\TestCase;

class ExampleTest extends TestCase
{
    public function testTrue(): void
    {
        $this->assertTrue(true);
    }

    public function testFalse(): void
    {
        $this->assertFalse(false);
    }

    public function testEquals(): void
    {
        $this->assertEquals(4, 2 + 2);
    }

    public function testSame(): void
    {
        $this->assertSame('hello', 'hello');
    }

    public function testArray(): void
    {
        $array = [1, 2, 3];

        $this->assertCount(3, $array);
        $this->assertContains(2, $array);
        $this->assertArrayHasKey(1, $array);
    }

    public function testString(): void
    {
        $string = 'Hello, World!';

        $this->assertStringContainsString('Hello', $string);
        $this->assertStringStartsWith('Hello', $string);
        $this->assertStringEndsWith('!', $string);
    }

    public function testNull(): void
    {
        $this->assertNull(null);
        $this->assertNotNull('not null');
    }

    public function testEmpty(): void
    {
        $this->assertEmpty([]);
        $this->assertNotEmpty([1]);
    }

    public function testGreaterThan(): void
    {
        $this->assertGreaterThan(5, 10);
        $this->assertLessThan(15, 10);
    }

    public function testInstanceOf(): void
    {
        $this->assertInstanceOf(\ArrayObject::class, new \ArrayObject());
    }

    public function testThrows(): void
    {
        $this->assertThrows(\InvalidArgumentException::class, function () {
            throw new \InvalidArgumentException('Test exception');
        });
    }
}
