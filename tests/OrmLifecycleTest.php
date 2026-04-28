<?php

declare(strict_types=1);

namespace Tests;

use Bin\App\App;
use Bin\Database\Collection;
use Bin\Database\Model;
use Bin\Database\ModelNotFoundException;
use Bin\Exception\ExceptionHandler;
use Bin\Exception\NotFoundHttpException;
use Bin\Request\Request;
use Bin\Route\RouteBinding;
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

    public function testFindOrReturnsModelForStringPrimaryKey(): void
    {
        $token = OrmLifecycleToken::findOr('tok_1', fn() => 'fallback');

        $this->assertInstanceOf(OrmLifecycleToken::class, $token);
        $this->assertSame('tok_1', $token->uuid);
    }

    public function testFindOrReturnsFallbackForMissingStringPrimaryKey(): void
    {
        $result = OrmLifecycleToken::findOr('missing', fn() => 'fallback');

        $this->assertSame('fallback', $result);
    }

    public function testQueryBuilderFindOrFailUsesStringPrimaryKey(): void
    {
        $token = OrmLifecycleToken::query()->findOrFail('tok_1');

        $this->assertInstanceOf(OrmLifecycleToken::class, $token);
        $this->assertSame('tok_1', $token->uuid);
    }

    public function testDestroyPreservesStringPrimaryKey(): void
    {
        $this->assertSame(1, OrmLifecycleToken::destroy('tok_1'));
        $this->assertNull(OrmLifecycleToken::find('tok_1'));
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

    public function testHydrateBypassesMassAssignmentAndSynchronizesOriginal(): void
    {
        $models = OrmLifecycleGuardedToken::hydrate([
            ['uuid' => 'raw_1', 'label' => 'Raw guarded token', 'active' => 1],
        ]);

        $this->assertInstanceOf(Collection::class, $models);
        $this->assertCount(1, $models);

        $model = $models->first();

        $this->assertInstanceOf(OrmLifecycleGuardedToken::class, $model);
        $this->assertTrue($model->exists);
        $this->assertFalse($model->wasRecentlyCreated());
        $this->assertSame('raw_1', $model->uuid);
        $this->assertSame('Raw guarded token', $model->label);
        $this->assertSame($model->getAttributes(), $model->getOriginal());
        $this->assertTrue($model->isClean());
    }

    public function testQueryHydrationUsesModelLifecycleAndFiresRetrievedOnce(): void
    {
        $retrieved = [];
        OrmLifecycleToken::retrieved(function (OrmLifecycleToken $token) use (&$retrieved): void {
            $retrieved[] = $token->uuid;
        });

        $token = OrmLifecycleToken::where('uuid', 'tok_1')->first();

        $this->assertInstanceOf(OrmLifecycleToken::class, $token);
        $this->assertSame(['tok_1'], $retrieved);
        $this->assertTrue($token->exists);
        $this->assertTrue($token->isClean());
    }

    public function testFindOrFailThrowsModelNotFoundExceptionWithModelAndIds(): void
    {
        try {
            OrmLifecycleToken::findOrFail('missing_token');
            $this->fail('Expected ModelNotFoundException was not thrown');
        } catch (ModelNotFoundException $e) {
            $this->assertSame(OrmLifecycleToken::class, $e->getModel());
            $this->assertSame(['missing_token'], $e->getIds());
            $this->assertStringContainsString(OrmLifecycleToken::class, $e->getMessage());
            $this->assertStringContainsString('missing_token', $e->getMessage());
        }
    }

    public function testModelNotFoundExceptionFormatsComplexIdsWithoutWarnings(): void
    {
        $ids = [['tenant' => 'acme', 'uuid' => 'missing'], null];

        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        try {
            $exception = new ModelNotFoundException(OrmLifecycleToken::class, $ids);
        } finally {
            restore_error_handler();
        }

        $this->assertInstanceOf(ModelNotFoundException::class, $exception);
        $this->assertSame($ids, $exception->getIds());
        $this->assertStringContainsString('"tenant":"acme"', $exception->getMessage());
        $this->assertStringContainsString('"uuid":"missing"', $exception->getMessage());
        $this->assertStringContainsString('null', $exception->getMessage());
    }

    public function testModelNotFoundExceptionFormatsAmbiguousScalarAndStringableIds(): void
    {
        $stringable = new class {
            public function __toString(): string
            {
                return 'stringable-id';
            }
        };
        $ids = [false, '', $stringable];

        $exception = new ModelNotFoundException(OrmLifecycleToken::class, $ids);

        $this->assertSame($ids, $exception->getIds());
        $this->assertStringContainsString('false', $exception->getMessage());
        $this->assertStringContainsString('""', $exception->getMessage());
        $this->assertStringContainsString('stringable-id', $exception->getMessage());
    }

    public function testQueryBuilderFindOrFailThrowsModelNotFoundException(): void
    {
        try {
            OrmLifecycleToken::query()->findOrFail('missing_token');
            $this->fail('Expected ModelNotFoundException was not thrown');
        } catch (ModelNotFoundException $e) {
            $this->assertSame(OrmLifecycleToken::class, $e->getModel());
            $this->assertSame(['missing_token'], $e->getIds());
        }
    }

    public function testRouteBindingTranslatesModelNotFoundExceptionToHttp404(): void
    {
        RouteBinding::model('token', OrmLifecycleToken::class);

        try {
            RouteBinding::resolve('token', 'missing_token');
            $this->fail('Expected NotFoundHttpException was not thrown');
        } catch (NotFoundHttpException $e) {
            $this->assertStringContainsString(OrmLifecycleToken::class, $e->getMessage());
        } finally {
            RouteBinding::clear();
        }
    }

    public function testExceptionHandlerRendersModelNotFoundAsJson404(): void
    {
        $app = App::getInstance();
        $app->instance(Request::class, new Request(
            query: [],
            post: [],
            server: ['HTTP_ACCEPT' => 'application/json'],
            cookies: []
        ));

        try {
            $handler = new ExceptionHandler(false);
            $response = $handler->render(new ModelNotFoundException(OrmLifecycleToken::class, ['missing_token']));

            $this->assertEquals(404, $response->getStatusCode());
            $this->assertEquals('application/json', $response->getHeader('Content-Type'));
            $this->assertStringContainsString('"status":404', (string) $response);
            $this->assertStringContainsString('Not Found', (string) $response);
        } finally {
            $app->forget(Request::class);
            $app->singleton(Request::class, Request::class);
        }
    }

    public function testRefreshOrFailThrowsWhenPersistedRowDisappears(): void
    {
        $token = OrmLifecycleToken::findOrFail('tok_1');
        $this->connection->exec("DELETE FROM orm_lifecycle_tokens WHERE uuid = 'tok_1'");

        try {
            $token->refreshOrFail();
            $this->fail('Expected ModelNotFoundException was not thrown');
        } catch (ModelNotFoundException $e) {
            $this->assertSame(OrmLifecycleToken::class, $e->getModel());
            $this->assertSame(['tok_1'], $e->getIds());
        }
    }

    public function testRefreshOrFailReloadsDatabaseStateAndClearsDirtyAttributes(): void
    {
        $token = OrmLifecycleToken::findOrFail('tok_1');
        $token->label = 'Dirty local label';
        $this->assertTrue($token->isDirty('label'));

        $this->connection->exec("UPDATE orm_lifecycle_tokens SET label = 'Reloaded token' WHERE uuid = 'tok_1'");

        $same = $token->refreshOrFail();

        $this->assertSame($token, $same);
        $this->assertSame('Reloaded token', $token->label);
        $this->assertTrue($token->isClean());
    }

    public function testUpdateWhereTreatsFirstArgumentAsConditionsAndSecondAsValues(): void
    {
        $affected = OrmLifecycleToken::updateWhere(
            ['uuid' => 'tok_2'],
            ['label' => 'Updated through helper', 'active' => 1]
        );

        $this->assertSame(1, $affected);

        $token = OrmLifecycleToken::findOrFail('tok_2');
        $this->assertSame('Updated through helper', $token->label);
        $this->assertSame(1, $token->active);
    }

    public function testM2cDataAccessMainLifecycleIsStableEndToEnd(): void
    {
        $created = new OrmLifecycleToken([
            'uuid' => 'tok_e2e',
            'label' => 'Created token',
            'active' => 1,
        ]);

        $this->assertTrue($created->save());
        $this->assertTrue($created->exists);
        $this->assertTrue($created->wasRecentlyCreated());
        $this->assertTrue($created->isClean());

        $queried = OrmLifecycleToken::where('active', 1)
            ->whereIn('uuid', ['tok_1', 'tok_e2e'])
            ->orderBy('uuid')
            ->get();

        $this->assertCount(2, $queried);
        $this->assertContains('tok_e2e', $queried->pluck('uuid'));
        $this->assertContains('tok_1', $queried->pluck('uuid'));
        $this->assertInstanceOf(OrmLifecycleToken::class, $queried->first());

        $created->label = 'Updated locally';
        $this->assertTrue($created->isDirty('label'));
        $this->assertTrue($created->save());
        $this->assertTrue($created->isClean());
        $this->assertSame('Updated locally', OrmLifecycleToken::findOrFail('tok_e2e')->label);

        $this->connection->exec("UPDATE orm_lifecycle_tokens SET label = 'Externally updated' WHERE uuid = 'tok_e2e'");
        $created->refreshOrFail();

        $this->assertSame('Externally updated', $created->label);
        $this->assertTrue($created->delete());
        $this->assertFalse($created->exists);
        $this->assertNull(OrmLifecycleToken::find('tok_e2e'));
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

class OrmLifecycleGuardedToken extends OrmLifecycleToken
{
    protected array $guarded = ['*'];
}

return new OrmLifecycleTest();
