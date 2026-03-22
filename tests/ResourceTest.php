<?php

declare(strict_types=1);

namespace Tests;

use Bin\Testing\TestCase;
use Bin\Resource\JsonResource;
use Bin\Resource\ResourceCollection;
use Bin\Resource\AnonymousResourceCollection;

/**
 * API Resource 测试
 */
class ResourceTest extends TestCase
{
    // 简单用户资源类
    protected function createUserResource(array $user): JsonResource
    {
        return new class($user) extends JsonResource {
            public function toArray(): array
            {
                return [
                    'id' => $this->id(),
                    'name' => $this->resource['name'] ?? '',
                    'email' => $this->resource['email'] ?? '',
                ];
            }
        };
    }

    // 带关系用户资源类
    protected function createUserWithPostsResource(array $user): JsonResource
    {
        return new class($user) extends JsonResource {
            public function toArray(): array
            {
                return [
                    'id' => $this->id(),
                    'name' => $this->resource['name'] ?? '',
                    'email' => $this->resource['email'] ?? '',
                ];
            }

            public function posts(): array
            {
                $posts = $this->resource['posts'] ?? [];
                return array_map(fn($p) => ['id' => $p['id'], 'title' => $p['title']], $posts);
            }
        };
    }

    // JsonResource 测试
    public function testResourceMake(): void
    {
        $user = ['id' => 1, 'name' => 'John', 'email' => 'john@example.com'];
        $resource = $this->createUserResource($user);

        $this->assertEquals(1, $resource->id());
        $this->assertTrue($resource->exists());
    }

    public function testResourceToArray(): void
    {
        $user = ['id' => 1, 'name' => 'John', 'email' => 'john@example.com'];
        $resource = $this->createUserResource($user);

        $expected = [
            'id' => 1,
            'name' => 'John',
            'email' => 'john@example.com',
        ];

        $this->assertEquals($expected, $resource->toArray());
    }

    public function testResourceWithOnly(): void
    {
        $user = ['id' => 1, 'name' => 'John', 'email' => 'john@example.com'];
        $resource = $this->createUserResource($user)->only(['id', 'name']);

        $data = $resource->jsonSerialize();

        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayNotHasKey('email', $data);
    }

    public function testResourceWithHide(): void
    {
        $user = ['id' => 1, 'name' => 'John', 'email' => 'john@example.com'];
        $resource = $this->createUserResource($user)->hide(['email']);

        $data = $resource->jsonSerialize();

        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayNotHasKey('email', $data);
    }

    public function testResourceWithIncludes(): void
    {
        $user = [
            'id' => 1,
            'name' => 'John',
            'email' => 'john@example.com',
            'posts' => [
                ['id' => 1, 'title' => 'First Post'],
                ['id' => 2, 'title' => 'Second Post'],
            ],
        ];
        $resource = $this->createUserWithPostsResource($user)->includes(['posts']);

        $data = $resource->jsonSerialize();

        $this->assertArrayHasKey('posts', $data);
        $this->assertCount(2, $data['posts']);
    }

    public function testResourceWith(): void
    {
        $user = ['id' => 1, 'name' => 'John', 'email' => 'john@example.com'];
        $resource = $this->createUserResource($user)->with(['extra' => 'data']);

        $data = $resource->jsonSerialize();

        $this->assertArrayHasKey('extra', $data);
        $this->assertEquals('data', $data['extra']);
    }

    public function testResourceToJson(): void
    {
        $user = ['id' => 1, 'name' => 'John', 'email' => 'john@example.com'];
        $resource = $this->createUserResource($user);

        $json = $resource->toJson();

        $this->assertStringContainsString('"id":1', $json);
        $this->assertStringContainsString('"name":"John"', $json);
    }

    public function testResourceJsonSerialize(): void
    {
        $user = ['id' => 1, 'name' => 'John', 'email' => 'john@example.com'];
        $resource = $this->createUserResource($user);

        $data = json_encode($resource, JSON_THROW_ON_ERROR);
        $decoded = json_decode($data, true);

        $this->assertEquals(1, $decoded['id']);
        $this->assertEquals('John', $decoded['name']);
    }

    public function testResourceToResponse(): void
    {
        $user = ['id' => 1, 'name' => 'John', 'email' => 'john@example.com'];
        $resource = $this->createUserResource($user);

        $response = $resource->toResponse();

        $this->assertInstanceOf(\Bin\Response\Response::class, $response);
    }

    // ResourceCollection 测试
    public function testResourceCollectionMake(): void
    {
        $users = [
            ['id' => 1, 'name' => 'John', 'email' => 'john@example.com'],
            ['id' => 2, 'name' => 'Jane', 'email' => 'jane@example.com'],
        ];

        $collection = ResourceCollection::make($users, get_class($this->createUserResource([])));

        $this->assertEquals(2, $collection->count());
    }

    public function testResourceCollectionToArray(): void
    {
        $users = [
            ['id' => 1, 'name' => 'John', 'email' => 'john@example.com'],
            ['id' => 2, 'name' => 'Jane', 'email' => 'jane@example.com'],
        ];

        $collection = ResourceCollection::make($users, get_class($this->createUserResource([])));

        $data = $collection->collect();

        $this->assertCount(2, $data);
        $this->assertEquals('John', $data[0]['name']);
        $this->assertEquals('Jane', $data[1]['name']);
    }

