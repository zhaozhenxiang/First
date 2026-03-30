<?php

declare(strict_types=1);

namespace Tests;

use Bin\Database\Attribute;
use Bin\Database\Model;
use Bin\Testing\TestCase;

class AccessorMutatorTest extends TestCase
{
    protected Model $model;

    public function setUp(): void
    {
        parent::setUp();

        // 使用匿名类定义带访问器/修改器的模型
        $this->model = new class extends Model {
            protected string $table = 'test_models';
            protected array $guarded = [];

            // 命名约定访问器
            public function getNameAttribute(?string $value): string
            {
                return ucfirst($value ?? '');
            }

            // 命名约定修改器
            public function setEmailAttribute(string $value): void
            {
                $this->attributes['email'] = strtolower($value);
            }

            // Attribute 类风格
            protected function password(): Attribute
            {
                return Attribute::make(
                    get: fn(?string $value) => $value ? '***' : null,
                    set: fn(string $value) => password_hash($value, PASSWORD_DEFAULT),
                );
            }

            // 计算属性
            protected function fullName(): Attribute
            {
                return Attribute::get(fn() => $this->attributes['name'] ?? '');
            }
        };

        $this->model->setRawAttributes([
            'id' => 1,
            'name' => 'john',
            'email' => 'JOHN@TEST.COM',
            'password' => 'hashed_password',
        ]);
        $this->model->exists = true;
    }

    // === 命名约定访问器 ===

    public function testAccessorTransformsValue(): void
    {
        $this->assertEquals('John', $this->model->name);
    }

    public function testAccessorWithNullValue(): void
    {
        $model = new class extends Model {
            protected string $table = 'test';
            protected array $guarded = [];
            public function getNameAttribute(?string $value): string
            {
                return ucfirst($value ?? '');
            }
        };
        $model->setRawAttributes(['name' => null]);
        $this->assertEquals('', $model->name);
    }

    // === 命名约定修改器 ===

    public function testMutatorTransformsValue(): void
    {
        $model = new class extends Model {
            protected string $table = 'test';
            protected array $guarded = [];
            public function setEmailAttribute(string $value): void
            {
                $this->attributes['email'] = strtolower($value);
            }
        };
        $model->setAttribute('email', 'HELLO@WORLD.COM');
        $this->assertEquals('hello@world.com', $model->getAttributes()['email']);
    }

    // === Attribute 类访问器 ===

    public function testAttributeClassAccessor(): void
    {
        $this->assertEquals('***', $this->model->password);
    }

    public function testAttributeClassMutator(): void
    {
        $model = new class extends Model {
            protected string $table = 'test';
            protected array $guarded = [];
            protected function password(): Attribute
            {
                return Attribute::make(
                    get: fn($v) => $v,
                    set: fn(string $v) => password_hash($v, PASSWORD_DEFAULT),
                );
            }
        };
        $model->setAttribute('password', 'secret123');
        $this->assertNotEquals('secret123', $model->getAttributes()['password']);
        $this->assertTrue(password_verify('secret123', $model->getAttributes()['password']));
    }

    // === 计算属性和 appends ===

    public function testAppendedAttributeInToArray(): void
    {
        $this->model->setAppends(['full_name']);
        $array = $this->model->toArray();
        $this->assertArrayHasKey('full_name', $array);
        $this->assertEquals('john', $array['full_name']);
    }

    public function testAppendMethodAddsAttribute(): void
    {
        $model = new class extends Model {
            protected string $table = 'test';
            protected array $guarded = [];
            public function getAvatarUrlAttribute(): string
            {
                return 'https://example.com/avatar.png';
            }
        };

        $model->setRawAttributes(['id' => 1]);
        $array = $model->toArray();
        $this->assertArrayNotHasKey('avatar_url', $array);

        $model->append('avatar_url');
        $array = $model->toArray();
        $this->assertArrayHasKey('avatar_url', $array);
        $this->assertEquals('https://example.com/avatar.png', $array['avatar_url']);
    }

    public function testSetAppends(): void
    {
        $this->model->setAppends([]);
        $array = $this->model->toArray();
        $this->assertArrayNotHasKey('full_name', $array);
    }

    // === getOriginal 不受访问器影响 ===

    public function testGetOriginalReturnsRawValue(): void
    {
        $this->assertEquals('john', $this->model->getOriginal('name'));
    }

    // === Attribute 类的 make/get/set 工厂方法 ===

    public function testAttributeMakeFactory(): void
    {
        $attr = Attribute::make(
            get: fn($v) => strtoupper($v),
            set: fn($v) => strtolower($v),
        );
        $this->assertNotNull($attr->get);
        $this->assertNotNull($attr->set);
    }

    public function testAttributeGetOnlyFactory(): void
    {
        $attr = Attribute::get(fn($v) => strtoupper($v));
        $this->assertNotNull($attr->get);
        $this->assertNull($attr->set);
    }

    public function testAttributeSetOnlyFactory(): void
    {
        $attr = Attribute::set(fn($v) => strtolower($v));
        $this->assertNull($attr->get);
        $this->assertNotNull($attr->set);
    }
}

return new AccessorMutatorTest();
