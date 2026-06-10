<?php

declare(strict_types=1);

namespace Tests;

use Attribute;
use Bin\Container\Container;
use Bin\Container\Exceptions\BindingResolutionException;
use Bin\Contracts\ContextualAttribute;
use Bin\Testing\TestCase;
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
