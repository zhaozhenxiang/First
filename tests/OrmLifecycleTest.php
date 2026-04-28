<?php

declare(strict_types=1);

namespace Tests;

use Bin\Database\Collection;
use Bin\Database\Model;
use Bin\Testing\TestCase;
use PDO;

class OrmLifecycleTest extends TestCase
{
    private PDO $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = new PDO('sqlite::memory:');
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->connection->exec('
            CREATE TABLE orm_lifecycle_tokens (
                uuid TEXT PRIMARY KEY,
                label TEXT NOT NULL,
                active INTEGER NOT NULL DEFAULT 1
            )
        ');
        $this->connection->exec(
            "INSERT INTO orm_lifecycle_tokens (uuid, label, active) VALUES ('tok_1', 'First token', 1)"
        );
        $this->connection->exec(
            "INSERT INTO orm_lifecycle_tokens (uuid, label, active) VALUES ('tok_2', 'Second token', 0)"
        );

        OrmLifecycleToken::resetBooted();
        OrmLifecycleToken::flushEventListeners();
        OrmLifecycleToken::setConnection($this->connection);
    }

    protected function tearDown(): void
    {
        OrmLifecycleToken::flushEventListeners();
        OrmLifecycleToken::resetBooted();
        OrmLifecycleToken::setConnection(null);

        parent::tearDown();
    }

    public function testFindUsesModelPrimaryKeyForStringKeys(): void
    {
        $token = OrmLifecycleToken::find('tok_1');

        $this->assertInstanceOf(OrmLifecycleToken::class, $token);
        $this->assertSame('tok_1', $token->uuid);
        $this->assertSame('First token', $token->label);
        $this->assertTrue($token->exists);
        $this->assertTrue($token->isClean());
    }

    public function testFindManyUsesModelPrimaryKeyForStringKeys(): void
    {
        $tokens = OrmLifecycleToken::findMany(['tok_1', 'tok_2']);

        $this->assertInstanceOf(Collection::class, $tokens);
        $this->assertCount(2, $tokens);
        $this->assertSame(['tok_1', 'tok_2'], $tokens->pluck('uuid'));
    }

    public function testNonIncrementingSavePreservesCallerProvidedPrimaryKey(): void
    {
        $token = new OrmLifecycleToken([
            'uuid' => 'tok_3',
            'label' => 'Third token',
            'active' => 1,
        ]);

        $this->assertFalse($token->exists);

        $this->assertTrue($token->save());

        $this->assertTrue($token->exists);
        $this->assertTrue($token->wasRecentlyCreated());
        $this->assertSame('tok_3', $token->uuid);
        $this->assertTrue($token->isClean());
        $this->assertSame('Third token', OrmLifecycleToken::find('tok_3')->label);
    }
}

class OrmLifecycleToken extends Model
{
    protected string $table = 'orm_lifecycle_tokens';
    protected string $primaryKey = 'uuid';
    protected string $keyType = 'string';
    protected bool $incrementing = false;
    protected bool $timestamps = false;
    protected array $guarded = [];
}

return new OrmLifecycleTest();
