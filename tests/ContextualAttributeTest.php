<?php

declare(strict_types=1);

namespace Tests;

use Attribute;
use Bin\Auth\AuthManager;
use Bin\Cache\CacheManager;
use Bin\Cache\CacheRepository;
use Bin\Container\Attributes\Auth as AuthAttribute;
use Bin\Container\Attributes\Cache as CacheAttribute;
use Bin\Container\Attributes\Config as ConfigAttribute;
use Bin\Container\Attributes\Db as DbAttribute;
use Bin\Container\Attributes\Give;
use Bin\Container\Attributes\Log as LogAttribute;
use Bin\Container\Attributes\RouteParameter;
use Bin\Container\Attributes\Storage as StorageAttribute;
use Bin\Container\Attributes\Tag;
use Bin\Container\Container;
use Bin\Container\Exceptions\BindingResolutionException;
use Bin\Contracts\ContextualAttribute;
use Bin\Database\ConnectionManager;
use Bin\Filesystem\Filesystem;
use Bin\Filesystem\StorageManager;
use Bin\Log\Logger;
use Bin\Log\LogManager;
use Bin\Testing\TestCase;
use PDO;
use ReflectionParameter;

class ContextualAttributeTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = new Container();
    }

    public function testCustomAttributeResolvesConstructorPrimitive(): void
    {
        $service = $this->container->make(ContextualAttributeTest_ConstructorTarget::class);

        $this->assertSame('from-attribute', $service->value);
    }

    public function testCustomAttributeResolvesMethodPrimitive(): void
    {
        $result = $this->container->call(function (
            #[ContextualAttributeTest_Value('method-attribute')]
            string $value
        ): string {
            return $value;
        });

        $this->assertSame('method-attribute', $result);
    }

    public function testExplicitCallParameterOverridesMethodAttribute(): void
    {
        $result = $this->container->call(function (
            #[ContextualAttributeTest_Value('method-attribute')]
            string $value
        ): string {
            return $value;
        }, ['value' => 'explicit']);

        $this->assertSame('explicit', $result);
    }

    public function testConstructorAttributeWinsBeforeLegacyContextualBinding(): void
    {
        $this->container
            ->when(ContextualAttributeTest_ContextualConflict::class)
            ->needs('value')
            ->give('from-legacy-contextual');

        $service = $this->container->make(ContextualAttributeTest_ContextualConflict::class);

        $this->assertSame('from-attribute', $service->value);
    }

    public function testLegacyContextualBindingStillWorksWithoutAttribute(): void
    {
        $this->container
            ->when(ContextualAttributeTest_LegacyContextualOnly::class)
            ->needs('value')
            ->give('from-legacy-contextual');

        $service = $this->container->make(ContextualAttributeTest_LegacyContextualOnly::class);

        $this->assertSame('from-legacy-contextual', $service->value);
    }

    public function testNonContextualPhpAttributesAreIgnored(): void
    {
        $service = $this->container->make(ContextualAttributeTest_IgnoredAttributeTarget::class);

        $this->assertSame('default-value', $service->value);
    }

    public function testAttributeResolutionFailureIncludesParameterAndAttribute(): void
    {
        try {
            $this->container->make(ContextualAttributeTest_ThrowingAttributeTarget::class);
            $this->fail('Expected BindingResolutionException');
        } catch (BindingResolutionException $exception) {
            $this->assertSame('value', $exception->getAbstract());
            $this->assertStringContainsString('ContextualAttributeTest_Explodes', $exception->getMessage());
            $this->assertStringContainsString('value', $exception->getMessage());
            $this->assertStringContainsString('boom', $exception->getMessage());
        }
    }

    public function testConfigAttributeInjectsConfiguredValue(): void
    {
        \config(['app.contextual_attribute_test' => 'configured']);

        $result = $this->container->call(function (
            #[ConfigAttribute('app.contextual_attribute_test')]
            string $value
        ): string {
            return $value;
        });

        $this->assertSame('configured', $result);
    }

    public function testConfigAttributeInjectsDefaultForMissingKey(): void
    {
        $result = $this->container->call(function (
            #[ConfigAttribute('app.contextual_attribute_missing', 'fallback')]
            string $value
        ): string {
            return $value;
        });

        $this->assertSame('fallback', $result);
    }

    public function testCacheAttributeInjectsNamedStore(): void
    {
        CacheManager::resetInstance();
        CacheManager::getInstance()->setConfigFor([
            'array' => ['driver' => 'array'],
        ]);
        CacheManager::getInstance()->setDefaultStoreFor('array');

        $cache = $this->container->call(function (
            #[CacheAttribute('array')]
            CacheRepository $cache
        ): CacheRepository {
            return $cache;
        });

        $this->assertInstanceOf(CacheRepository::class, $cache);
    }

    public function testDbAttributeInjectsActivePdoConnection(): void
    {
        $pdo = new PDO('sqlite::memory:');
        ConnectionManager::setConnection($pdo);

        try {
            $resolved = $this->container->call(function (
                #[DbAttribute]
                PDO $connection
            ): PDO {
                return $connection;
            });
        } finally {
            ConnectionManager::reset();
        }

        $this->assertSame($pdo, $resolved);
    }

    public function testStorageAttributeInjectsDisk(): void
    {
        StorageManager::resetInstance();
        StorageManager::getInstance()
            ->setConfig([
                'local' => [
                    'driver' => 'local',
                    'root' => sys_get_temp_dir(),
                ],
            ])
            ->setDefaultDisk('local');

        $disk = $this->container->call(function (
            #[StorageAttribute('local')]
            Filesystem $disk
        ): Filesystem {
            return $disk;
        });

        $this->assertInstanceOf(Filesystem::class, $disk);
    }

    public function testLogAttributeInjectsChannel(): void
    {
        LogManager::resetInstance();

        $logger = $this->container->call(function (
            #[LogAttribute('audit')]
            Logger $logger
        ): Logger {
            return $logger;
        });

        $this->assertSame(LogManager::getInstance()->channelFor('audit'), $logger);
    }

    public function testAuthAttributeInjectsDefaultAuthManager(): void
    {
        AuthManager::resetInstance();

        $auth = $this->container->call(function (
            #[AuthAttribute]
            AuthManager $auth
        ): AuthManager {
            return $auth;
        });

        $this->assertSame(AuthManager::getInstance(), $auth);
    }

    public function testRouteParameterAttributeReadsCallContext(): void
    {
        $result = $this->container->call(function (
            #[RouteParameter('slug')]
            string $slug
        ): string {
            return $slug;
        }, ['slug' => 'from-call-context']);

        $this->assertSame('from-call-context', $result);
    }

    public function testRouteParameterAttributeUsesDefaultWhenMissing(): void
    {
        $result = $this->container->call(function (
            #[RouteParameter('missing')]
            string $value = 'fallback'
        ): string {
            return $value;
        });

        $this->assertSame('fallback', $result);
    }

    public function testRouteParameterAttributeThrowsForMissingRequiredValue(): void
    {
        try {
            $this->container->call(function (
                #[RouteParameter('missing')]
                string $value
            ): string {
                return $value;
            });
            $this->fail('Expected BindingResolutionException');
        } catch (BindingResolutionException $exception) {
            $this->assertSame('value', $exception->getAbstract());
            $this->assertStringContainsString('route parameter [missing]', $exception->getMessage());
        }
    }

    public function testTagAttributeInjectsTaggedServices(): void
    {
        $this->container->bind('contextual.handler.one', ContextualAttributeTest_TaggedOne::class);
        $this->container->bind('contextual.handler.two', ContextualAttributeTest_TaggedTwo::class);
        $this->container->tag(['contextual.handler.one', 'contextual.handler.two'], 'contextual.handlers');

        $handlers = $this->container->call(function (
            #[Tag('contextual.handlers')]
            array $handlers
        ): array {
            return $handlers;
        });

        $this->assertCount(2, $handlers);
        $this->assertInstanceOf(ContextualAttributeTest_TaggedOne::class, $handlers[0]);
        $this->assertInstanceOf(ContextualAttributeTest_TaggedTwo::class, $handlers[1]);
    }

    public function testGiveAttributeInjectsExplicitImplementation(): void
    {
        $service = $this->container->call(function (
            #[Give(ContextualAttributeTest_GivenImplementation::class)]
            ContextualAttributeTest_GivenContract $service
        ): ContextualAttributeTest_GivenContract {
            return $service;
        });

        $this->assertInstanceOf(ContextualAttributeTest_GivenImplementation::class, $service);
    }

    public function testNonDefaultAuthGuardFailsClearly(): void
    {
        try {
            $this->container->call(function (
                #[AuthAttribute('admin')]
                AuthManager $auth
            ): AuthManager {
                return $auth;
            });
            $this->fail('Expected BindingResolutionException');
        } catch (BindingResolutionException $exception) {
            $this->assertStringContainsString('Auth guard [admin]', $exception->getMessage());
        }
    }

    public function testNamedDbConnectionFailsClearly(): void
    {
        try {
            $this->container->call(function (
                #[DbAttribute('analytics')]
                PDO $connection
            ): PDO {
                return $connection;
            });
            $this->fail('Expected BindingResolutionException');
        } catch (BindingResolutionException $exception) {
            $this->assertStringContainsString('Database connection [analytics]', $exception->getMessage());
        }
    }
}