    public function testResourceCollectionWithPagination(): void
    {
        $users = [
            ['id' => 1, 'name' => 'John', 'email' => 'john@example.com'],
            ['id' => 2, 'name' => 'Jane', 'email' => 'jane@example.com'],
        ];

        $collection = ResourceCollection::make($users, get_class($this->createUserResource([])))
            ->pagination([
                'total' => 10,
                'per_page' => 2,
                'current_page' => 1,
            ]);

        $data = $collection->jsonSerialize();

        $this->assertArrayHasKey('meta', $data);
        $this->assertArrayHasKey('pagination', $data['meta']);
        $this->assertEquals(10, $data['meta']['pagination']['total']);
    }

    public function testResourceCollectionWith(): void
    {
        $users = [
            ['id' => 1, 'name' => 'John', 'email' => 'john@example.com'],
        ];

        $collection = ResourceCollection::make($users, get_class($this->createUserResource([])))
            ->with(['version' => '1.0']);

        $data = $collection->jsonSerialize();

        $this->assertArrayHasKey('version', $data);
        $this->assertEquals('1.0', $data['version']);
    }

    public function testResourceCollectionWithOnly(): void
    {
        $users = [
            ['id' => 1, 'name' => 'John', 'email' => 'john@example.com'],
            ['id' => 2, 'name' => 'Jane', 'email' => 'jane@example.com'],
        ];

        $collection = ResourceCollection::make($users, get_class($this->createUserResource([])))
            ->only(['id', 'name']);

        $serialized = $collection->jsonSerialize();
        $data = $serialized['data'];

        $this->assertArrayHasKey('id', $data[0]);
        $this->assertArrayHasKey('name', $data[0]);
        $this->assertArrayNotHasKey('email', $data[0]);
    }

    public function testResourceCollectionIsEmpty(): void
    {
        $collection = ResourceCollection::make([], get_class($this->createUserResource([])));

        $this->assertTrue($collection->isEmpty());
        $this->assertFalse($collection->isNotEmpty());
    }

    public function testResourceCollectionFirst(): void
    {
        $users = [
            ['id' => 1, 'name' => 'John', 'email' => 'john@example.com'],
            ['id' => 2, 'name' => 'Jane', 'email' => 'jane@example.com'],
        ];

        $collection = ResourceCollection::make($users, get_class($this->createUserResource([])));

        $first = $collection->first();

        $this->assertNotNull($first);
        $this->assertEquals(1, $first->id());
    }

    public function testResourceCollectionTake(): void
    {
        $users = [
            ['id' => 1, 'name' => 'John', 'email' => 'john@example.com'],
            ['id' => 2, 'name' => 'Jane', 'email' => 'jane@example.com'],
            ['id' => 3, 'name' => 'Bob', 'email' => 'bob@example.com'],
        ];

        $collection = ResourceCollection::make($users, get_class($this->createUserResource([])));

        $taken = $collection->take(2);

        $this->assertCount(2, $taken);
    }

    public function testResourceCollectionSkip(): void
    {
        $users = [
            ['id' => 1, 'name' => 'John', 'email' => 'john@example.com'],
            ['id' => 2, 'name' => 'Jane', 'email' => 'jane@example.com'],
            ['id' => 3, 'name' => 'Bob', 'email' => 'bob@example.com'],
        ];

        $collection = ResourceCollection::make($users, get_class($this->createUserResource([])));

        $skipped = $collection->skip(1);

        $this->assertCount(2, $skipped);
    }

    // AnonymousResourceCollection 测试
    public function testAnonymousResourceCollection(): void
    {
        $data = [
            ['id' => 1, 'name' => 'John'],
            ['id' => 2, 'name' => 'Jane'],
        ];

        $collection = AnonymousResourceCollection::make($data);

        $this->assertEquals(2, $collection->count());
    }

    public function testAnonymousResourceCollectionWithTransformer(): void
    {
        $data = [
            ['id' => 1, 'name' => 'John', 'email' => 'john@example.com'],
            ['id' => 2, 'name' => 'Jane', 'email' => 'jane@example.com'],
        ];

        $collection = AnonymousResourceCollection::make($data, function ($item) {
            return [
                'id' => $item['id'],
                'full_name' => strtoupper($item['name']),
            ];
        });

        $result = $collection->collect();

        $this->assertEquals('JOHN', $result[0]['full_name']);
        $this->assertEquals('JANE', $result[1]['full_name']);
    }

    // 辅助函数测试
    public function testResourceCollectionHelper(): void
    {
        $data = [
            ['id' => 1, 'name' => 'John'],
            ['id' => 2, 'name' => 'Jane'],
        ];

        $collection = resource_collection($data);

        $this->assertInstanceOf(AnonymousResourceCollection::class, $collection);
    }

    public function testPaginateHelper(): void
    {
        $data = [
            ['id' => 1, 'name' => 'John'],
            ['id' => 2, 'name' => 'Jane'],
        ];

        $paginated = paginate($data, 10, 2, 1);

        $json = $paginated->jsonSerialize();

        $this->assertArrayHasKey('meta', $json);
        $this->assertEquals(10, $json['meta']['pagination']['total']);
        $this->assertEquals(1, $json['meta']['pagination']['current_page']);
    }
}
