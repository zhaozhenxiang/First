<?php

declare(strict_types=1);

namespace Tests;

use Bin\Testing\TestCase;
use Bin\Database\Collection;

class CollectionTest extends TestCase
{
    public function testMakeCollection(): void
    {
        $collection = Collection::make([1, 2, 3]);

        $this->assertEquals(3, $collection->count());
        $this->assertEquals([1, 2, 3], $collection->all());
    }

    public function testFilter(): void
    {
        $collection = Collection::make([1, 2, 3, 4, 5]);

        $filtered = $collection->filter(fn($item) => $item > 2);

        $this->assertEquals([3, 4, 5], array_values($filtered));
    }

    public function testMap(): void
    {
        $collection = Collection::make([1, 2, 3]);

        $mapped = $collection->map(fn($item) => $item * 2);

        $this->assertEquals([2, 4, 6], $mapped);
    }

    public function testPluck(): void
    {
        $users = [
            ['name' => 'John', 'email' => 'john@example.com'],
            ['name' => 'Jane', 'email' => 'jane@example.com'],
        ];

        $collection = Collection::make($users);

        $names = $collection->pluck('name');

        $this->assertEquals(['John', 'Jane'], $names);
    }

    public function testGroupBy(): void
    {
        $items = [
            ['category' => 'fruit', 'name' => 'apple'],
            ['category' => 'fruit', 'name' => 'banana'],
            ['category' => 'vegetable', 'name' => 'carrot'],
        ];

        $collection = Collection::make($items);

        $grouped = $collection->groupBy('category');

        $this->assertArrayHasKey('fruit', $grouped);
        $this->assertCount(2, $grouped['fruit']);
    }

    public function testFirst(): void
    {
        $collection = Collection::make([1, 2, 3, 4, 5]);

        $this->assertEquals(1, $collection->first());
        $this->assertEquals(3, $collection->first(fn($item) => $item > 2));
    }

    public function testTake(): void
    {
        $collection = Collection::make([1, 2, 3, 4, 5]);

        $taken = $collection->take(3);

        $this->assertEquals([1, 2, 3], $taken);
    }

    public function testSkip(): void
    {
        $collection = Collection::make([1, 2, 3, 4, 5]);

        $skipped = $collection->skip(2);

        $this->assertEquals([3, 4, 5], $skipped);
    }

    public function testSum(): void
    {
        $collection = Collection::make([1, 2, 3, 4, 5]);

        $this->assertEquals(15, $collection->sum());
    }

    public function testAvg(): void
    {
        $collection = Collection::make([1, 2, 3, 4, 5]);

        $this->assertEquals(3.0, $collection->avg());
    }

    public function testMax(): void
    {
        $collection = Collection::make([1, 2, 3, 4, 5]);

        $this->assertEquals(5, $collection->max());
    }

    public function testMin(): void
    {
        $collection = Collection::make([1, 2, 3, 4, 5]);

        $this->assertEquals(1, $collection->min());
    }

    public function testContains(): void
    {
        $collection = Collection::make([1, 2, 3, 4, 5]);

        $this->assertTrue($collection->contains(3));
        $this->assertFalse($collection->contains(10));
    }

    public function testReverse(): void
    {
        $collection = Collection::make([1, 2, 3]);

        $reversed = $collection->reverse();

        $this->assertEquals([3, 2, 1], $reversed);
    }

    public function testUnique(): void
    {
        $collection = Collection::make([1, 2, 2, 3, 3, 3]);

        $unique = $collection->unique();

        $this->assertEquals([1, 2, 3], array_values($unique));
    }
}