#[Attribute(Attribute::TARGET_PARAMETER)]
class ContextualAttributeTest_Value implements ContextualAttribute
{
    public function __construct(private string $value)
    {
    }

    public function resolve(Container $container, ReflectionParameter $parameter): mixed
    {
        return $this->value;
    }
}

#[Attribute(Attribute::TARGET_PARAMETER)]
class ContextualAttributeTest_IgnoredAttribute
{
}

#[Attribute(Attribute::TARGET_PARAMETER)]
class ContextualAttributeTest_Explodes implements ContextualAttribute
{
    public function resolve(Container $container, ReflectionParameter $parameter): mixed
    {
        throw new \RuntimeException('boom');
    }
}

class ContextualAttributeTest_ConstructorTarget
{
    public function __construct(
        #[ContextualAttributeTest_Value('from-attribute')]
        public string $value
    ) {
    }
}

class ContextualAttributeTest_ContextualConflict
{
    public function __construct(
        #[ContextualAttributeTest_Value('from-attribute')]
        public string $value
    ) {
    }
}

class ContextualAttributeTest_LegacyContextualOnly
{
    public function __construct(public string $value)
    {
    }
}

class ContextualAttributeTest_IgnoredAttributeTarget
{
    public function __construct(
        #[ContextualAttributeTest_IgnoredAttribute]
        public string $value = 'default-value'
    ) {
    }
}

class ContextualAttributeTest_ThrowingAttributeTarget
{
    public function __construct(
        #[ContextualAttributeTest_Explodes]
        public string $value
    ) {
    }
}

class ContextualAttributeTest_TaggedOne
{
}

class ContextualAttributeTest_TaggedTwo
{
}

interface ContextualAttributeTest_GivenContract
{
}

class ContextualAttributeTest_GivenImplementation implements ContextualAttributeTest_GivenContract
{
}
