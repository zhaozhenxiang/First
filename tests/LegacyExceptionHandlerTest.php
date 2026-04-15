<?php

declare(strict_types=1);

namespace Tests;

use Bin\Exception\Handler;
use Bin\Response\Response;
use Bin\Testing\TestCase;

class LegacyExceptionHandlerTest extends TestCase
{
    public function testExtendedHandlerSendsReturnedResponse(): void
    {
        $exception = new class ('legacy boom') extends \RuntimeException {};
        $exceptionClass = $exception::class;

        Handler::extend($exceptionClass, function (\Throwable $e) {
            return new Response('legacy-handled', 500);
        });

        ob_start();
        Handler::handle($exception);
        $output = ob_get_clean();

        $this->assertEquals('legacy-handled', $output);
    }

    public function testLegacyHandlerRendersProductionResponse(): void
    {
        $reflection = new \ReflectionClass(Handler::class);
        $method = $reflection->getMethod('renderProductionResponse');
        $method->setAccessible(true);

        $response = $method->invoke(null, 500);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertStringContainsString('Internal Server Error', $response->getContent());
        $this->assertStringContainsString('<!DOCTYPE html>', $response->getContent());
    }
}
