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

        $this->assertEquals([3, 4, 5], $filtered->values()->toArray());
    }

    public function testMap(): void
    {
        $collection = Collection::make([1, 2, 3]);

        $mapped = $collection->map(fn($item) => $item * 2);

        $this->assertEquals([2, 4, 6], $mapped->toArray());
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

        $this->assertEquals([1, 2, 3], $taken->toArray());
    }

    public function testSkip(): void
    {
        $collection = Collection::make([1, 2, 3, 4, 5]);

        $skipped = $collection->skip(2);

        $this->assertEquals([3, 4, 5], $skipped->toArray());
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

        $this->assertEquals([3, 2, 1], $reversed->toArray());
    }

    public function testUnique(): void
    {
        $collection = Collection::make([1, 2, 2, 3, 3, 3]);

        $unique = $collection->unique();

        $this->assertEquals([1, 2, 3], $unique->values()->toArray());
    }

    // === 新增测试 ===

    public function testLast(): void
    {
        $collection = Collection::make([1, 2, 3, 4, 5]);
        $this->assertEquals(5, $collection->last());
        $this->assertEquals(3, $collection->last(fn($item) => $item < 4));
    }

    public function testReject(): void
    {
        $collection = Collection::make([1, 2, 3, 4, 5]);
        $rejected = $collection->reject(fn($item) => $item > 3);
        $this->assertEquals([1, 2, 3], $rejected->values()->toArray());
    }

    public function testEach(): void
    {
        $collection = Collection::make([1, 2, 3]);
        $result = [];
        $collection->each(function ($item) use (&$result) {
            $result[] = $item * 2;
        });
        $this->assertEquals([2, 4, 6], $result);
    }

    public function testReduce(): void
    {
        $collection = Collection::make([1, 2, 3, 4, 5]);
        $sum = $collection->reduce(fn($carry, $item) => $carry + $item, 0);
        $this->assertEquals(15, $sum);
    }

    public function testSlice(): void
    {
        $collection = Collection::make([1, 2, 3, 4, 5]);
        $sliced = $collection->slice(1, 3);
        $this->assertEquals([2, 3, 4], $sliced->values()->toArray());
    }

    public function testChunk(): void
    {
        $collection = Collection::make([1, 2, 3, 4, 5, 6, 7]);
        $chunks = $collection->chunk(3);
        $this->assertCount(3, $chunks);
        $this->assertEquals([1, 2, 3], $chunks[0]->toArray());
        $this->assertEquals([4, 5, 6], $chunks[1]->toArray());
        $this->assertEquals([7], $chunks[2]->toArray());
    }

    public function testSort(): void
    {
        $collection = Collection::make([3, 1, 2]);
        $sorted = $collection->sort(fn($a, $b) => $a <=> $b);
        $this->assertEquals([1, 2, 3], $sorted->values()->toArray());
    }

    public function testSortBy(): void
    {
        $users = [
            ['name' => 'Charlie', 'age' => 30],
            ['name' => 'Alice', 'age' => 25],
            ['name' => 'Bob', 'age' => 28],
        ];
        $collection = Collection::make($users);
        $sorted = $collection->sortBy('age');
        $this->assertEquals('Alice', $sorted[0]['name']);
        $this->assertEquals('Bob', $sorted[1]['name']);
    }

    public function testWhere(): void
    {
        $users = [
            ['name' => 'Alice', 'age' => 25],
            ['name' => 'Bob', 'age' => 30],
            ['name' => 'Charlie', 'age' => 25],
        ];
        $collection = Collection::make($users);
        $result = $collection->where('age', 25);
        $this->assertCount(2, $result);
    }

    public function testWhereIn(): void
    {
        $users = [
            ['name' => 'Alice', 'role' => 'admin'],
            ['name' => 'Bob', 'role' => 'user'],
            ['name' => 'Charlie', 'role' => 'admin'],
        ];
        $collection = Collection::make($users);
        $result = $collection->whereIn('role', ['admin']);
        $this->assertCount(2, $result);
    }

    public function testWhereNull(): void
    {
        $users = [
            ['name' => 'Alice', 'email' => 'a@test.com'],
            ['name' => 'Bob', 'email' => null],
        ];
        $collection = Collection::make($users);
        $result = $collection->whereNull('email');
        $this->assertCount(1, $result);
        $this->assertEquals('Bob', $result->first()['name']);
    }

    public function testWhereNotNull(): void
    {
        $users = [
            ['name' => 'Alice', 'email' => 'a@test.com'],
            ['name' => 'Bob', 'email' => null],
        ];
        $collection = Collection::make($users);
        $result = $collection->whereNotNull('email');
        $this->assertCount(1, $result);
        $this->assertEquals('Alice', $result->first()['name']);
    }

    public function testKeyBy(): void
    {
        $users = [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ];
        $collection = Collection::make($users);
        $result = $collection->keyBy('id');
        $this->assertEquals('Alice', $result[1]['name']);
        $this->assertEquals('Bob', $result[2]['name']);
    }

    public function testCollapse(): void
    {
        $collection = Collection::make([[1, 2], [3, 4], [5]]);
        $result = $collection->collapse();
        $this->assertEquals([1, 2, 3, 4, 5], $result->toArray());
    }

    public function testFlatten(): void
    {
        $collection = Collection::make([1, [2, [3, 4]], 5]);
        $result = $collection->flatten();
        $this->assertEquals([1, 2, 3, 4, 5], $result->toArray());
    }

    public function testFlip(): void
    {
        $collection = Collection::make(['a' => 1, 'b' => 2]);
        $result = $collection->flip();
        $this->assertEquals([1 => 'a', 2 => 'b'], $result->toArray());
    }

    public function testKeys(): void
    {
        $collection = Collection::make(['name' => 'Alice', 'age' => 25]);
        $this->assertEquals(['name', 'age'], $collection->keys()->toArray());
    }

    public function testValues(): void
    {
        $collection = Collection::make(['a' => 1, 'b' => 2, 'c' => 3]);
        $this->assertEquals([1, 2, 3], $collection->values()->toArray());
    }

    public function testMerge(): void
    {
        $collection = Collection::make([1, 2]);
        $result = $collection->merge([3, 4]);
        $this->assertEquals([1, 2, 3, 4], $result->toArray());
    }

    public function testDiff(): void
    {
        $collection = Collection::make([1, 2, 3, 4]);
        $result = $collection->diff([2, 4]);
        $this->assertEquals([1, 3], $result->values()->toArray());
    }

    public function testIntersect(): void
    {
        $collection = Collection::make([1, 2, 3, 4]);
        $result = $collection->intersect([2, 3, 5]);
        $this->assertEquals([2, 3], $result->values()->toArray());
    }

    public function testCombine(): void
    {
        $collection = Collection::make(['a', 'b']);
        $result = $collection->combine([1, 2]);
        $this->assertEquals(['a' => 1, 'b' => 2], $result);
    }

    public function testNth(): void
    {
        $collection = Collection::make([0, 1, 2, 3, 4, 5]);
        $result = $collection->nth(2);
        $this->assertEquals([0, 2, 4], $result->values()->toArray());
    }

    public function testIsEmpty(): void
    {
        $collection = Collection::make([]);
        $this->assertTrue($collection->isEmpty());
        $this->assertFalse($collection->isNotEmpty());
    }

    public function testIsNotEmpty(): void
    {
        $collection = Collection::make([1]);
        $this->assertFalse($collection->isEmpty());
        $this->assertTrue($collection->isNotEmpty());
    }

    public function testToJson(): void
    {
        $collection = Collection::make(['a' => 1, 'b' => 2]);
        $json = $collection->toJson();
        $decoded = json_decode($json, true);
        $this->assertEquals(['a' => 1, 'b' => 2], $decoded);
    }

    public function testArrayAccess(): void
    {
        $collection = Collection::make(['a' => 1, 'b' => 2]);
        $this->assertTrue(isset($collection['a']));
        $this->assertEquals(1, $collection['a']);
        $collection['c'] = 3;
        $this->assertEquals(3, $collection['c']);
        unset($collection['b']);
        $this->assertFalse(isset($collection['b']));
    }

    public function testGetIterator(): void
    {
        $collection = Collection::make([1, 2, 3]);
        $result = [];
        foreach ($collection as $item) {
            $result[] = $item;
        }
        $this->assertEquals([1, 2, 3], $result);
    }

    public function testToArray(): void
    {
        $collection = Collection::make([1, 2, 3]);
        $this->assertEquals([1, 2, 3], $collection->toArray());
    }

    public function testPop(): void
    {
        $collection = Collection::make([1, 2, 3]);
        $popped = $collection->pop();
        $this->assertEquals([1, 2], $popped->toArray());
    }

    public function testShuffle(): void
    {
        $collection = Collection::make([1, 2, 3, 4, 5]);
        $shuffled = $collection->shuffle();
        // 只验证元素相同（顺序可能不同）
        $arr = $shuffled->toArray();
        sort($arr);
        $this->assertEquals([1, 2, 3, 4, 5], $arr);
    }

    public function testUnion(): void
    {
        $collection = Collection::make(['a' => 1, 'b' => 2]);
        $result = $collection->union(['b' => 20, 'c' => 30]);
        $this->assertEquals(1, $result['a']);
        $this->assertEquals(2, $result['b']); // 原始值优先
        $this->assertEquals(30, $result['c']);
    }
}
