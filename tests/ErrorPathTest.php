<?php

declare(strict_types=1);

namespace Tests;

use Bin\Testing\TestCase;
use Bin\Database\Collection;
use Bin\Database\QueryBuilder;
use Bin\Database\Model;
use Bin\Response\Response;
use PDO;

class ErrorPathTest extends TestCase
{
    // === Collection 空集合边界 ===

    public function testFirstOnEmptyReturnsNull(): void
    {
        $collection = Collection::make([]);
        $this->assertNull($collection->first());
    }

    public function testLastOnEmptyReturnsNull(): void
    {
        $collection = Collection::make([]);
        $this->assertNull($collection->last());
    }

    public function testPopOnEmptyReturnsEmptyCollection(): void
    {
        $collection = Collection::make([]);
        $this->assertTrue($collection->pop()->isEmpty());
    }

    public function testSumOnEmptyReturnsZero(): void
    {
        $collection = Collection::make([]);
        $this->assertEquals(0, $collection->sum());
    }

    public function testAvgOnEmptyReturnsNull(): void
    {
        $collection = Collection::make([]);
        $this->assertNull($collection->avg());
    }

    public function testMaxOnEmptyReturnsNull(): void
    {
        $collection = Collection::make([]);
        $this->assertNull($collection->max());
    }

    public function testMinOnEmptyReturnsNull(): void
    {
        $collection = Collection::make([]);
        $this->assertNull($collection->min());
    }

    public function testContainsOnEmptyReturnsFalse(): void
    {
        $collection = Collection::make([]);
        $this->assertFalse($collection->contains('anything'));
    }

    // === QueryBuilder 错误路径 ===

    public function testFindOrFailThrowsException(): void
    {
        $hasException = false;
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE err_test (id INTEGER PRIMARY KEY, name TEXT)');

        $qb = new QueryBuilder($pdo);
        try {
            $qb->from('err_test')->findOrFail(999);
        } catch (\Throwable $e) {
            $hasException = true;
        }
        $this->assertTrue($hasException);
    }

    public function testFindReturnsNullForMissingRecord(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE err_find (id INTEGER PRIMARY KEY, name TEXT)');

        $qb = new QueryBuilder($pdo);
        $result = $qb->from('err_find')->find(999);
        $this->assertNull($result);
    }

    public function testQueryBuilderEscapesValuesViaBindings(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE inject_test (id INTEGER PRIMARY KEY, name TEXT)');
        $pdo->exec("INSERT INTO inject_test (id, name) VALUES (1, 'normal')");

        $qb = new QueryBuilder($pdo);
        $results = $qb->from('inject_test')->where('name', "'; DROP TABLE inject_test; --")->get();
        $this->assertTrue(count($results) === 0);

        // 表应该还存在
        $stmt = $pdo->query('SELECT COUNT(*) FROM inject_test');
        $this->assertEquals(1, $stmt->fetchColumn());
    }

    public function testInsertWithSpecialCharacters(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE special_chars (id INTEGER PRIMARY KEY, content TEXT)');

        $qb = new QueryBuilder($pdo);
        $qb->from('special_chars')->insert(['content' => '<script>alert("xss")</script>']);

        $stmt = $pdo->query('SELECT content FROM special_chars WHERE id = 1');
        $this->assertEquals('<script>alert("xss")</script>', $stmt->fetchColumn());
    }

    // === Response 边界 ===

    public function testResponseNullContent(): void
    {
        $response = new Response(null);
        $this->assertEquals('', (string) $response);
    }

    public function testResponseEmptyString(): void
    {
        $response = new Response('');
        $this->assertEquals('', (string) $response);
    }

    public function testResponseArrayWithEmptyArray(): void
    {
        $response = new Response([]);
        $this->assertEquals('[]', (string) $response);
    }

    public function testResponseSetContentOverrides(): void
    {
        $response = new Response('original');
        $response->setContent('replaced');
        $this->assertEquals('replaced', $response->getContent());
    }

    // === Request 空状态 ===

    public function testRequestInputReturnsDefaultForMissing(): void
    {
        $request = new \Bin\Request\Request(query: []);
        $this->assertEquals('default', $request->input('nonexistent', 'default'));
    }

    public function testRequestAllReturnsEmptyArray(): void
    {
        $request = new \Bin\Request\Request(query: []);
        $this->assertEquals([], $request->all());
    }

    public function testRequestOnlyReturnsEmptyForMissing(): void
    {
        $request = new \Bin\Request\Request(query: ['a' => 1]);
        $result = $request->only(['b', 'c']);
        $this->assertEquals([], $result);
    }

    public function testRequestExceptReturnsAllExcluding(): void
    {
        $request = new \Bin\Request\Request(
            query: ['a' => 1, 'b' => 2, 'c' => 3],
        );
        $result = $request->except(['a']);
        $this->assertArrayNotHasKey('a', $result);
        $this->assertArrayHasKey('b', $result);
    }

    // === Collection 边界操作 ===

    public function testSliceWithNegativeOffset(): void
    {
        $collection = Collection::make([1, 2, 3, 4, 5]);
        $result = $collection->slice(-2);
        $this->assertEquals([4, 5], $result->values()->toArray());
    }

    public function testChunkWithSizeLargerThanCollection(): void
    {
        $collection = Collection::make([1, 2, 3]);
        $chunks = $collection->chunk(10);
        $this->assertCount(1, $chunks);
        $this->assertEquals([1, 2, 3], $chunks[0]->toArray());
    }

    public function testMergeWithEmptyArray(): void
    {
        $collection = Collection::make([1, 2, 3]);
        $result = $collection->merge([]);
        $this->assertEquals([1, 2, 3], $result->toArray());
    }

    public function testDiffWithEmptyArray(): void
    {
        $collection = Collection::make([1, 2, 3]);
        $result = $collection->diff([]);
        $this->assertEquals([1, 2, 3], $result->values()->toArray());
    }

    // === Model 错误路径 ===

    public function testModelFindReturnsNullForMissing(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE err_models (id INTEGER PRIMARY KEY, name TEXT)');

        ErrTestModel::setConnection($pdo);
        ErrTestModel::resetBooted();

        $result = ErrTestModel::find(999);
        $this->assertNull($result);

        ErrTestModel::setConnection(null);
    }
}

class ErrTestModel extends Model
{
    protected string $table = 'err_models';
    protected array $fillable = ['name'];
    protected bool $timestamps = false;
}
